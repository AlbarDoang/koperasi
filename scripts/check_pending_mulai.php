<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$res = $connect->query("SELECT id_mulai_nabung,id_tabungan,jenis_tabungan,jumlah,status,created_at FROM mulai_nabung WHERE status='pending' ORDER BY created_at DESC LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
} else {
    echo "NO_PENDING\n";
}
