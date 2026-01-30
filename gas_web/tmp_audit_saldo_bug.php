<?php
// Comprehensive audit of the saldo bug scenario
header('Content-Type: application/json');

$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'tabungan';

$connect = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($connect->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

$audit = [];

// Get user data
$user_result = $connect->query("SELECT id, nama_lengkap, saldo FROM pengguna LIMIT 1");
$user = $user_result->fetch_assoc();
$user_id = $user['id'];

$audit['user'] = $user;

// Get jenis tabungan (Pelajar = ID 2)
$jenis_result = $connect->query("SELECT * FROM jenis_tabungan");
$jenis_list = [];
while ($j = $jenis_result->fetch_assoc()) $jenis_list[] = $j;
$audit['jenis_tabungan'] = $jenis_list;

// Get tabungan_masuk for this user
$masuk_result = $connect->query("SELECT id, id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at FROM tabungan_masuk WHERE id_pengguna = $user_id");
$masuk = [];
while ($m = $masuk_result->fetch_assoc()) $masuk[] = $m;
$audit['tabungan_masuk'] = $masuk;

// Get tabungan_keluar for this user
$keluar_result = $connect->query("SELECT id, id_pengguna, id_jenis_tabungan, jumlah, status, rejected_reason, keterangan, created_at FROM tabungan_keluar WHERE id_pengguna = $user_id ORDER BY created_at ASC");
$keluar = [];
while ($k = $keluar_result->fetch_assoc()) $keluar[] = $k;
$audit['tabungan_keluar'] = $keluar;

// Analyze the bug scenario
$audit['analysis'] = [];

// Sum masuk untuk Pelajar (ID 2)
$masuk_pelajar = $connect->query("SELECT COALESCE(SUM(jumlah),0) as total FROM tabungan_masuk WHERE id_pengguna = $user_id AND id_jenis_tabungan = 2");
$masuk_pelajar_row = $masuk_pelajar->fetch_assoc();
$audit['analysis']['masuk_pelajar_total'] = intval($masuk_pelajar_row['total']);

// Sum keluar untuk Pelajar yang APPROVED
$keluar_approved = $connect->query("SELECT COALESCE(SUM(jumlah),0) as total FROM tabungan_keluar WHERE id_pengguna = $user_id AND id_jenis_tabungan = 2 AND status = 'approved'");
$keluar_approved_row = $keluar_approved->fetch_assoc();
$audit['analysis']['keluar_approved_total'] = intval($keluar_approved_row['total']);

// Sum keluar untuk Pelajar yang REJECTED
$keluar_rejected = $connect->query("SELECT COALESCE(SUM(jumlah),0) as total FROM tabungan_keluar WHERE id_pengguna = $user_id AND id_jenis_tabungan = 2 AND status = 'rejected'");
$keluar_rejected_row = $keluar_rejected->fetch_assoc();
$audit['analysis']['keluar_rejected_total'] = intval($keluar_rejected_row['total']);

// Sum ALL keluar untuk Pelajar (buggy calculation)
$keluar_all = $connect->query("SELECT COALESCE(SUM(jumlah),0) as total FROM tabungan_keluar WHERE id_pengguna = $user_id AND id_jenis_tabungan = 2");
$keluar_all_row = $keluar_all->fetch_assoc();
$audit['analysis']['keluar_all_total'] = intval($keluar_all_row['total']);

// Calculate expected vs actual
$audit['analysis']['expected_saldo_pelajar'] = $audit['analysis']['masuk_pelajar_total'] - $audit['analysis']['keluar_approved_total'];
$audit['analysis']['buggy_saldo_calculation'] = $audit['analysis']['masuk_pelajar_total'] - $audit['analysis']['keluar_all_total'];
$audit['analysis']['actual_dashboard_saldo'] = intval($user['saldo']);

// Scenario breakdown
$audit['scenario'] = [
    'step1_deposit' => '20k',
    'step2_request_5k_rejected' => 'Rejected (5k still in tabungan_keluar as rejected)',
    'step3_request_20k_rejected' => 'Rejected (20k still in tabungan_keluar as rejected)',
    'step4_request_15k_approved' => 'Approved (15k deducted from tabungan_masuk, wallet credited)',
    'expected_result' => 'Saldo Pelajar = 20k - 15k = 5k, Dashboard saldo = 15k',
    'actual_result' => 'Saldo Pelajar = ???, Dashboard saldo = ' . intval($user['saldo'])
];

echo json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$connect->close();
?>
