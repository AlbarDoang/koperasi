<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$r = $connect->query('SHOW COLUMNS FROM jenis_tabungan');
while($c = $r->fetch_assoc()) echo json_encode($c) . "\n";
?>