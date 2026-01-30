<?php
// E2E test script for pencairan (withdrawal approval)
// Run: php scripts/test_pencairan_e2e.php

$GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true; // avoid connection.php fallback
include __DIR__ . '/../flutter_api/connection.php';
include __DIR__ . '/../login/function/ledger_helpers.php';

function call_api_file($name, $params) {
    // Use global connection variables from outer scope (connection.php executed once globally)
    global $connect, $con, $koneksi;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $params;
    ob_start();
    include __DIR__ . '/../flutter_api/' . $name;
    $out = ob_get_clean();
    // Clear POST to avoid leaking between calls
    $_POST = [];
    return $out;
}

$out = [];
// 1) find a suitable active pengguna with sufficient balance
// detect whether 'id_anggota' exists in this installation; use it if present, otherwise fall back to 'id'
$colsRes = $connect->query("SHOW COLUMNS FROM pengguna");
$cols = [];
while ($cr = $colsRes->fetch_assoc()) $cols[] = $cr['Field'];
$idField = in_array('id_anggota', $cols) ? 'id_anggota' : 'id';

// Build a safe select list based on present columns
$selectParts = ["{$idField} AS id_anggota"];
$selectParts[] = (in_array('id', $cols) ? 'id' : "NULL AS id");
$selectParts[] = (in_array('nis', $cols) ? 'nis' : "'' AS nis");
$selectParts[] = (in_array('no_hp', $cols) ? 'no_hp' : "'' AS no_hp");
$selectParts[] = (in_array('nama', $cols) ? 'nama' : "'' AS nama");
$selectParts[] = (in_array('saldo', $cols) ? 'saldo' : "0 AS saldo");
$select = implode(', ', $selectParts);

$whereActive = in_array('status', $cols) ? "WHERE status='aktif' AND saldo > 10000" : "WHERE saldo > 10000";
$res = $connect->query("SELECT {$select} FROM pengguna {$whereActive} ORDER BY id DESC LIMIT 1");
if ($res && $res->num_rows > 0) {
    $user = $res->fetch_assoc();
} else {
    // fallback: pick any active (or just any pengguna)
    $whereAny = in_array('status', $cols) ? "WHERE status='aktif'" : "";
    $res = $connect->query("SELECT {$select} FROM pengguna {$whereAny} ORDER BY id DESC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
    } else {
        echo json_encode([ 'success' => false, 'message' => 'No active pengguna found to run test' ]);
        exit(1);
    }
}

$initial_saldo = floatval($user['saldo']);
$amount = min(50000, floor($initial_saldo / 2));
if ($amount <= 0) {
    echo json_encode([ 'success' => false, 'message' => 'User saldo too low to run test', 'saldo' => $initial_saldo ]);
    exit(1);
}

$out['chosen_user'] = ['id_anggota' => $user['id_anggota'], 'id' => intval($user['id']), 'nis' => $user['nis'], 'no_hp' => $user['no_hp'], 'nama' => $user['nama'], 'saldo' => $initial_saldo];
$test_keterangan = 'E2E test pencairan ' . date('c');

// 2) Call add_penarikan.php
// Choose identifier to send to API: prefer nis/no_hp if available (alias 'nis' in our select), otherwise use id_anggota
$api_identifier = !empty($user['nis']) ? $user['nis'] : (!empty($user['no_hp']) ? $user['no_hp'] : $user['id_anggota']);
$addRespRaw = call_api_file('add_penarikan.php', [ 'id_anggota' => $api_identifier, 'jumlah' => $amount, 'id_petugas' => 1, 'keterangan' => $test_keterangan ]);
$addResp = json_decode($addRespRaw, true);
$out['add_raw'] = $addRespRaw;
$out['add_json'] = $addResp;
if (empty($addResp) || empty($addResp['success'])) {
    echo json_encode([ 'success' => false, 'message' => 'add_penarikan failed', 'response' => $addResp ]);
    exit(1);
}
$no = $addResp['data']['no_keluar'];
$out['no_keluar'] = $no;

// 3) Verify t_keluar row exists and is pending
$tk = $connect->query("SELECT * FROM t_keluar WHERE no_keluar='" . $connect->real_escape_string($no) . "' LIMIT 1");
if (!$tk || $tk->num_rows == 0) {
    echo json_encode([ 'success' => false, 'message' => 't_keluar row not found after add', 'no_keluar' => $no ]);
    exit(1);
}
$trow = $tk->fetch_assoc();
$out['t_keluar_before'] = $trow;

