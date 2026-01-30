<?php
$connect = new mysqli('localhost', 'root', '', 'tabungan');
if ($connect->connect_error) {
    die("Connection error: " . $connect->connect_error);
}

$test_user_hp = "081990608817";

echo "=== Testing get_riwayat_tabungan Logic ===\n\n";

// Step 1: Resolve user ID
echo "Step 1: Resolve user ID from no_hp = '$test_user_hp'\n";
$stmt = $connect->prepare("SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1");
$stmt->bind_param('s', $test_user_hp);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$user_id = intval($row['id']);
$stmt->close();
echo "User ID: $user_id\n\n";

// Step 2: Query mulai_nabung with the same filter logic
echo "Step 2: Query mulai_nabung with nomor_hp = '$test_user_hp'\n";
$res = $connect->query("SELECT id, nomor_hp, jenis_tabungan, jumlah, status, created_at FROM mulai_nabung WHERE nomor_hp = '$test_user_hp' AND jenis_tabungan = 'Tabungan Reguler' AND status IN ('berhasil','menunggu_admin','menunggu_penyerahan','pending')");
echo "Rows: " . $res->num_rows . "\n";
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
echo "\n";

// Step 3: Test prepared statement version
echo "Step 3: Test with prepared statement\n";
$stmt = $connect->prepare("SELECT id, nomor_hp, jenis_tabungan, jumlah, status, created_at FROM mulai_nabung WHERE nomor_hp = ? AND jenis_tabungan = ? AND status IN ('berhasil','menunggu_admin','menunggu_penyerahan','pending')");
$jenis = "Tabungan Reguler";
$stmt->bind_param('ss', $test_user_hp, $jenis);
$stmt->execute();
$res = $stmt->get_result();
echo "Rows: " . $res->num_rows . "\n";
while ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
}
$stmt->close();

$connect->close();
?>
