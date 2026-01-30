<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$db = getConnectionOOP();
$res = $db->query('SHOW COLUMNS FROM pengguna');
$cols = [];
while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
echo json_encode($cols, JSON_PRETTY_PRINT);
