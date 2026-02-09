<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$r = $connect->query("SHOW TABLES LIKE 'tabungan_masuk'");
echo json_encode(['has'=>($r && $r->num_rows>0), 'note' => 'helper script']);
?>