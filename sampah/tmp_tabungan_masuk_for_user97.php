<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$uid = 97;
$stmt = $connect->prepare("SELECT id,id_pengguna,id_jenis_tabungan,jumlah,keterangan,created_at FROM tabungan_masuk WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) echo json_encode($r) . "\n";
?>