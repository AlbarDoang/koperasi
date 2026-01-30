<?php
require 'gas_web/flutter_api/connection.php';

$user_id = 4; // jtbttn user

echo "=== DATABASE TRANSAKSI TABLE ===\n\n";

// Check all columns for this user
$sql = "SELECT * FROM transaksi WHERE id_anggota = $user_id ORDER BY id_transaksi DESC LIMIT 10";
$result = $connect->query($sql);
if (!$result) {
    echo "Query error: " . $connect->error . "\n";
    exit;
}

while ($row = $result->fetch_assoc()) {
    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
}

echo "\n=== API RESPONSE FROM get_riwayat_transaksi.php ===\n\n";

// Simulate API call
ob_start();
$_GET['id_pengguna'] = $user_id;
include 'gas_web/flutter_api/get_riwayat_transaksi.php';
$output = ob_get_clean();
echo $output;
?>
