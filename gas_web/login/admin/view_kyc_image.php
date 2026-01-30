<?php
// Secure admin-only proxy to serve KTP/selfie images stored outside webroot
// Usage: view_kyc_image.php?id_user=123&type=ktp

// Try to include AdminMiddleware from a few possible locations (use realpath candidates)
$adminMiddlewareCandidates = [
    realpath(__DIR__ . '/../middleware/AdminMiddleware.php'),
    realpath(__DIR__ . '/../../middleware/AdminMiddleware.php'),
    realpath(dirname(__DIR__) . '/middleware/AdminMiddleware.php'),
    realpath(dirname(__DIR__, 2) . '/login/middleware/AdminMiddleware.php'),
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
if (!(class_exists('AdminMiddleware') && method_exists('AdminMiddleware', 'handle'))) {
    http_response_code(500);
    echo 'Server configuration error: invalid AdminMiddleware.';
    exit();
}

// Ensure admin session
AdminMiddleware::handle();

// Basic validation
if (!isset($_GET['id_user']) || !is_numeric($_GET['id_user'])) {
    // Invalid identifier - log and return placeholder image (do not leak details to client)
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] invalid or missing id_user param: " . var_export($_GET['id_user'] ?? null, true) . "\n", FILE_APPEND);
    serve_placeholder();
}
$id_user = intval($_GET['id_user']);
$type = (isset($_GET['type']) && $_GET['type'] === 'selfie') ? 'selfie' : 'ktp';

// Helper to serve an inline SVG placeholder (no filesystem path disclosed)
function serve_placeholder(){
    $svg = '<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">\n  <rect width="100%" height="100%" fill="#f2f4f7"/>\n  <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#9aa3b2" font-family="Arial, Helvetica, sans-serif" font-size="20">No image available</text>\n</svg>';
    header('Content-Type: image/svg+xml');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $svg;
    exit();
}

// DB connection and storage config: use realpath candidates and fail loudly if not found
$configCandidates = [
    realpath(__DIR__ . '/../koneksi/config.php'),
    realpath(__DIR__ . '/../../koneksi/config.php'),
    realpath(__DIR__ . '/../../../koneksi/config.php'),
];
$cfgFound = false;
foreach ($configCandidates as $c) { if ($c && file_exists($c)) { require_once $c; $cfgFound = true; break; } }
if (!$cfgFound) { http_response_code(500); echo 'Server configuration error: config.php not found. Checked: ' . json_encode($configCandidates); exit(); }

$storageCandidates = [ realpath(__DIR__ . '/../../flutter_api/storage_config.php'), realpath(dirname(__DIR__, 2) . '/flutter_api/storage_config.php') ];
$storageFound = false; foreach ($storageCandidates as $s) { if ($s && file_exists($s)) { require_once $s; $storageFound = true; break; } }
if (!$storageFound) { http_response_code(500); echo 'Server configuration error: storage_config.php not found.'; exit(); }

// Ensure DB connection variable
if (isset($con) && $con instanceof mysqli) { }
elseif (isset($koneksi) && $koneksi instanceof mysqli) { $con = $koneksi; }
elseif (isset($connect) && $connect instanceof mysqli) { $con = $connect; }
else { http_response_code(500); echo 'Server error: database connection not initialized by config.php'; exit(); }

$con->set_charset('utf8mb4');

// Determine column name and fetch latest verification record for this user
$stmt = $con->prepare("SELECT foto_ktp, foto_selfie FROM verifikasi_pengguna WHERE id_pengguna = ? ORDER BY id DESC LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] DB prepare failed for user " . $id_user . "\n", FILE_APPEND);
    echo 'DB prepare failed';
    exit();
}
$stmt->bind_param('i', $id_user);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] No verification record for user " . $id_user . "\n", FILE_APPEND);
    echo 'Verification record not found';
    exit();
}
$row = $res->fetch_assoc();
$filename = $type === 'selfie' ? ($row['foto_selfie'] ?? '') : ($row['foto_ktp'] ?? '');
if (empty($filename)) {
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] filename empty for user " . $id_user . " type=" . $type . "\n", FILE_APPEND);
    serve_placeholder();
}

// Build candidate paths. Database may store plain filename or legacy paths; prefer using configured storage.
$maybe = [];
// If filename looks like plain name (no slashes), use configured KYC storage
if (strpos($filename, '/') === false && strpos($filename, '\\') === false) {
    if ($type === 'selfie') {
        $maybe[] = KYC_STORAGE_SELFIE . basename($filename);
        $maybe[] = KYC_STORAGE_SELFIE . $id_user . DIRECTORY_SEPARATOR . basename($filename);
    } else {
        $maybe[] = KYC_STORAGE_KTP . basename($filename);
        $maybe[] = KYC_STORAGE_KTP . $id_user . DIRECTORY_SEPARATOR . basename($filename);
    }
    // legacy public folder inside project
    $maybe[] = dirname(__DIR__, 2) . '/flutter_api/foto_verifikasi/' . basename($filename);
} else {
    // filename may already be a path (legacy) - use it but validate
    $maybe[] = $filename;
}

@file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] candidates=" . json_encode($maybe) . "\n", FILE_APPEND);

$realFile = null;
foreach ($maybe as $p) {
    $rp = realpath($p);
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] candidate=" . var_export($p, true) . " realpath=" . var_export($rp, true) . "\n", FILE_APPEND);
    if ($rp && file_exists($rp) && is_readable($rp)) {
        // Ensure file is inside KYC base if using KYC storage
        if (function_exists('kyc_path_is_inside_base') && !kyc_path_is_inside_base($rp)) {
            // allow legacy public folder (contains "foto_verifikasi")
            if (stripos($rp, 'foto_verifikasi') === false) continue;
        }
        $realFile = $rp;
        break;
    }
}

if (!$realFile) {
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] file not found for user " . $id_user . " candidate: " . var_export($filename, true) . "\n", FILE_APPEND);
    serve_placeholder();
}

// Serve file securely
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $realFile) : mime_content_type($realFile);
if ($finfo) finfo_close($finfo);
if (!$mime) $mime = 'application/octet-stream';

// Prevent exposing filesystem path in headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realFile));
header('Cache-Control: private, max-age=3600');
header('Pragma: private');
header('Content-Disposition: inline; filename="' . basename($realFile) . '"');

// Stream
$fp = fopen($realFile, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
    }
    fclose($fp);
    exit();
} else {
    http_response_code(500);
    @file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [view_kyc_image] Failed to open file: " . $realFile . "\n", FILE_APPEND);
    echo 'Failed to open file';
    exit();
}

?>