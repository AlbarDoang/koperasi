<?php
// Secure proxy to serve pinjaman_kredit item photos stored in LOAN_STORAGE_BASE
// Usage: foto_barang_image.php?id=<pinjaman_id>
//
// NOTE: Do NOT include api_bootstrap.php here. It sets up output buffering
// designed for JSON API responses, which intercepts binary image data and
// replaces it with a JSON error. We only need the DB connection and storage config.

if (!headers_sent()) header_remove('X-Powered-By');

// Disable any existing output buffers to ensure binary image data is sent cleanly
while (ob_get_level()) ob_end_clean();

// Include only what we need: database config + storage config (no output buffering)
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/flutter_api/storage_config.php';

// Validate id
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { http_response_code(400); echo 'Bad Request'; exit(); }

// Require admin or petugas session
if (session_status() === PHP_SESSION_NONE) session_start();
$akses = isset($_SESSION['akses']) ? strtolower($_SESSION['akses']) : '';
$isAllowed = in_array($akses, ['admin', 'petugas'], true);
if (!$isAllowed) { http_response_code(403); echo 'Forbidden'; exit(); }

// Lookup pinjaman record — use $con from database.php (mysqli)
if (!isset($con) || !$con) { http_response_code(500); echo 'Internal'; exit(); }
$stmt = $con->prepare("SELECT id_pengguna, foto_barang FROM pinjaman_kredit WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$row) { http_response_code(404); echo 'Not Found'; exit(); }
$filename = $row['foto_barang'] ?? '';
$uid = intval($row['id_pengguna'] ?? 0);
if (empty($filename) || strstr($filename, '/') !== false || strstr($filename, "\\") !== false) {
    // Don't serve legacy path values or external URLs; require migration to new storage
    http_response_code(404); echo 'Not Found'; exit();
}

// Use id_pengguna-based folder (do not rely on username). Keep old behavior working by storing only filename in DB.
$userFolder = LOAN_STORAGE_BASE . $uid . DIRECTORY_SEPARATOR;
$path = $userFolder . $filename;
@file_put_contents(dirname(__DIR__, 2) . '/flutter_api/api_debug.log', date('c') . " [foto_barang_image] Serving path={$path} uid={$uid} filename={$filename}\n", FILE_APPEND);
if (!file_exists($path) || !is_file($path)) { http_response_code(404); echo 'Not Found (file missing)'; exit(); }
if (!loan_path_is_inside_base($path)) { http_response_code(403); echo 'Forbidden'; exit(); }

// Determine MIME and serve
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? @finfo_file($finfo, $path) : mime_content_type($path);
if ($finfo) @finfo_close($finfo);
$mime = $mime ?: 'application/octet-stream';
if (!in_array($mime, ['image/jpeg','image/png'], true)) { http_response_code(415); echo 'Unsupported Media Type'; exit(); }

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=60');
readfile($path);
exit();
?>