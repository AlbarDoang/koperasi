<?php
// Admin image proxy for verifikasi_pengguna KTP/selfie images
// Usage: verification_image.php?user_id=123&type=ktp
// NOTE: This endpoint expects `user_id` (id_pengguna) and no longer relies on any `id`/verification id column in the verifikasi_pengguna table.

// Load storage_config.php early (needed for token verification)
$storageCandidates = [
    realpath(__DIR__ . '/../../flutter_api/storage_config.php'),
    realpath(dirname(__DIR__, 2) . '/flutter_api/storage_config.php'),
    realpath(dirname(__DIR__, 3) . '/flutter_api/storage_config.php'),
];
$storageFoundEarly = false;
foreach ($storageCandidates as $s) {
    if ($s && file_exists($s)) { require_once $s; $storageFoundEarly = true; break; }
}
// Validate input user_id early so token can be validated without starting session
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo 'Parameter user_id tidak valid atau tidak disediakan';
    exit();
}
$userId = intval($_GET['user_id']);
// Determine requested type
$type = (isset($_GET['type']) && $_GET['type'] === 'selfie') ? 'selfie' : 'ktp';

// Token-based access: allow short-lived signed tokens so images can be embedded without session cookies
$bypassAuth = false;
if (isset($_GET['token']) && $storageFoundEarly && defined('PROFILE_IMAGE_SECRET')) {
    $tokenRaw = base64_decode($_GET['token']);
    if ($tokenRaw !== false) {
        $parts = explode(':', $tokenRaw);
        if (count($parts) === 4) {
            list($tid, $ttype, $texp, $thmac) = $parts;
            if (is_numeric($tid)) {
                $tid = intval($tid);
                $expected = hash_hmac('sha256', $tid . '|' . $ttype . '|' . $texp, PROFILE_IMAGE_SECRET);
                if (hash_equals($expected, $thmac) && $tid === $userId && $ttype === $type && intval($texp) >= time()) {
                    $bypassAuth = true;
                    @file_put_contents(dirname(__DIR__, 2) . '/flutter_api/api_debug.log', date('c') . " [verification_image] token accepted for user_id={$userId} type={$type}\n", FILE_APPEND);
                } else {
                    @file_put_contents(dirname(__DIR__, 2) . '/flutter_api/api_debug.log', date('c') . " [verification_image] invalid token for user_id={$userId} type={$type}\n", FILE_APPEND);
                }
            }
        }
    }
}

// If token didn't bypass, require admin session via AdminMiddleware
if (!$bypassAuth) {
    // Try to include AdminMiddleware from a few possible locations (use realpath-based candidates)
    $adminMiddlewareCandidates = [
        realpath(__DIR__ . '/../middleware/AdminMiddleware.php'),
        realpath(__DIR__ . '/../../middleware/AdminMiddleware.php'),
        realpath(dirname(__DIR__) . '/middleware/AdminMiddleware.php'),
        realpath(dirname(__DIR__, 2) . '/login/middleware/AdminMiddleware.php'),
        realpath(__DIR__ . '/../../login/middleware/AdminMiddleware.php'),
    ];
    $included = false;
    foreach ($adminMiddlewareCandidates as $candidate) {
        if ($candidate && file_exists($candidate)) {
            require_once $candidate;
            $included = true;
            break;
        }
    }
    if (!$included) {
        http_response_code(500);
        echo 'Server configuration error: Admin middleware not found (checked several locations).';
        exit();
    }
    // Check and run handler if available
    if (class_exists('AdminMiddleware') && method_exists('AdminMiddleware', 'handle')) {
        AdminMiddleware::handle(); // ensure admin session
    } else {
        http_response_code(500);
        echo 'Server configuration error: invalid AdminMiddleware implementation.';
        exit();
    }
}

// This proxy accepts a numeric user_id (id_pengguna) and will fetch the latest verification record for that user.
// Legacy: it still supports stored filename values (stored in foto_ktp/foto_selfie) which may be plain filenames or legacy paths.
$type = (isset($_GET['type']) && $_GET['type'] === 'selfie') ? 'selfie' : 'ktp';

// DB connection (reuse common connection if available)
// Use realpath-based include candidates to avoid relative ../../ issues and fail loudly when missing.
$configCandidates = [
    realpath(__DIR__ . '/../koneksi/config.php'),    // login/koneksi/config.php (likely)
    realpath(__DIR__ . '/../../koneksi/config.php'),  // gas_web/koneksi/config.php (alternative)
    realpath(__DIR__ . '/../../../koneksi/config.php'),
    realpath(dirname(__DIR__, 3) . '/koneksi/config.php'),
];
$cfgFound = false;
foreach ($configCandidates as $c) {
    if ($c && file_exists($c)) {
        require_once $c;
        $cfgFound = true;
        break;
    }
}
if (!$cfgFound) {
    http_response_code(500);
    echo 'Server configuration error: config.php not found. Checked: ' . json_encode($configCandidates);
    exit();
}

