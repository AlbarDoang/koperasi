<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$db = getConnectionOOP();
$res = $db->query('SELECT id, nama_jenis FROM jenis_tabungan LIMIT 20');
$out = [];
while ($r = $res->fetch_assoc()) $out[] = $r;
echo json_encode($out, JSON_PRETTY_PRINT);
