<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$patterns = ["jenis_transaksi LIKE '%pinjaman%'", "keterangan LIKE '%pinjaman%'", "keterangan LIKE '%disetujui%'"];
$sql = 'SELECT * FROM transaksi WHERE ' . implode(' OR ', $patterns) . ' ORDER BY tanggal DESC LIMIT 50';
$res = $connect->query($sql);
if (!$res) { echo 'query failed: ' . $connect->error . "\n"; exit(1); }
while ($r = $res->fetch_assoc()) print_r($r);
