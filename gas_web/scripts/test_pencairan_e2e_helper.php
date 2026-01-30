<?php
// E2E helper test for pencairan using ledger_helpers directly (for DB variants without t_keluar)
// Run: php scripts/test_pencairan_e2e_helper.php

$GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
include __DIR__ . '/../flutter_api/connection.php';
include __DIR__ . '/../login/function/ledger_helpers.php';

$out = [];
// find a user with some balance (or any user)
$colsRes = $connect->query("SHOW COLUMNS FROM pengguna");
$cols = [];
while ($cr = $colsRes->fetch_assoc()) $cols[] = $cr['Field'];

// prefer approved users if status_akun exists
$where = "";
if (in_array('status_akun', $cols)) {
    // Prefer fully approved users
    $where = "WHERE LOWER(status_akun) = 'approved' AND saldo > 10000";
} else if (in_array('status', $cols)) {
    $where = "WHERE LOWER(status) LIKE '%aktif%' AND saldo > 10000";
} else {
    $where = "WHERE saldo > 10000";
}
$res = $connect->query("SELECT id, nama_lengkap as nama, saldo FROM pengguna $where ORDER BY id DESC LIMIT 1");
if (!$res || $res->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'No suitable pengguna found for helper test']);
    exit(1);
}
$user = $res->fetch_assoc();
$user_id = intval($user['id']);
$initial_saldo = floatval($user['saldo']);
$amount = min(50000, floor($initial_saldo / 2));
if ($amount <= 0) { echo json_encode(['success' => false, 'message' => 'Insufficient saldo for user', 'saldo' => $initial_saldo]); exit(1); }
$out['user'] = $user;
$out['initial_saldo'] = $initial_saldo;
$out['amount'] = $amount;

// Count existing tabungan_keluar rows
$has_tab_keluar = has_table($connect, 'tabungan_keluar');
$out['has_tabungan_keluar'] = $has_tab_keluar;
$before_count = 0;
if ($has_tab_keluar) {
    $r = $connect->query("SELECT COUNT(*) as c FROM tabungan_keluar WHERE id_pengguna = " . $user_id);
    $rr = $r->fetch_assoc(); $before_count = intval($rr['c']);
}
$out['before_tabungan_keluar_count'] = $before_count;

// Create ledger keluar
$keterangan = 'E2E helper penarikan test ' . date('c');
$ok = insert_ledger_keluar($connect, $user_id, $amount, $keterangan, 1, 1);
$out['insert_ledger_keluar_ok'] = (bool)$ok;

// Check results
$after_count = 0; $found_row = null;
if ($has_tab_keluar) {
    $r = $connect->prepare("SELECT * FROM tabungan_keluar WHERE id_pengguna = ? AND keterangan = ? ORDER BY id DESC LIMIT 1");
    $r->bind_param('is', $user_id, $keterangan);
    $r->execute();
    $res = $r->get_result();
    if ($res && $res->num_rows > 0) {
        $found_row = $res->fetch_assoc();
    }
    $r->close();
    $r2 = $connect->query("SELECT COUNT(*) as c FROM tabungan_keluar WHERE id_pengguna = " . $user_id);
    $after_count = intval($r2->fetch_assoc()['c']);
}
$out['after_tabungan_keluar_count'] = $after_count;
$out['found_tabungan_keluar'] = $found_row;

// Check pengguna.saldo to see if it changed (modern DBs should not change)
$r3 = $connect->query("SELECT saldo FROM pengguna WHERE id = " . $user_id . " LIMIT 1");
$saldo_after = floatval($r3->fetch_assoc()['saldo']);
$out['saldo_after'] = $saldo_after;
$out['saldo_changed'] = (abs($saldo_after - $initial_saldo) > 0.0001);

// Cleanup: delete created tabungan_keluar row if found
$cleanup = ['deleted_tabungan_keluar' => false, 'credited_back' => false];
if ($found_row) {
    $del = $connect->query("DELETE FROM tabungan_keluar WHERE id = " . intval($found_row['id']));
    $cleanup['deleted_tabungan_keluar'] = (bool)$del;
}
// If saldo changed (fallback), credit back
if ($out['saldo_changed']) {
    $diff = $initial_saldo - $saldo_after;
    if (abs($diff) > 0.0001) {
        $credit_ok = wallet_credit($connect, $user_id, $diff, 'Revert E2E helper');
        $cleanup['credited_back'] = (bool)$credit_ok;
    }
}
$out['cleanup'] = $cleanup;

// Recheck final saldo
$r4 = $connect->query("SELECT saldo FROM pengguna WHERE id = " . $user_id . " LIMIT 1");
$out['final_saldo'] = floatval($r4->fetch_assoc()['saldo']);
$out['saldo_restored_ok'] = (abs($out['final_saldo'] - $initial_saldo) < 0.0001);

$out['success'] = true;
$out['message'] = 'Helper E2E run complete';

echo json_encode($out, JSON_PRETTY_PRINT);

?>