<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$sql = "SELECT * FROM transaksi WHERE jenis_transaksi LIKE '%pinjaman%' ORDER BY tanggal DESC LIMIT 20";
$stmt = $connect->query($sql);
if (!$stmt) { echo 'query failed: ' . $connect->error . "\nSQL: " . $sql . "\n"; exit(1); }
while ($r = $stmt->fetch_assoc()) print_r($r);
echo "\n";
