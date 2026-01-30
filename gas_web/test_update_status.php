<?php
// Test file to debug update_status_mulai_nabung.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'id_mulai_nabung' => '149'
];

echo "=== TESTING update_status_mulai_nabung.php ===\n";
ob_start();
include __DIR__ . '/flutter_api/update_status_mulai_nabung.php';
$response = ob_get_clean();
echo "Response: " . $response . "\n";
echo "Length: " . strlen($response) . "\n";
if (strlen($response) > 0) {
    echo "First 100 chars: " . substr($response, 0, 100) . "\n";
}
?>
