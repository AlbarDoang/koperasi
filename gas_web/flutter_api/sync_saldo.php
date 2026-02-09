<?php
/**
 * API: Sync Saldo
 * Recalculate saldo for a user (or all users) using ledger tables (transaksi, tabungan_masuk/keluar) and
 * update `pengguna.saldo` when there is a discrepancy.
 *
 * POST params:
 * - id_pengguna OR id_tabungan OR username OR all=1
 */
include 'connection.php';
require_once 'helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed. Use POST"]);
    exit();
}

$targetAll = isset($_POST['all']) && ($_POST['all'] == '1' || $_POST['all'] === true);
$input_id = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : (isset($_POST['id_tabungan']) ? trim($_POST['id_tabungan']) : (isset($_POST['username']) ? trim($_POST['username']) : ''));

function calc_for_user($connect, $row) {
    // $row is associative array from pengguna table
    $id = intval($row['id']);
    // Use safe_sum_transaksi by trying id_tabungan first, then id_pengguna
    $id_tabungan_val = $row['id_tabungan'] ?? ($row['nis'] ?? '');
    $id_pengguna_val = $row['id_pengguna'] ?? ($row['id_pengguna'] ?? $row['id']);

    $saldo_calculated = null;
    if (!empty($id_tabungan_val)) {
        $trx = safe_sum_transaksi($connect, $id_tabungan_val);
        if ($trx !== null) {
            $saldo_calculated = floatval($trx['saldo']);
        }
    }
    if ($saldo_calculated === null) {
        if (!empty($id_pengguna_val)) {
            $trx = safe_sum_transaksi($connect, $id_pengguna_val);
            if ($trx !== null) {
                $saldo_calculated = floatval($trx['saldo']);
            }
        }
    }

    // Fallback: sum tabungan_masuk / tabungan_keluar
    if ($saldo_calculated === null) {
        $total_masuk = 0; $total_keluar = 0;
        $has_m = $connect->query("SHOW TABLES LIKE 'tabungan_masuk'");
        $has_k = $connect->query("SHOW TABLES LIKE 'tabungan_keluar'");
        if ($has_m && $has_m->num_rows > 0) {
            // Exclude top-up entries (created by admin_verifikasi_mulai_nabung) from wallet calculation.
            // Top-ups should be reflected only in per-jenis tabungan and NOT be part of pengguna.saldo (dashboard).
            $q = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS s FROM tabungan_masuk WHERE id_pengguna = ? AND (keterangan IS NULL OR keterangan NOT LIKE '%Topup%')");
            $q->bind_param('i', $id);
            $q->execute(); $res = $q->get_result(); if ($res) { $r = $res->fetch_assoc(); $total_masuk = floatval($r['s']); }
            $q->close();
        }
        if ($has_k && $has_k->num_rows > 0) {
            // Only count approved withdrawals when computing wallet sync
            $has_status_col = false; $chkS = $connect->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'status'"); if ($chkS && $chkS->num_rows > 0) $has_status_col = true;
            $statusClause = $has_status_col ? " AND status = 'approved'" : "";
            $sqlOut = "SELECT COALESCE(SUM(jumlah),0) AS s FROM tabungan_keluar WHERE id_pengguna = ?" . $statusClause;
            $q = $connect->prepare($sqlOut);
            $q->bind_param('i', $id);
            $q->execute(); $res = $q->get_result(); if ($res) { $r = $res->fetch_assoc(); $total_keluar = floatval($r['s']); }
            $q->close();
        }
        // legacy tabungan table
        $has_tab = $connect->query("SHOW TABLES LIKE 'tabungan'");
        if (($has_tab && $has_tab->num_rows > 0)) {
            // sum by id_pengguna column if exists
            $q = $connect->prepare("SELECT COALESCE(SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE 0 END),0) AS masuk, COALESCE(SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END),0) AS keluar FROM tabungan WHERE id_pengguna = ?");
            $q->bind_param('i', $id);
            $q->execute(); $res = $q->get_result(); if ($res) { $r = $res->fetch_assoc(); $total_masuk += floatval($r['masuk']); $total_keluar += floatval($r['keluar']); }
            $q->close();
        }
        $saldo_calculated = $total_masuk - $total_keluar;
    }

    return ['id' => $id, 'saldo_calculated' => $saldo_calculated];
}

