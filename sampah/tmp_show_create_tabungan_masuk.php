<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$r = $connect->query('SHOW CREATE TABLE tabungan_masuk');
$row = $r->fetch_assoc();
echo $row['Create Table'] . "\n";
?>