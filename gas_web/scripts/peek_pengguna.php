<?php
include __DIR__ . '/../flutter_api/connection.php';
$r = $connect->query('SELECT id, nama_lengkap, status_akun, saldo, no_hp FROM pengguna LIMIT 10');
$out = [];
while ($row = $r->fetch_assoc()) $out[] = $row;
header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
?>