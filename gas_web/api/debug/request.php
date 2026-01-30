<?php
// api/debug/request.php
// Local dev debug endpoint — returns headers + raw body + JSON decode info
// WARNING: For local development only. Do NOT enable in production.

declare(strict_types=1);

// Always return JSON and prevent any accidental output
header('Content-Type: application/json; charset=utf-8');
// Helpful CORS for local testing
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight quickly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Helper to get headers cross-platform
function safe_get_all_headers(): array {
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        // Normalize keys to original case where possible
        return is_array($h) ? $h : [];
    }
    if (function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        return is_array($h) ? $h : [];
    }
    // Fallback to $_SERVER parsing
    $headers = [];
    foreach ($_SERVER as $name => $value) {
        if (strpos($name, 'HTTP_') === 0) {
            $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[$header] = $value;
        }
    }
    return $headers;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$headers = safe_get_all_headers();

// Find Authorization header in a case-insensitive manner
$authHeader = null;
foreach ($headers as $k => $v) {
    if (strtolower($k) === 'authorization') {
        $authHeader = $v;
        break;
    }
}
// Also check server-variables fallback
if ($authHeader === null) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
}

// Parse Bearer token
$bearer = null;
if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $bearer = trim($m[1]);
}

// Read raw body and remove BOM
$raw = file_get_contents('php://input');
if ($raw !== null) {
    $raw = trim((string)$raw);
    $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
}

$json = null;
$jsonError = 'No error';
if ($raw !== '' && $raw !== null) {
    $decoded = json_decode($raw, true);
    $jsonError = json_last_error() === JSON_ERROR_NONE ? 'No error' : json_last_error_msg();
    $json = is_array($decoded) ? $decoded : $decoded;
}

$response = [
    'method' => $method,
    'all_headers' => $headers,
    'authorization_header' => $authHeader,
    'bearer_token' => $bearer,
    'raw_body' => $raw,
    'json' => $json,
    'json_error' => $jsonError,
];

http_response_code(200);
echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
