<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$res = $connect->query("SELECT id_mulai_nabung,id_tabungan,jenis_tabungan,jumlah,status,created_at FROM mulai_nabung WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) ORDER BY created_at DESC LIMIT 50");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
?>