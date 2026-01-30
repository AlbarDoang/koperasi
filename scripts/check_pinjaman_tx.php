<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$q = 'SELECT id_transaksi, jenis_transaksi, jumlah, keterangan, tanggal FROM transaksi WHERE (jenis_transaksi LIKE "%pinjaman%" OR keterangan LIKE "%pinjaman%") ORDER BY tanggal DESC LIMIT 20';
$r = $connect->query($q);
if ($r) {
    while ($row = $r->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo 'Query failed: ' . $connect->error . "\n";
    echo 'SQL: ' . $q . "\n";
}
?>