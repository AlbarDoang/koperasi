<?php
// Aktifkan error reporting untuk debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ============================================================================
// FILE: register_tahap2.php
// FUNGSI: Menerima file foto KTP dan selfie dari Flutter, simpan ke folder
//         foto_verifikasi, dan insert ke database tabungan tabel 
//         verifikasi_pengguna
// ============================================================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Tangani preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// 1. KONEKSI DATABASE KE "tabungan"
// ============================================================================

// Use central bootstrap to ensure output buffering and consistent shutdown handling
require_once __DIR__ . '/api_bootstrap.php';

// Helper: clean any stray output (HTML/warnings) before emitting JSON
if (!function_exists('safeJsonResponse')) {
    function safeJsonResponse($success, $message, $data = null) {
        // Capture any unexpected buffered output and log it, then discard
        if (ob_get_level() && ($buf = ob_get_clean()) !== null) {
            $trim = trim($buf);
            if ($trim !== '') {
                $script = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'unknown');
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $summary = mb_substr($trim, 0, 2000);
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Unexpected buffered output before JSON for {$script} {$uri} {$ip}: " . $summary . "\n", FILE_APPEND);
            }
            // Start a fresh buffer to keep connection.php behavior consistent
            if (ob_get_level() === 0) ob_start();
        }
        // Delegate to central sendJsonResponse which sets the FLUTTER_API_JSON_OUTPUT flag
        sendJsonResponse($success, $message, $data);
    }
}

@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] START from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] SERVER: CONTENT_LENGTH=" . ($_SERVER['CONTENT_LENGTH'] ?? '') . " post_max_size=" . ini_get('post_max_size') . " upload_max_filesize=" . ini_get('upload_max_filesize') . "\n", FILE_APPEND);

$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "tabungan";  // Database yang benar

$koneksi = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($koneksi->connect_error) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] DB connect failed: " . $koneksi->connect_error . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Koneksi database gagal: ' . $koneksi->connect_error);
} 

// Set charset UTF-8
$koneksi->set_charset("utf8mb4");

// ============================================================================
// 2. VALIDASI REQUEST METHOD
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safeJsonResponse(false, 'Hanya POST method yang diperbolehkan.');
} 

// ============================================================================
// 3. VALIDASI INPUT: ID PENGGUNA
// ============================================================================
if (!isset($_POST['id_pengguna']) || empty($_POST['id_pengguna'])) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Missing id_pengguna; SERVER: CONTENT_LENGTH=" . ($_SERVER['CONTENT_LENGTH'] ?? '') . " REQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n", FILE_APPEND);
    safeJsonResponse(false, 'ID pengguna tidak ditemukan atau kosong.');
} 

$id_pengguna = intval($_POST['id_pengguna']);

if ($id_pengguna <= 0) {
    safeJsonResponse(false, 'ID pengguna harus berupa angka positif.');
} 

// ============================================================================
// NEW: VALIDASI EKSISTENSI PENGGUNA
// ============================================================================
$checkUser = $koneksi->prepare("SELECT 1 FROM pengguna WHERE id = ? LIMIT 1");
if (!$checkUser) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] User check prepare failed: " . $koneksi->error . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Gagal memeriksa pengguna: ' . $koneksi->error);
}
$checkUser->bind_param('i', $id_pengguna);
$checkUser->execute();
$checkUser->store_result();
if ($checkUser->num_rows === 0) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Missing pengguna id={$id_pengguna}\n", FILE_APPEND);
    safeJsonResponse(false, 'ID pengguna tidak ditemukan.');
}
$checkUser->close();

// ============================================================================
// 4. QUICK CONTENT-LENGTH CHECK
// ============================================================================
// Compute a reasonable POST payload cap that accommodates up to two files of KYC_MAX_FILE_SIZE
$maxPost = max(10 * 1024 * 1024, 2 * KYC_MAX_FILE_SIZE + 1 * 1024 * 1024); // bytes (min 10MB, otherwise 2 * per-file + 1MB overhead)
if (!empty($_SERVER['CONTENT_LENGTH']) && intval($_SERVER['CONTENT_LENGTH']) > $maxPost) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Oversized POST detected: " . ($_SERVER['CONTENT_LENGTH'] ?? '') . " (max {$maxPost})\n", FILE_APPEND);
    safeJsonResponse(false, 'Ukuran upload terlalu besar (max ' . intval($maxPost / (1024*1024)) . 'MB)');
} 

