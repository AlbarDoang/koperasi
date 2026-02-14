<?php
// Storage configuration for KYC files
// IMPORTANT: directory paths must be outside of htdocs/public

if (!defined('KYC_STORAGE_BASE')) {
    // Windows path example: C:\laragon\www\gas\gas_storage\verifikasi_pengguna
    define('KYC_STORAGE_BASE', 'C:' . DIRECTORY_SEPARATOR . 'laragon' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'gas' . DIRECTORY_SEPARATOR . 'gas_storage' . DIRECTORY_SEPARATOR . 'verifikasi_pengguna');
}

if (!defined('KYC_STORAGE_KTP')) define('KYC_STORAGE_KTP', KYC_STORAGE_BASE . DIRECTORY_SEPARATOR . 'ktp' . DIRECTORY_SEPARATOR);
if (!defined('KYC_STORAGE_SELFIE')) define('KYC_STORAGE_SELFIE', KYC_STORAGE_BASE . DIRECTORY_SEPARATOR . 'selfie' . DIRECTORY_SEPARATOR);

// Limits and allowed types
if (!defined('KYC_MAX_FILE_SIZE')) define('KYC_MAX_FILE_SIZE', 15 * 1024 * 1024); // 15MB
// NOTE: This is a per-file limit for KYC PHOTOS. Ensure PHP server config is compatible:
// - upload_max_filesize should be >= KYC_MAX_FILE_SIZE
// - post_max_size should be >= 2 * KYC_MAX_FILE_SIZE + 1MB (to allow two files + overhead)
// Keep allowed MIME list as a variable for backwards-compatibility. Add 'image/heic' if you want to accept HEIC.
$KYC_ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/heic'];

// Ensure directory exists and is writable
function kyc_ensure_dir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0777, true)) return false;
    }
    if (!is_writable($path)) @chmod($path, 0777);
    return true;
}

// Generate safe unique filename with deterministic extension from mime
function kyc_generate_filename($prefix, $mime) {
    $ext = 'jpg';
    if ($mime === 'image/png') $ext = 'png';
    try {
        $rand = bin2hex(random_bytes(12));
    } catch (Throwable $e) {
        $rand = uniqid('', true);
        $rand = preg_replace('/[^a-f0-9]/', '', md5($rand));
    }
    return $prefix . '_' . $rand . '.' . $ext;
}

// Normalize input mime and file extension mapping
function kyc_mime_to_ext($mime) {
    if ($mime === 'image/png') return 'png';
    return 'jpg';
}

// Validate that a filesystem path is inside the KYC storage base (prevent path traversal)
function kyc_path_is_inside_base($path) {
    $realBase = realpath(KYC_STORAGE_BASE);
    $realPath = realpath($path);
    if ($realBase === false || $realPath === false) return false;
    // Windows paths: make case-insensitive comparison
    if (stripos(PHP_OS, 'WIN') === 0) {
        return stripos($realPath, $realBase) === 0;
    }
    return strpos($realPath, $realBase) === 0;
}

// Return per-user KYC subdirectory for ktp/selfie (ends with DIRECTORY_SEPARATOR)
function kyc_user_dir($id_pengguna, $type = 'ktp') {
    $id = intval($id_pengguna);
    if ($type === 'selfie') return KYC_STORAGE_SELFIE . $id . DIRECTORY_SEPARATOR;
    return KYC_STORAGE_KTP . $id . DIRECTORY_SEPARATOR;
}

// Ensure per-user KYC dir exists (creates with 0777 to match existing behavior)
function kyc_ensure_user_dir($id_pengguna, $type = 'ktp') {
    $d = kyc_user_dir($id_pengguna, $type);
    if (!is_dir($d)) {
        if (!mkdir($d, 0777, true)) return false;
    }
    if (!is_writable($d)) @chmod($d, 0777);
    return true;
}

