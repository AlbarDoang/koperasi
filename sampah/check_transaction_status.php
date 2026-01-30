<?php
include 'gas_web/flutter_api/connection.php';

// Get user ID that was showing in the screenshots
$user_id = 2;

// Query database
$sql = 'SELECT id_transaksi, jenis_transaksi, status, keterangan FROM transaksi WHERE id_anggota = ' . intval($user_id) . ' ORDER BY tanggal DESC LIMIT 10';
$result = $connect->query($sql);

echo "=== Transactions for User ID " . $user_id . " ===\n";

if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id_transaksi']}, Jenis: {$row['jenis_transaksi']}, Status: {$row['status']}, Keterangan: {$row['keterangan']}\n";
  }
} else {
  echo "No transactions found or query error: " . $connect->error . "\n";
}

// Also test the API directly
echo "\n=== Testing get_riwayat_transaksi.php API ===\n";

// Simulate the API call
$json_input = json_encode(['id_pengguna' => $user_id]);
$_POST['id_pengguna'] = $user_id;

// Include the API
ob_start();
include 'gas_web/flutter_api/get_riwayat_transaksi.php';
$output = ob_get_clean();

echo "API Response:\n" . $output . "\n";
?>
