<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$id = 97;
$stmt = $connect->prepare("SELECT id, nama_lengkap, saldo FROM pengguna WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) echo json_encode($row) . "\n"; else echo "not found\n";
?>