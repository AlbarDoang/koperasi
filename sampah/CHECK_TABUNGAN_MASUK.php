<?php
require 'gas_web/flutter_api/connection.php';

echo "=== RECENT TABUNGAN_MASUK ENTRIES ===\n\n";

// Get most recent tabungan_masuk entries
$sql = "SELECT id, id_anggota, jenis_tabungan, jumlah, status, keterangan, tanggal FROM tabungan_masuk ORDER BY tanggal DESC LIMIT 20";
$result = $connect->query($sql);

if (!$result) {
    echo "Error: " . $connect->error . "\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'];
        echo " | User: " . $row['id_anggota'];
        echo " | Jenis: " . $row['jenis_tabungan'];
        echo " | Status: " . $row['status'];
        echo " | Jumlah: " . $row['jumlah'];
        echo " | Keterangan: " . substr($row['keterangan'], 0, 40);
        echo " | Tanggal: " . $row['tanggal'];
        echo "\n";
    }
}

?>
