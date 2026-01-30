<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$db = getConnectionOOP();
$res = $db->query("SELECT id,no_hp,nama_lengkap,status_akun,saldo FROM pengguna WHERE id = 97");
if (!$res) { echo json_encode(['ok'=>false,'error'=>$db->error]); exit; }
$r = $res->fetch_assoc();
echo json_encode($r, JSON_PRETTY_PRINT);
