<?php
header('Content-Type: text/plain');
include 'gas_web/flutter_api/connection.php';

echo "=== CHECKING RECENT TRANSACTIONS ===\n";
$sql = "SELECT id_transaksi, id_anggota, jenis_transaksi, status, tanggal, keterangan 
        FROM transaksi 
        WHERE jenis_transaksi = 'setoran' 
        ORDER BY tanggal DESC 
        LIMIT 10";

$result = $connect->query($sql);
if (!$result) {
    die("Query error: " . $connect->error);
}

echo "Total rows: " . $result->num_rows . "\n\n";
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "[$count] ID: {$row['id_transaksi']} | User: {$row['id_anggota']} | Status: '{$row['status']}' | Date: {$row['tanggal']}\n";
    echo "    Keterangan: " . substr($row['keterangan'], 0, 100) . "\n\n";
}
?>