// 4) Approve via approve_penarikan.php
$approveRespRaw = call_api_file('approve_penarikan.php', [ 'no_keluar' => $no, 'action' => 'approve', 'approved_by' => 1 ]);
$approveResp = json_decode($approveRespRaw, true);
$out['approve_raw'] = $approveRespRaw;
$out['approve_json'] = $approveResp;
if (empty($approveResp) || empty($approveResp['success'])) {
    echo json_encode([ 'success' => false, 'message' => 'approve_penarikan failed', 'response' => $approveResp ]);
    exit(1);
}

// 5) Re-query t_keluar and check status
$tk2 = $connect->query("SELECT * FROM t_keluar WHERE no_keluar='" . $connect->real_escape_string($no) . "' LIMIT 1");
$trow2 = $tk2->fetch_assoc();
$out['t_keluar_after'] = $trow2;

// 6) Check whether tabungan_keluar exists and find inserted ledger
$has_tabungan_keluar = false;
$r = $connect->query("SHOW TABLES LIKE 'tabungan_keluar'");
if ($r && $r->num_rows > 0) $has_tabungan_keluar = true;
$out['has_tabungan_keluar'] = $has_tabungan_keluar;

$ledger = null;
if ($has_tabungan_keluar) {
    // find by keterangan pattern
    $k = 'Penarikan disetujui (no_keluar ' . $no . ')';
    $stmt = $connect->prepare("SELECT * FROM tabungan_keluar WHERE keterangan = ? ORDER BY id DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $k);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $ledger = $res->fetch_assoc();
        }
        $stmt->close();
    }
    $out['found_tabungan_keluar'] = $ledger !== null;
    $out['tabungan_keluar_row'] = $ledger;
} else {
    // fallback: check that pengguna.saldo was reduced by amount
    $r2 = $connect->query("SELECT saldo FROM pengguna WHERE (id_anggota='" . $connect->real_escape_string($user['id_anggota']) . "' OR id='" . $connect->real_escape_string($user['id']) . "') LIMIT 1");
    $newSaldoRow = $r2->fetch_assoc();
    $out['new_saldo'] = floatval($newSaldoRow['saldo']);
    $out['expected_saldo'] = $initial_saldo - $amount;
    $out['saldo_ok'] = (abs($out['new_saldo'] - $out['expected_saldo']) < 0.0001);
}

// 7) Verify transaksi row exists for this no_keluar
$tx = $connect->query("SELECT * FROM transaksi WHERE no_keluar='" . $connect->real_escape_string($no) . "' ORDER BY id DESC LIMIT 5");
$txrows = [];
if ($tx) {
    while ($tr = $tx->fetch_assoc()) $txrows[] = $tr;
}
$out['transaksi_rows'] = $txrows;

// 8) Cleanup: attempt to revert effects
$cleanup = ['deleted_tabungan_keluar' => false, 'credited_back' => false, 'deleted_transaksi' => 0, 'deleted_t_keluar' => 0];
// If tabungan_keluar inserted, delete it
if ($ledger) {
    $idlk = intval($ledger['id']);
    $q = $connect->query("DELETE FROM tabungan_keluar WHERE id = " . $idlk);
    $cleanup['deleted_tabungan_keluar'] = (bool)$q;
}

// Credit back to pengguna.saldo if it was decreased (fallback case) or to be safe always credit back the amount
// Use wallet_credit to add back
$credit_ok = wallet_credit($connect, intval($user['id']), $amount, 'Revert E2E test pencairan');
$cleanup['credited_back'] = $credit_ok;

// Delete transaksi rows created for this no_keluar
if (!empty($txrows)) {
    $cnt = 0;
    foreach ($txrows as $t) {
        if (!empty($t['id'])) {
            $del = $connect->query("DELETE FROM transaksi WHERE id = " . intval($t['id']));
            if ($del) $cnt++;
        }
    }
    $cleanup['deleted_transaksi'] = $cnt;
}

// Delete t_keluar test row
$deltk = $connect->query("DELETE FROM t_keluar WHERE no_keluar='" . $connect->real_escape_string($no) . "'");
$cleanup['deleted_t_keluar'] = (bool)$deltk;
$out['cleanup'] = $cleanup;

// Re-check saldo after credit
$r3 = $connect->query("SELECT saldo FROM pengguna WHERE (id_anggota='" . $connect->real_escape_string($user['id_anggota']) . "' OR id='" . $connect->real_escape_string($user['id']) . "') LIMIT 1");
$saldolast = $r3->fetch_assoc();
$out['saldo_after_cleanup'] = floatval($saldolast['saldo']);

// If saldo after cleanup differs from initial, note it
$out['saldo_restored_ok'] = (abs($out['saldo_after_cleanup'] - $initial_saldo) < 0.0001);

$out['success'] = true;
$out['message'] = 'E2E test completed (approved and reverted). Check cleanup results.';

echo json_encode($out, JSON_PRETTY_PRINT);

?>