<?php
/**
 * Proxy endpoint to serve user profile photos securely.
 * - URL: login/user/foto_profil_image.php?id=<user_id>&sig=<signature>&exp=<unix_ts>
 * - Authorization: either a valid PHP session (same user OR admin) OR a valid signed URL
 * - Files are stored in PROFILE_STORAGE_PHOTO (outside docroot) or legacy uploads folder
 * - Does not expose filesystem paths; streams image with correct Content-Type
 */

// Basic headers
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

// Bootstrap (includes DB connection and storage config)
require_once dirname(__DIR__, 2) . '/flutter_api/api_bootstrap.php';
require_once dirname(__DIR__, 2) . '/flutter_api/helpers.php';

// Helper: constant
$requestedId = isset($_GET['id']) ? trim($_GET['id']) : (isset($_GET['id_pengguna']) ? trim($_GET['id_pengguna']) : '');
if (empty($requestedId) || !preg_match('/^[0-9]+$/', $requestedId)) {
    http_response_code(400);
    echo 'Bad Request';
    exit();
}

// Try to read Authorization headers if present (support Bearer token if project uses it)
$headers = [];
if (function_exists('getallheaders')) $headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
$bearerToken = null;
if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $m)) {
    $bearerToken = trim($m[1]);
}

// Session check (admin or same user)
$isAuthorized = false;
if (session_status() === PHP_SESSION_NONE) session_start();
$isAdmin = isset($_SESSION['akses']) && strtolower($_SESSION['akses']) === 'admin';
$sessionUserId = $_SESSION['id'] ?? ($_SESSION['user_id'] ?? null);
if ($isAdmin || ($sessionUserId !== null && (string)$sessionUserId === (string)$requestedId)) {
    $isAuthorized = true;
}

// If not authorized via session, check signed URL
if (!$isAuthorized) {
    $sig = isset($_GET['sig']) ? $_GET['sig'] : '';
    $exp = isset($_GET['exp']) ? intval($_GET['exp']) : 0;
    if ($sig && $exp && time() <= $exp) {
        // We need filename to verify signature; fetch filename from DB
        $conn = getConnection();
        if (!$conn) { http_response_code(500); echo 'Internal'; exit(); }
        $stmt = $conn->prepare("SELECT foto_profil FROM pengguna WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $requestedId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $filename = $row['foto_profil'] ?? null;
        $stmt->close();
        if (!$filename) { http_response_code(404); echo 'Not Found'; exit(); }
        // Compute expected sig
        $payload = $requestedId . ':' . $filename . ':' . $exp;
        $expected = hash_hmac('sha256', $payload, PROFILE_IMAGE_SECRET);
        if (hash_equals($expected, $sig)) {
            $isAuthorized = true;
        }
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

// At this point, we are authorized to serve the file. Look up filename.
$conn = getConnection();
if (!$conn) { http_response_code(500); echo 'Internal'; exit(); }
$stmt = $conn->prepare("SELECT foto_profil FROM pengguna WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $requestedId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();
$filename = $row['foto_profil'] ?? null;
if (empty($filename)) {
    http_response_code(404);
    echo 'Not Found';
    exit();
}

// Candidate paths - prefer per-user folder: PROFILE_STORAGE_PHOTO/<user_id>/<filename>
$profilePath = PROFILE_STORAGE_PHOTO . $requestedId . DIRECTORY_SEPARATOR . $filename;
$profilePathLegacyFlat = PROFILE_STORAGE_PHOTO . $filename; // older flat storage
$legacyPath = dirname(__DIR__) . '/uploads/foto_profil/' . $filename; // old location (inside project)
$filePath = null;
$fromLegacy = false;

if (file_exists($profilePath) && is_file($profilePath)) {
    $filePath = $profilePath;
} elseif (file_exists($profilePathLegacyFlat) && is_file($profilePathLegacyFlat)) {
    // Found in older flat profile storage
    $filePath = $profilePathLegacyFlat;
} elseif (file_exists($legacyPath) && is_file($legacyPath)) {
    // Serve from legacy but attempt an asynchronous migrate (best-effort)
    $filePath = $legacyPath;
    $fromLegacy = true;
} else {
    http_response_code(404);
    echo 'Not Found';
    exit();
}

// If serving from legacy, attempt to copy into profile storage and update DB (best-effort)
if ($fromLegacy) {
    // Ensure profile dir exists
    @profile_ensure_dir(PROFILE_STORAGE_BASE);
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? @finfo_file($finfo, $legacyPath) : mime_content_type($legacyPath);
    if (!$mime) $mime = 'image/jpeg';
    $newName = profile_generate_filename($requestedId, $mime);
    $dest = PROFILE_STORAGE_PHOTO . $newName;
    if (@copy($legacyPath, $dest)) {
        @chmod($dest, 0644);
        // Update DB to point to new file (best-effort)
        $safeNew = $conn->real_escape_string($newName);
        $safeId = intval($requestedId);
        $conn->query("UPDATE pengguna SET foto_profil = '{$safeNew}' WHERE id = {$safeId} LIMIT 1");
        // Update $filePath to new copy
        $filePath = $dest;
    }
}

// Ensure file is inside profile storage OR legacy (we already handled legacy). If it's in profile storage, validate path safety
if (!profile_path_is_inside_base($filePath) && !$fromLegacy) {
    http_response_code(403);
    echo 'Forbidden';
    exit();
}

// Determine Content-Type
$mimeType = 'application/octet-stream';
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
if ($finfo) {
    $mimeType = @finfo_file($finfo, $filePath) ?: $mimeType;
    @finfo_close($finfo);
} else {
    $mimeType = mime_content_type($filePath) ?: $mimeType;
}

// Only allow image types
if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
    http_response_code(415);
    echo 'Unsupported Media Type';
    exit();
}

// Serve file with appropriate headers: NO CACHE (we will control freshness via updated_at + cache buster)
header_remove();
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
// Stream file
$fh = fopen($filePath, 'rb');
if ($fh) {
    while (!feof($fh)) {
        echo fread($fh, 8192);
        flush();
    }
    fclose($fh);
}
exit();
?>