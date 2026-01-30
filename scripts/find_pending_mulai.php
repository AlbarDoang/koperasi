<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$stmt = $connect->prepare("SELECT id_mulai_nabung, id_tabungan, jenis_tabungan, jumlah, status, created_at FROM mulai_nabung WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$res = $stmt->get_result();
$rows = array();
while ($r = $res->fetch_assoc()) $rows[] = $r;
// Output valid JSON only
echo json_encode($rows);
