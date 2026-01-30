<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
require_once __DIR__ . '/../gas_web/flutter_api/notif_helper.php';
$user = 97;
$mid = 123456;
$ins_id = 654321;
$created = date('Y-m-d H:i:s');
$res = create_mulai_nabung_notification($connect, $user, $mid, $ins_id, $created);
echo "Result: "; var_export($res); echo "\n";
// Check last errors
echo "connect->error: "; echo $connect->error . "\n";
// Check latest notifikasi rows for that user with that title
$stmt = $connect->prepare('SELECT id, id_pengguna, title, message, data, created_at FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 10');
$stmt->bind_param('i', $user);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) print_r($row);
