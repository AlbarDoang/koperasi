<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
function find($q) {
    global $connect;
    $safe = $connect->real_escape_string($q);
    $r = $connect->query("SELECT id, nama_jenis FROM jenis_tabungan WHERE nama_jenis LIKE '%$safe%' LIMIT 10");
    while ($row = $r->fetch_assoc()) echo json_encode($row) . "\n";
}
find('Aqiqah');
find('Investasi');
find('Reguler');
find('Pelajar');
find('Umroh');
find('Qurban');
?>