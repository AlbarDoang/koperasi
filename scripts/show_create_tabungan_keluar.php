<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$db = getConnectionOOP();
$res = $db->query('SHOW CREATE TABLE tabungan_keluar');
if (!$res) { echo json_encode(['ok'=>false,'error'=>$db->error]); exit; }
$r = $res->fetch_assoc();
echo $r['Create Table'];
