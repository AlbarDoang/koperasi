<?php
// Temporary diagnostic endpoint for CORS & reachability testing
declare(strict_types=1);
// Disable display errors to keep JSON clean
ini_set('display_errors', '0');
error_reporting(0);

$logPath = __DIR__ . '/debug.log';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
header('Content-Type: application/json; charset=utf-8');

// Log a compact entry for inspection
@file_put_contents($logPath, date('Y-m-d H:i:s') . " PING from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " origin=" . ($origin ?: 'none') . " method=" . ($_SERVER['REQUEST_METHOD'] ?? '') . "\n", FILE_APPEND | LOCK_EX);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$headers = [];
if (function_exists('getallheaders')) $headers = getallheaders();

echo json_encode([
    'status' => true,
    'message' => 'pong',
    'origin_received' => $origin ?: null,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'headers' => $headers,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;