<?php
require_once __DIR__ . '/../gas_web/config/database.php';
require_once __DIR__ . '/../gas_web/login/dashboard_helpers.php';

$con = getConnection();
if (!$con) { echo "DB connection failed\n"; exit(2); }

function sql_sum_t_transfer($con, $range) {
    if (!dashboard_table_exists($con, 't_transfer')) return null;
    switch ($range) {
        case 'today': $where = "DATE(`tanggal`) = CURDATE()"; break;
        case 'week': $where = "YEARWEEK(`tanggal`,1) = YEARWEEK(CURDATE(),1)"; break;
        case 'month': $where = "DATE_FORMAT(`tanggal`, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"; break;
        default: $where = '1=1';
    }
    $q = "SELECT COALESCE(SUM(`nominal`),0) AS tot FROM `t_transfer` WHERE $where";
    $r = $con->query($q); if (!$r) return null; $row = $r->fetch_assoc(); return floatval($row['tot'] ?? 0);
}

$ok = true;
foreach (['today','week','month'] as $r) {
    $expected = sql_sum_t_transfer($con, $r);
    $actual = dashboard_sum_transaction($con, 'transfer', $r);

    if ($expected === null) {
        // t_transfer not present; attempt to compute expected from transaksi using outgoing-transfer heuristics
        if (!dashboard_table_exists($con, 'transaksi')) {
            echo "SKIP: neither t_transfer nor transaksi present, skipping range={$r}\n";
            continue;
        }
        // choose amount column
        $amtCol = dashboard_find_column($con, 'transaksi', ['jumlah','nominal','jumlah_masuk','jumlah_keluar','amount']);
        $dateCol = dashboard_find_column($con, 'transaksi', ['tanggal','created_at','updated_at']);
        if (!$amtCol || !$dateCol) {
            echo "SKIP: transaksi lacks expected columns, skipping range={$r}\n";
            continue;
        }
        $where = '';
        switch ($r) {
            case 'today': $where = sprintf("DATE(`%s`) = CURDATE()", $dateCol); break;
            case 'week': $where = sprintf("YEARWEEK(`%s`,1) = YEARWEEK(CURDATE(),1)", $dateCol); break;
            case 'month': $where = sprintf("DATE_FORMAT(`%s`, '%%Y-%%m') = DATE_FORMAT(CURDATE(), '%%Y-%%m')", $dateCol); break;
            default: $where = '1=1';
        }
        $clauses = [];
        if (dashboard_column_exists($con,'transaksi','jenis_transaksi')) $clauses[] = "(LOWER(`jenis_transaksi`) LIKE '%transfer%' AND LOWER(`jenis_transaksi`) LIKE '%keluar%')";
        if (dashboard_column_exists($con,'transaksi','kegiatan')) $clauses[] = "(LOWER(`kegiatan`) LIKE '%transfer%' AND LOWER(`kegiatan`) LIKE '%keluar%')";
        if (dashboard_column_exists($con,'transaksi','keterangan')) $clauses[] = "(LOWER(`keterangan`) LIKE '%transfer%' AND LOWER(`keterangan`) LIKE '%keluar%')";
        if (empty($clauses)) {
            echo "SKIP: transaksi lacks transfer-direction markers, skipping range={$r}\n";
            continue;
        }
        $q = sprintf("SELECT COALESCE(SUM(`%s`),0) AS tot FROM `transaksi` WHERE %s AND (%s)", $amtCol, $where, implode(' OR ', $clauses));
        $res = $con->query($q); $row = $res->fetch_assoc(); $expected = floatval($row['tot'] ?? 0);
    }

    if (abs($actual - $expected) > 0.0001) {
        echo "FAIL: transfer sum mismatch for {$r}. expected={$expected} actual={$actual}\n";
        $ok = false;
    } else {
        echo "PASS: transfer sum {$r} = {$actual}\n";
    }
}

if ($ok) { echo "All transfer aggregation checks passed.\n"; exit(0); } else { exit(1); }
