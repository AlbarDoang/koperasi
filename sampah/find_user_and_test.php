<?php
include 'gas_web/flutter_api/connection.php';

// Find user ID from phone number shown in screenshot
$phone = '081990608817';

// Query user
$sql_user = "SELECT * FROM user_utama WHERE nomor_hp = '" . $connect->real_escape_string($phone) . "' LIMIT 1";
$result_user = $connect->query($sql_user);

if ($result_user && $result_user->num_rows > 0) {
  $user = $result_user->fetch_assoc();
  $user_id = $user['id'];
  echo "Found User ID: " . $user_id . "\n";
  echo "Phone: " . $user['nomor_hp'] . "\n";
  echo "Name: " . $user['nama_pengguna'] . "\n\n";
  
  // Query transactions for this user
  $sql = "SELECT id_transaksi, jenis_transaksi, status, jumlah, keterangan, tanggal FROM transaksi WHERE id_anggota = " . intval($user_id) . " ORDER BY tanggal DESC LIMIT 10";
  $result = $connect->query($sql);
  
  echo "=== Transactions for this user ===\n";
  
  if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      echo "ID: {$row['id_transaksi']}, Jenis: {$row['jenis_transaksi']}, Status: {$row['status']}, Jumlah: {$row['jumlah']}, Date: {$row['tanggal']}\n";
    }
  } else {
    echo "No transactions found\n";
  }
  
  // Test API
  echo "\n=== Testing API Response ===\n";
  $_POST['id_pengguna'] = $user_id;
  $_SERVER['REQUEST_METHOD'] = 'POST';
  ob_start();
  include 'gas_web/flutter_api/get_riwayat_transaksi.php';
  $output = ob_get_clean();
  echo "API Response:\n" . $output . "\n";
  
} else {
  echo "User not found with phone: " . $phone . "\n";
  
  // List all users
  echo "\nAll users in database:\n";
  $sql_all = "SELECT id, nomor_hp, nama_pengguna FROM user_utama LIMIT 10";
  $result_all = $connect->query($sql_all);
  while ($row = $result_all->fetch_assoc()) {
    echo "ID: {$row['id']}, Phone: {$row['nomor_hp']}, Name: {$row['nama_pengguna']}\n";
  }
}
?>