// storage_config.php (KYC storage)
$storageCandidates = [
    realpath(__DIR__ . '/../../flutter_api/storage_config.php'),
    realpath(dirname(__DIR__, 2) . '/flutter_api/storage_config.php'),
    realpath(dirname(__DIR__, 3) . '/flutter_api/storage_config.php'),
];
$storageFound = false;
foreach ($storageCandidates as $s) {
    if ($s && file_exists($s)) { require_once $s; $storageFound = true; break; }
}
if (!$storageFound) {
    http_response_code(500);
    echo 'Server configuration error: storage_config.php not found. Checked: ' . json_encode($storageCandidates);
    exit();
}

// Ensure database connection variable exists (depending on project it may be $con or $koneksi)
if (isset($con) && $con instanceof mysqli) {
    // ok
} elseif (isset($koneksi) && $koneksi instanceof mysqli) {
    $con = $koneksi;
} elseif (isset($connect) && $connect instanceof mysqli) {
    $con = $connect;
} else {
    http_response_code(500);
    echo 'Server error: database connection not initialized by config.php';
    exit();
}
$con->set_charset('utf8mb4');

// Use id_pengguna from GET parameter (user_id). Reject other modes to avoid relying on 'id' column
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo 'Parameter user_id tidak valid atau tidak disediakan';
    exit();
}
$userId = intval($_GET['user_id']);

@file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] request user_id=" . var_export($userId, true) . " type=" . $type . "\n", FILE_APPEND);

// Query using id_pengguna (do NOT select column 'id')
$stmt = $con->prepare("SELECT foto_ktp, foto_selfie FROM verifikasi_pengguna WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] DB prepare failed for user_id=" . $userId . "\n", FILE_APPEND);
    echo 'Server error: DB prepare failed';
    exit();
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    echo 'DATA VERIFIKASI TIDAK DITEMUKAN';
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] no verification record for user_id=" . $userId . "\n", FILE_APPEND);
    exit();
}
$row = $res->fetch_assoc();
$path = $type === 'selfie' ? ($row['foto_selfie'] ?? '') : ($row['foto_ktp'] ?? '');
if (empty($path)) {
    http_response_code(404);
    echo 'DATA VERIFIKASI TIDAK DITEMUKAN';
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] filename empty for user_id=" . $userId . "\n", FILE_APPEND);
    exit();
}

// Build candidate real paths to check; do not expose them to clients
$maybe_paths = [];
if (strpos($path, '/') === false && strpos($path, '\\') === false && preg_match('/^[a-zA-Z0-9_\-\.]+$/', basename($path))) {
    // legacy filename - check old public folder and also new storage folders
    $maybe_paths[] = __DIR__ . '/../../flutter_api/foto_verifikasi/' . basename($path);
    $maybe_paths[] = dirname(__DIR__, 3) . '/gas_web/flutter_api/foto_verifikasi/' . basename($path);
    if (defined('KYC_STORAGE_KTP')) {
        $maybe_paths[] = KYC_STORAGE_KTP . basename($path);
        // support per-user subfolder (admin provides user_id)
        $maybe_paths[] = KYC_STORAGE_KTP . $userId . DIRECTORY_SEPARATOR . basename($path);
    }
    if (defined('KYC_STORAGE_SELFIE')) {
        $maybe_paths[] = KYC_STORAGE_SELFIE . basename($path);
        $maybe_paths[] = KYC_STORAGE_SELFIE . $userId . DIRECTORY_SEPARATOR . basename($path);
    }
} else {
    $maybe_paths[] = $path;
} 

// Log candidate paths and their realpath results for debugging
@file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] candidates: " . json_encode($maybe_paths) . "\n", FILE_APPEND);

$realFile = null;
foreach ($maybe_paths as $p) {
    $rp = realpath($p);
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] candidate=" . var_export($p, true) . " realpath=" . var_export($rp, true) . "\n", FILE_APPEND);
    if ($rp && file_exists($rp) && is_readable($rp)) { $realFile = $rp; break; }
}

if (!$realFile) {
    http_response_code(404);
    echo 'DATA VERIFIKASI TIDAK DITEMUKAN';
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] file not found for user_id=" . $userId . " path=" . var_export($path, true) . "\n", FILE_APPEND);
    exit();
}

// Security: ensure file is within storage base if it is inside KYC base
if (!kyc_path_is_inside_base($realFile) && stripos($realFile, 'foto_verifikasi') === false) {
    http_response_code(403);
    echo 'DATA VERIFIKASI TIDAK DITEMUKAN';
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] access denied (outside kyc base) realFile=" . var_export($realFile, true) . "\n", FILE_APPEND);
    exit();
}

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $realFile) : mime_content_type($realFile);
if ($finfo) finfo_close($finfo);
if (!$mime) $mime = 'application/octet-stream';

// Serve with appropriate headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realFile));
header('Cache-Control: private, max-age=3600');
header('Pragma: private');
header('Content-Disposition: inline; filename="' . basename($realFile) . '"');

// Stream file using readfile()
if (readfile($realFile) !== false) {
    exit();
} else {
    http_response_code(500);
    echo 'Server error: failed to read file';
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [verification_image] failed to read file=" . var_export($realFile, true) . "\n", FILE_APPEND);
    exit();
}



?>