// ============================================================================
// ============================================================================
// If both $_POST and $_FILES are empty but client sent a large payload, check server post_max_size
function parse_size($size) {
    $unit = strtolower(substr(trim($size), -1));
    $val = (int)$size;
    if ($unit === 'g') $val *= 1024 * 1024 * 1024;
    elseif ($unit === 'm') $val *= 1024 * 1024;
    elseif ($unit === 'k') $val *= 1024;
    return $val;
}
if (empty($_POST) && empty($_FILES) && !empty($_SERVER['CONTENT_LENGTH'])) {
    $postLimit = ini_get('post_max_size');
    if ($postLimit && parse_size($postLimit) < (2 * KYC_MAX_FILE_SIZE)) {
        safeJsonResponse(false, 'Ukuran upload terlalu besar atau server membatasi POST. Pastikan upload_max_filesize/post_max_size di php.ini >= ' . intval((2 * KYC_MAX_FILE_SIZE) / (1024*1024)) . 'MB.');
    }
}

// Detailed file error checks
if (!isset($_FILES['foto_ktp'])) {
    safeJsonResponse(false, 'Foto KTP tidak ditemukan atau gagal upload. Pastikan ukuran/format dan konfigurasi server (upload_max_filesize/post_max_size).');
}
if ($_FILES['foto_ktp']['error'] !== UPLOAD_ERR_OK) {
    if (in_array($_FILES['foto_ktp']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
        safeJsonResponse(false, 'Ukuran foto KTP terlalu besar menurut konfigurasi server (upload_max_filesize/post_max_size). Pastikan >= ' . intval(KYC_MAX_FILE_SIZE / (1024*1024)) . 'MB.');
    } else {
        safeJsonResponse(false, 'Foto KTP gagal di-upload (kode error: ' . intval($_FILES['foto_ktp']['error']) . ').');
    }
}

if (!isset($_FILES['foto_selfie'])) {
    safeJsonResponse(false, 'Foto selfie tidak ditemukan atau gagal upload. Pastikan ukuran/format dan konfigurasi server (upload_max_filesize/post_max_size).');
}
if ($_FILES['foto_selfie']['error'] !== UPLOAD_ERR_OK) {
    if (in_array($_FILES['foto_selfie']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
        safeJsonResponse(false, 'Ukuran foto selfie terlalu besar menurut konfigurasi server (upload_max_filesize/post_max_size). Pastikan >= ' . intval(KYC_MAX_FILE_SIZE / (1024*1024)) . 'MB.');
    } else {
        safeJsonResponse(false, 'Foto selfie gagal di-upload (kode error: ' . intval($_FILES['foto_selfie']['error']) . ').');
    }
}

// Validate file size using central limit (KYC_MAX_FILE_SIZE)
if ($_FILES['foto_ktp']['size'] > KYC_MAX_FILE_SIZE) {
    safeJsonResponse(false, 'Ukuran foto KTP terlalu besar (max ' . intval(KYC_MAX_FILE_SIZE / (1024*1024)) . 'MB).');
}

if ($_FILES['foto_selfie']['size'] > KYC_MAX_FILE_SIZE) {
    safeJsonResponse(false, 'Ukuran foto selfie terlalu besar (max ' . intval(KYC_MAX_FILE_SIZE / (1024*1024)) . 'MB).');
}

// Validasi tipe MIME - read allowed list from central config (keeps backwards compat)
$allowedMimes = isset($KYC_ALLOWED_MIMES) ? $KYC_ALLOWED_MIMES : ['image/jpeg', 'image/png'];
$ktpMime = @mime_content_type($_FILES['foto_ktp']['tmp_name']) ?: '';
$selfieMime = @mime_content_type($_FILES['foto_selfie']['tmp_name']) ?: '';

// Friendly allowed types text for messages
$allowedText = 'JPG atau PNG';
if (in_array('image/heic', $allowedMimes)) $allowedText .= ' (atau HEIC)';

if (!in_array($ktpMime, $allowedMimes)) {
    safeJsonResponse(false, 'Foto KTP harus berformat ' . $allowedText . '.');
}

if (!in_array($selfieMime, $allowedMimes)) {
    safeJsonResponse(false, 'Foto selfie harus berformat ' . $allowedText . '.');
} 

// ============================================================================
// 5. BUAT FOLDER PENYIMPANAN JIKA BELUM ADA (external KYC storage)
// ============================================================================
// Ensure top-level KYC storage exists
if (!defined('KYC_STORAGE_BASE')) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] KYC storage not configured.\n", FILE_APPEND);
    safeJsonResponse(false, 'Server tidak dikonfigurasi untuk menyimpan file verifikasi.');
}

$kb = basename(KYC_STORAGE_BASE);
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] KYC storage OK: base=" . $kb . "\n", FILE_APPEND); 

// Ensure per-user directories exist (ktp/selfie)
$userKtpDir = kyc_user_dir($id_pengguna, 'ktp');
$userSelfieDir = kyc_user_dir($id_pengguna, 'selfie');
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Ensuring user kyc dirs: ktp=" . $userKtpDir . " selfie=" . $userSelfieDir . "\n", FILE_APPEND);
if (!kyc_ensure_user_dir($id_pengguna, 'ktp')) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Failed to create user KTP dir: " . $userKtpDir . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Gagal membuat folder penyimpanan KTP untuk pengguna.');
}
if (!kyc_ensure_user_dir($id_pengguna, 'selfie')) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Failed to create user SELFIE dir: " . $userSelfieDir . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Gagal membuat folder penyimpanan selfie untuk pengguna.');
}