$results = [];

if ($targetAll) {
    $res = $connect->query("SELECT * FROM pengguna");
    if (!$res) {
        echo json_encode(["success" => false, "message" => "DB error: " . $connect->error]);
        exit();
    }
    while ($row = $res->fetch_assoc()) {
        $calc = calc_for_user($connect, $row);
        $id = $calc['id']; $new = floatval($calc['saldo_calculated']);
        $old = floatval($row['saldo'] ?? 0);
        if (intval($old) !== intval($new)) {
            // backup to log and update
            $log = date('c') . " SYNC user={$id} old={$old} new={$new}\n";
            @file_put_contents(__DIR__ . '/sync_saldo.log', $log, FILE_APPEND);
            // Update pengguna.saldo
            $stmt = $connect->prepare("UPDATE pengguna SET saldo = ? WHERE id = ?");
            $stmt->bind_param('di', $new, $id);
            $stmt->execute();
            $stmt->close();
            // Also write to saldo_audit.log for traceability
            @file_put_contents(__DIR__ . '/saldo_audit.log', date('c') . " SYNC_UPDATE user={$id} old={$old} new={$new}\n", FILE_APPEND);
            $results[] = ['id' => $id, 'old' => $old, 'new' => $new, 'updated' => true];
        } else {
            $results[] = ['id' => $id, 'old' => $old, 'new' => $new, 'updated' => false];
        }
    }
    echo json_encode(["success" => true, "message" => "Sync completed for all users", "results" => $results]);
    exit();
}

if (empty($input_id)) {
    echo json_encode(["success" => false, "message" => "Provide id_pengguna, id_tabungan/username or set all=1"]);
    exit();
}

// Try to find the user by several columns but only include columns that actually exist
$safe = $connect->real_escape_string($input_id);
$colExists = function($col) use ($connect) {
    $r = $connect->query("SHOW COLUMNS FROM `pengguna` LIKE '" . $connect->real_escape_string($col) . "'");
    return ($r && $r->num_rows > 0);
};
$whereParts = [];
if ($colExists('id')) $whereParts[] = "id = '$safe'";
if ($colExists('id_pengguna')) $whereParts[] = "id_pengguna = '$safe'";
if ($colExists('id_pengguna')) $whereParts[] = "id_pengguna = '$safe'";
if ($colExists('id_tabungan')) $whereParts[] = "id_tabungan = '$safe'";
if ($colExists('username')) $whereParts[] = "username = '$safe'";
if ($colExists('no_hp')) $whereParts[] = "no_hp = '$safe'";
if ($colExists('nis')) $whereParts[] = "nis = '$safe'";

if (empty($whereParts)) {
    echo json_encode(["success" => false, "message" => "No suitable column found to lookup user"]);
    exit();
}

$sql = "SELECT * FROM pengguna WHERE (" . implode(' OR ', $whereParts) . ") LIMIT 1";
$res = $connect->query($sql);
if (!$res || $res->num_rows == 0) {
    echo json_encode(["success" => false, "message" => "User not found for: $input_id"]);
    exit();
}
$row = $res->fetch_assoc();
$calc = calc_for_user($connect, $row);
$new = floatval($calc['saldo_calculated']);
$old = floatval($row['saldo'] ?? 0);
$updated = false;
if (intval($old) !== intval($new)) {
    $log = date('c') . " SYNC user={$row['id']} old={$old} new={$new}\n";
    @file_put_contents(__DIR__ . '/sync_saldo.log', $log, FILE_APPEND);
    $stmt = $connect->prepare("UPDATE pengguna SET saldo = ? WHERE id = ?");
    $stmt->bind_param('di', $new, $row['id']);
    $stmt->execute();
    $stmt->close();
    @file_put_contents(__DIR__ . '/saldo_audit.log', date('c') . " SYNC_UPDATE user={$row['id']} old={$old} new={$new}\n", FILE_APPEND);
    $updated = true;
}

echo json_encode(["success" => true, "message" => "Sync completed", "id" => $row['id'], "old" => $old, "new" => $new, "updated" => $updated]);


