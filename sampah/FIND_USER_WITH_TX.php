<?php
require 'gas_web/flutter_api/connection.php';

echo "=== USERS WITH TRANSACTIONS ===\n\n";

// Find users with the most recent transactions
$sql = "SELECT DISTINCT id_anggota, COUNT(*) as tx_count FROM transaksi GROUP BY id_anggota ORDER BY id_anggota DESC LIMIT 20";
$result = $connect->query($sql);

while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id_anggota'] . " - Count: " . $row['tx_count'] . "\n";
}

echo "\n=== RECENT TRANSACTIONS ===\n\n";

// Get most recent transactions
$sql2 = "SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi ORDER BY tanggal DESC LIMIT 20";
$result2 = $connect->query($sql2);

while ($row = $result2->fetch_assoc()) {
    echo "ID: " . $row['id_transaksi'];
    echo " | User: " . $row['id_anggota'];
    echo " | Status: " . $row['status'];
    echo " | Keterangan: " . substr($row['keterangan'], 0, 50);
    echo " | Tanggal: " . $row['tanggal'];
    echo "\n";
}

?>
