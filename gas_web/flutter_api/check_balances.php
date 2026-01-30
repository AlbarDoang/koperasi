<?php
require_once __DIR__ . '/connection.php';
header('Content-Type: application/json');
$ids = $_GET['ids'] ?? '95,97';
$ids_clean = implode(',', array_map('intval', explode(',', $ids)));
$res = $connect->query("SELECT id, no_hp, nama_lengkap, saldo FROM pengguna WHERE id IN ($ids_clean)");
$out = [];
if ($res) while ($r = $res->fetch_assoc()) $out[] = $r;
echo json_encode(['success' => true, 'users' => $out], JSON_PRETTY_PRINT);

