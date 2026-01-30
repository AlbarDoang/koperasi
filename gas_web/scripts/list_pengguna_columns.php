<?php
include __DIR__ . '/../flutter_api/connection.php';
$r = $connect->query('SHOW COLUMNS FROM pengguna');
$out = [];
while ($c = $r->fetch_assoc()) $out[] = $c['Field'];
header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
?>