// Best-effort: move files from root KYC folder into the user's subfolder if filename contains the user id
function kyc_migrate_root_files_to_user($id_pengguna) {
    $moved = [];
    $id = intval($id_pengguna);
    $patterns = [KYC_STORAGE_KTP, KYC_STORAGE_SELFIE];
    foreach ($patterns as $base) {
        if (!is_dir($base)) continue;
        $dh = opendir($base);
        if (!$dh) continue;
        while (($f = readdir($dh)) !== false) {
            if ($f === '.' || $f === '..') continue;
            $full = $base . $f;
            if (!is_file($full)) continue;
            if (strpos($f, (string)$id) !== false) {
                $t = $base . $id . DIRECTORY_SEPARATOR;
                if (!is_dir($t)) @mkdir($t, 0777, true);
                $dst = $t . $f;
                if (@rename($full, $dst)) {
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [storage_config] Migrated $full -> $dst\n", FILE_APPEND);
                    $moved[] = $dst;
                } else {
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [storage_config] Failed to migrate $full -> $dst\n", FILE_APPEND);
                }
            }
        }
        closedir($dh);
    }
    return $moved;
}

// Auto-create base and required subdirectories (ktp/selfie) when this config is included.
// This is idempotent and fails silently if permissions prevent creation.
@kyc_ensure_dir(KYC_STORAGE_BASE);
@kyc_ensure_dir(KYC_STORAGE_KTP);
@kyc_ensure_dir(KYC_STORAGE_SELFIE);

// -----------------------------------------------------------------------------
// Profile photo storage (outside document root)
// - Must be outside htdocs and not directly accessible by web server.
// - We will store files in a separate folder and only serve via a proxy endpoint
// -----------------------------------------------------------------------------
if (!defined('PROFILE_STORAGE_BASE')) {
    // Windows example: C:\laragon\www\gas\gas_storage\foto_profil
    define('PROFILE_STORAGE_BASE', 'C:' . DIRECTORY_SEPARATOR . 'laragon' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'gas' . DIRECTORY_SEPARATOR . 'gas_storage' . DIRECTORY_SEPARATOR . 'foto_profil');
}
if (!defined('PROFILE_STORAGE_PHOTO')) define('PROFILE_STORAGE_PHOTO', PROFILE_STORAGE_BASE . DIRECTORY_SEPARATOR);
if (!defined('PROFILE_MAX_FILE_SIZE')) define('PROFILE_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB// Signing secret used to generate short-lived image access tokens (change in production)
if (!defined('PROFILE_IMAGE_SECRET')) define('PROFILE_IMAGE_SECRET', getenv('PROFILE_IMAGE_SECRET') ?: 'dev-change-this-secret');$PROFILE_ALLOWED_MIMES = ['image/jpeg', 'image/png'];

// Ensure directory exists and is writable
function profile_ensure_dir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) return false;
    }
    if (!is_writable($path)) @chmod($path, 0755);
    return true;
}

// Generate secure unique filename (uses random_bytes for security)
function profile_generate_filename($prefix, $mime) {
    $ext = 'jpg';
    if ($mime === 'image/png') $ext = 'png';
    try {
        $rand = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $rand = uniqid('', true);
        $rand = preg_replace('/[^a-f0-9]/', '', md5($rand));
    }
    // prefix might be user id; sanitize to safe chars
    $safe = preg_replace('/[^0-9a-zA-Z_-]/', '', (string)$prefix);
    return $safe . '_' . $rand . '.' . $ext;
}

// Map mime to extension
function profile_mime_to_ext($mime) {
    if ($mime === 'image/png') return 'png';
    return 'jpg';
}

// Validate that a filesystem path is inside the profile storage base (prevent path traversal)
function profile_path_is_inside_base($path) {
    $realBase = realpath(PROFILE_STORAGE_BASE);
    $realPath = realpath($path);
    if ($realBase === false || $realPath === false) return false;
    if (stripos(PHP_OS, 'WIN') === 0) {
        return stripos($realPath, $realBase) === 0;
    }
    return strpos($realPath, $realBase) === 0;
}

// Auto-create profile storage directory (idempotent)
@profile_ensure_dir(PROFILE_STORAGE_BASE);