// Best-effort: migrate any legacy files from root into this user's subfolders
$migrated = kyc_migrate_root_files_to_user($id_pengguna);
if (!empty($migrated)) @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Migrated legacy files into user dir: " . json_encode($migrated) . "\n", FILE_APPEND);

// ============================================================================
// 6. PROSES UPLOAD FOTO KTP -> store into external KYC KTP folder (per-user)
// ============================================================================
$ktpExt = kyc_mime_to_ext($ktpMime);
$ktpFileName = 'ktp.' . $ktpExt;
$ktpFullPath = $userKtpDir . $ktpFileName;

@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Saving KTP to user folder: {$ktpFullPath}\n", FILE_APPEND);
if (!move_uploaded_file($_FILES['foto_ktp']['tmp_name'], $ktpFullPath)) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] move_uploaded_file KTP failed: " . var_export(error_get_last(), true) . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Gagal menyimpan foto KTP.');
} else {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] KTP saved: {$ktpFileName} -> {$ktpFullPath}\n", FILE_APPEND);
} 

// ============================================================================
// 7. PROSES UPLOAD FOTO SELFIE -> external KYC SELFIE folder (per-user)
// ============================================================================
$selfieExt = kyc_mime_to_ext($selfieMime);
$selfieFileName = 'selfie.' . $selfieExt;
$selfieFullPath = $userSelfieDir . $selfieFileName;

@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Saving SELFIE to user folder: {$selfieFullPath}\n", FILE_APPEND);
if (!move_uploaded_file($_FILES['foto_selfie']['tmp_name'], $selfieFullPath)) {
    // Hapus foto KTP yang sudah tersimpan jika selfie gagal
    @unlink($ktpFullPath);
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] move_uploaded_file SELFIE failed: " . var_export(error_get_last(), true) . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Gagal menyimpan foto selfie.');
} else {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] SELFIE saved: {$selfieFileName} -> {$selfieFullPath}\n", FILE_APPEND);
} 

// ============================================================================
// 8. INSERT KE DATABASE VERIFIKASI_PENGGUNA
// ============================================================================
$query = $koneksi->prepare("
    INSERT INTO verifikasi_pengguna (id_pengguna, foto_ktp, foto_selfie, created_at, updated_at)
    VALUES (?, ?, ?, NOW(), NOW())
");

if (!$query) {
    // Hapus file yang sudah tersimpan
    @unlink($ktpFullPath);
    @unlink($selfieFullPath);
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Prepare failed: " . $koneksi->error . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Prepare statement gagal: ' . $koneksi->error);
} 

// Store full absolute paths in DB, but return only filenames to the mobile client
$foto_ktp_db = $ktpFullPath;
$foto_selfie_db = $selfieFullPath;
$query->bind_param("iss", $id_pengguna, $foto_ktp_db, $foto_selfie_db);

$ok = false;
try {
    $ok = $query->execute();
} catch (mysqli_sql_exception $e) {
    // Hapus file yang sudah tersimpan
    @unlink($ktpFullPath);
    @unlink($selfieFullPath);
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Exception on execute: " . $e->getMessage() . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Gagal menyimpan ke database: ' . $e->getMessage());
}

if ($ok) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] DB insert OK for id_pengguna={$id_pengguna}, ktp={$ktpFileName}, selfie={$selfieFileName}\n", FILE_APPEND);
    // Update pengguna.status_akun -> 'submitted' (user uploaded KTP & selfie)
    try {
        $upd = $koneksi->prepare("UPDATE pengguna SET status_akun = 'submitted', updated_at = NOW() WHERE id = ? LIMIT 1");
        if ($upd) {
            $upd->bind_param('i', $id_pengguna);
            $upd->execute();
            $upd->close();
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Updated pengguna.status_akun=submitted for id={$id_pengguna}\n", FILE_APPEND);
        }
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] Failed to update pengguna status: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    // Return filenames (not absolute paths) for mobile client while we store absolute paths in DB
    safeJsonResponse(true, 'Verifikasi identitas berhasil disimpan.', array('data' => array(
        'id_pengguna' => $id_pengguna,
        'foto_ktp' => basename($ktpFullPath),
        'foto_selfie' => basename($selfieFullPath),
        'created_at' => date('Y-m-d H:i:s')
    )));
} else {
    // Hapus file yang sudah tersimpan
    @unlink($ktpFullPath);
    @unlink($selfieFullPath);
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register_tahap2] DB insert failed: " . $query->error . "\n", FILE_APPEND);
    safeJsonResponse(false, 'Gagal menyimpan ke database: ' . $query->error);
}

$query->close();
$koneksi->close();
