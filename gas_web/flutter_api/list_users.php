<?php
require_once __DIR__ . '/connection.php';
header('Content-Type: application/json');

$res = $connect->query("SELECT id, no_hp, nama_lengkap, saldo, status_akun FROM pengguna LIMIT 20");
$out = [];
if ($res) {
    while ($r = $res->fetch_assoc()) $out[] = $r;
}

echo json_encode(['success' => true, 'count' => count($out), 'users' => $out], JSON_PRETTY_PRINT);

