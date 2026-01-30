<?php
// Admin-only endpoint: generate short-lived signed token for verification_image.php
// POST params: user_id (int), type (ktp|selfie)
require_once __DIR__ . '/../middleware/AdminMiddleware.php';
// Start session and ensure admin
if (!class_exists('AdminMiddleware') || !method_exists('AdminMiddleware', 'handle')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server misconfiguration']);
    exit();
}
AdminMiddleware::handle();
header('Content-Type: application/json; charset=utf-8');

$userId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? intval($_POST['user_id']) : null;
$type = (isset($_POST['type']) && $_POST['type'] === 'selfie') ? 'selfie' : 'ktp';
if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'user_id required']);
    exit();
}
// Use profile image secret as signing secret (defined in storage_config.php)
$secretCandidates = [
    realpath(__DIR__ . '/../../flutter_api/storage_config.php'),
    realpath(dirname(__DIR__, 2) . '/flutter_api/storage_config.php')
];
$secret = null;
foreach ($secretCandidates as $c) {
    if ($c && file_exists($c)) { require_once $c; if (defined('PROFILE_IMAGE_SECRET')) { $secret = PROFILE_IMAGE_SECRET; break; } }
}
if (!$secret) {
    echo json_encode(['success' => false, 'error' => 'Signing secret not configured']);
    exit();
}
$expiry = time() + 60; // 60 seconds validity
$payload = $userId . '|' . $type . '|' . $expiry;
$hmac = hash_hmac('sha256', $payload, $secret);
$token = base64_encode($userId . ':' . $type . ':' . $expiry . ':' . $hmac);
@file_put_contents(__DIR__ . '/../../flutter_api/api_debug.log', date('c') . " [kyc_token] user_id={$userId} type={$type} expiry={$expiry} token=" . substr($token,0,16) . "...\n", FILE_APPEND);
echo json_encode(['success' => true, 'token' => $token, 'expiry' => $expiry]);
exit();
