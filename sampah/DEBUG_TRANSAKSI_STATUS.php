<?php
require 'gas_web/flutter_api/connection.php';

// Get user ID
$user_id = 4; // jtbttn user

echo "=== DEBUG TRANSAKSI TABLE ===\n\n";

// Check transaksi table
$query = "SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi WHERE id_anggota = ? ORDER BY tanggal DESC LIMIT 10";
$stmt = $connect->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Transaksi records for user ID $user_id:\n";
while ($row = $result->fetch_assoc()) {
    echo "\nID: " . $row['id_transaksi'];
    echo "\nStatus: " . $row['status'];
    echo "\nJenis: " . $row['jenis_transaksi'];
    echo "\nJumlah: " . $row['jumlah'];
    echo "\nKeterangan: " . $row['keterangan'];
    echo "\nTanggal: " . $row['tanggal'];
    echo "\n---";
}

echo "\n\n=== DEBUG TABUNGAN_MASUK TABLE ===\n\n";

// Check tabungan_masuk table
$query2 = "SELECT id, id_anggota, jenis_tabungan, jumlah, status, keterangan, tanggal FROM tabungan_masuk WHERE id_anggota = ? ORDER BY tanggal DESC LIMIT 10";
$stmt2 = $connect->prepare($query2);
$stmt2->bind_param('i', $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

echo "Tabungan_masuk records for user ID $user_id:\n";
while ($row = $result2->fetch_assoc()) {
    echo "\nID: " . $row['id'];
    echo "\nStatus: " . $row['status'];
    echo "\nJenis: " . $row['jenis_tabungan'];
    echo "\nJumlah: " . $row['jumlah'];
    echo "\nKeterangan: " . $row['keterangan'];
    echo "\nTanggal: " . $row['tanggal'];
    echo "\n---";
}

echo "\n\nDone.\n";
?>
