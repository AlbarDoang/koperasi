<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$r = $connect->query('SELECT id,nama,nama_jenis FROM jenis_tabungan ORDER BY id ASC');
while($row=$r->fetch_assoc()) echo json_encode($row)."\n";
?>