// -----------------------------------------------------------------------------
// Loan item photos storage (outside document root)
// - Structure: LOAN_STORAGE_BASE/{normalized_username}/{filename}
// - Normalized username: lowercase, spaces -> underscore, remove chars except a-z0-9_
// -----------------------------------------------------------------------------
if (!defined('LOAN_STORAGE_BASE')) {
    // Windows example: C:\laragon\www\gas\gas_storage\foto_barang_pinjaman_kredit
    define('LOAN_STORAGE_BASE', 'C:' . DIRECTORY_SEPARATOR . 'laragon' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'gas' . DIRECTORY_SEPARATOR . 'gas_storage' . DIRECTORY_SEPARATOR . 'foto_barang_pinjaman_kredit' . DIRECTORY_SEPARATOR);
}
if (!defined('LOAN_MAX_FILE_SIZE')) define('LOAN_MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB per item photo

function loan_ensure_dir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) return false;
    }
    if (!is_writable($path)) @chmod($path, 0755);
    return true;
}

// Normalize a username to folder name: lowercase, trim, spaces -> underscore, remove non a-z0-9_
function loan_normalize_username($name) {
    $s = trim((string)$name);
    $s = strtolower($s);
    // replace spaces and consecutive spaces with single underscore
    $s = preg_replace('/\s+/', '_', $s);
    // remove characters except a-z0-9_
    $s = preg_replace('/[^a-z0-9_]/', '', $s);
    // fallback to user_<rand> if empty
    if ($s === '') {
        try { $s = 'user_' . bin2hex(random_bytes(6)); } catch (Throwable $e) { $s = 'user_' . uniqid(); }
    }
    return $s . DIRECTORY_SEPARATOR;
}

function loan_generate_filename($prefix, $mime) {
    $ext = 'jpg';
    if ($mime === 'image/png') $ext = 'png';
    try { $rand = bin2hex(random_bytes(12)); } catch (Throwable $e) { $rand = preg_replace('/[^a-f0-9]/','',md5(uniqid('',true))); }
    return $prefix . '_' . time() . '_' . $rand . '.' . $ext;
}

function loan_path_is_inside_base($path) {
    $realBase = realpath(LOAN_STORAGE_BASE);
    $realPath = realpath($path);
    if ($realBase === false || $realPath === false) return false;
    if (stripos(PHP_OS, 'WIN') === 0) {
        return stripos($realPath, $realBase) === 0;
    }
    return strpos($realPath, $realBase) === 0;
}

// Return per-user loan folder by id
function loan_user_dir($id_pengguna) {
    return LOAN_STORAGE_BASE . intval($id_pengguna) . DIRECTORY_SEPARATOR;
}

function loan_ensure_user_dir($id_pengguna) {
    $d = loan_user_dir($id_pengguna);
    if (!is_dir($d)) {
        if (!mkdir($d, 0755, true)) return false;
    }
    if (!is_writable($d)) @chmod($d, 0755);
    return true;
}

// Best-effort: migrate files from LOAN_STORAGE_BASE root into user's folder when filename contains user id
function loan_migrate_root_files_to_user($id_pengguna) {
    $moved = [];
    $id = intval($id_pengguna);
    if (!is_dir(LOAN_STORAGE_BASE)) return $moved;
    $dh = @opendir(LOAN_STORAGE_BASE);
    if (!$dh) return $moved;
    while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..') continue;
        $full = LOAN_STORAGE_BASE . $f;
        if (!is_file($full)) continue;
        if (strpos($f, (string)$id) !== false) {
            $t = loan_user_dir($id);
            if (!is_dir($t)) @mkdir($t, 0755, true);
            $dst = $t . $f;
            if (@rename($full, $dst)) {
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [storage_config] Migrated loan file $full -> $dst\n", FILE_APPEND);
                $moved[] = $dst;
            } else {
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [storage_config] Failed to migrate loan file $full -> $dst\n", FILE_APPEND);
            }
        }
    }
    closedir($dh);
    return $moved;
}

// Auto-create loan storage base
@loan_ensure_dir(LOAN_STORAGE_BASE);

