<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$stmt = $connect->prepare("SELECT id, id_pengguna, type, title, message, created_at FROM notifikasi WHERE type LIKE '%pinjaman%' ORDER BY created_at DESC LIMIT 20");
if ($stmt) { $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) print_r($row); } else { echo 'No notif table or prepare failed: '.$connect->error; }
?>