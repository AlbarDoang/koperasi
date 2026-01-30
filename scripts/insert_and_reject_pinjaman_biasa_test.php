<?php
require_once __DIR__ . '/../gas_web/config/database.php';
require_once __DIR__ . '/../gas_web/login/approval_helpers.php';

// Insert a test pending pinjaman_biasa
$id_pengguna = 1;
$jumlah = 500000;
$tenor = 6;
$tujuan = 'Test rejection notification';
$sql = "INSERT INTO pinjaman_biasa (id_pengguna, jumlah_pinjaman, tenor, tujuan_penggunaan, status) VALUES (?, ?, ?, ?, 'pending')";
$stmt = $con->prepare($sql);
$stmt->bind_param('iiis', $id_pengguna, $jumlah, $tenor, $tujuan);
if (!$stmt->execute()) { echo "Insert failed: " . $stmt->error . "\n"; exit(1);} 
$newId = $con->insert_id;
$stmt->close();

echo "Inserted pending pinjaman_biasa id={$newId}\n";

// Fetch pending row via approval helpers
$schemaRes = approval_get_schema($con);
$schema = $schemaRes['schema'];
$pendingRow = approval_fetch_pending_row($con, $schema, $newId);
print_r($pendingRow);

// Apply reject action
$res = approval_apply_action($con, $schema, $pendingRow, 'reject', 1, 'Unit test reject reason');
print_r($res);

// Check notifications for user 1
$stmt2 = $con->prepare("SELECT id, id_pengguna, type, title, message, data, read_status, created_at FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 5");
$stmt2->bind_param('i', $id_pengguna);
$stmt2->execute();
$res2 = $stmt2->get_result();
$rows = $res2->fetch_all(MYSQLI_ASSOC);
echo "Recent notifikasi for user {$id_pengguna}:\n";
print_r($rows);

// Cleanup: remove test row (optional)
// $con->query("DELETE FROM pinjaman_biasa WHERE id = " . intval($newId));

?>