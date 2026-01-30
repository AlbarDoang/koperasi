<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$db = getConnectionOOP();
if (!$db) { echo json_encode(['ok'=>false,'error'=>'db_conn']); exit; }
$res = $db->query("SHOW COLUMNS FROM tabungan_keluar");
$cols = [];
while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
echo json_encode(['ok'=>true,'columns'=>$cols], JSON_PRETTY_PRINT);
