<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$id_user = $argv[1] ?? null;
if ($id_user === null) { echo "Usage: php find_last_pending_withdrawal.php <user_id>\n"; exit(1); }
$stmt = $connect->prepare("SELECT id, id_pengguna, id_jenis_tabungan, jumlah, status, created_at FROM tabungan_keluar WHERE id_pengguna = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
$stmt->bind_param('i', $id_user);
$stmt->execute(); $res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $r = $res->fetch_assoc();
    echo json_encode($r, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No pending withdrawal found for user $id_user\n";
}
