<?php
// Test file to debug buat_mulai_nabung.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'id_tabungan' => '1',
    'nomor_hp' => '081234567890',
    'nama_pengguna' => 'Test User',
    'jumlah' => '20000',
    'tanggal' => '2026-01-22',
    'jenis_tabungan' => 'Tabungan Reguler'
];

echo "=== TESTING buat_mulai_nabung.php ===\n";
ob_start();
include __DIR__ . '/flutter_api/buat_mulai_nabung.php';
$response = ob_get_clean();
echo "Response: " . $response . "\n";
echo "Length: " . strlen($response) . "\n";
if (strlen($response) > 0) {
    echo "First 50 chars: " . substr($response, 0, 50) . "\n";
}
?>
