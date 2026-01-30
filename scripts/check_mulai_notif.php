<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$t = 'Setoran Tabungan Disetujui';
$stmt = $connect->prepare('SELECT id, id_pengguna, title, message, type, created_at FROM notifikasi WHERE title = ? ORDER BY created_at DESC LIMIT 50');
$stmt->bind_param('s', $t);
$stmt->execute();
$r = $stmt->get_result();
$rows = [];
while ($row = $r->fetch_assoc()) $rows[] = $row;
echo json_encode($rows, JSON_PRETTY_PRINT);
