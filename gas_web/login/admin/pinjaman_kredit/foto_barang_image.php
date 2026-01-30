<?php
// Secure proxy to serve pinjaman_kredit item photos stored in LOAN_STORAGE_BASE
// Usage: foto_barang_image.php?id=<pinjaman_id>

if (!headers_sent()) header_remove('X-Powered-By');
require_once dirname(__DIR__, 3) . '/flutter_api/api_bootstrap.php';
require_once dirname(__DIR__, 3) . '/flutter_api/storage_config.php';

// Validate id
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { http_response_code(400); echo 'Bad Request'; exit(); }

// Require admin session
if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin = isset($_SESSION['akses']) && strtolower($_SESSION['akses']) === 'admin';
if (!$isAdmin) { http_response_code(403); echo 'Forbidden'; exit(); }

// Lookup pinjaman record
$conn = getConnection();
if (!$conn) { http_response_code(500); echo 'Internal'; exit(); }
$stmt = $conn->prepare("SELECT id_pengguna, foto_barang FROM pinjaman_kredit WHERE id = ? LIMIT 1");
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
@file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [foto_barang_image] Serving path={$path} uid={$uid} filename={$filename}\n", FILE_APPEND);
if (!file_exists($path) || !is_file($path)) { http_response_code(404); echo 'Not Found'; exit(); }
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