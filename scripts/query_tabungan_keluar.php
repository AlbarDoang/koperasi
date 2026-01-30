<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$q = $connect->prepare("SELECT id, id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at FROM tabungan_keluar WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 100");
$id = 95;
$q->bind_param('i', $id);
$q->execute(); $res = $q->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
file_put_contents(__DIR__.'/query_tabungan_keluar_out.json', json_encode($rows, JSON_PRETTY_PRINT));
echo json_encode(['count'=>count($rows)])."\n"; 
echo json_encode($rows, JSON_PRETTY_PRINT);
