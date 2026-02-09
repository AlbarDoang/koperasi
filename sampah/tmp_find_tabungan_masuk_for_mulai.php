<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$mid = 9;
$stmt = $connect->prepare("SELECT id,id_pengguna,id_jenis_tabungan,jumlah,keterangan,created_at FROM tabungan_masuk WHERE keterangan LIKE ? LIMIT 10");
$like = "%mulai_nabung $mid%";
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
?>