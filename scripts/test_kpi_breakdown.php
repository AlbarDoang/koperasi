<?php
require_once __DIR__ . '/../gas_web/config/database.php';
require_once __DIR__ . '/../gas_web/login/dashboard_helpers.php';

$con = getConnection();
if (!$con) { echo "DB connection failed\n"; exit(2); }

$kpis = dashboard_collect_kpis($con);
$chart = dashboard_generate_chart_payload($con, 3);

// 1) Transfer series must be all zeros in main chart
$transferZeros = true;
foreach ($chart['transfer'] as $v) { if (floatval($v) !== 0.0) $transferZeros = false; }
if ($transferZeros) echo "PASS: main chart transfer series is zeroed.\n"; else { echo "FAIL: main chart transfer series contains non-zero values.\n"; }

// 2) Total koperasi balance must equal deposits - loans
// compute deposit and loans using helpers
$deposits = 0.0;
// sum deposit sources (all-time)
$sources = dashboard_get_transaction_sources()['deposit'] ?? [];
foreach ($sources as $s) {
    if (!dashboard_table_exists($con, $s['table'])) continue;
    $amt = $s['amount'] ?? 'jumlah';
    // For tabungan_masuk we consider only status='berhasil'
    $where = '1=1';
    if ($s['table'] === 'tabungan_masuk' && dashboard_column_exists($con, 'tabungan_masuk', 'status')) {
        $where = "`status` = 'berhasil'";
    }
    if (!dashboard_column_exists($con, $s['table'], $amt)) continue; 
    $q = sprintf("SELECT COALESCE(SUM(`%s`),0) AS tot FROM `%s` WHERE %s", $amt, $s['table'], $where);
    $r = $con->query($q); if (!$r) continue; $row = $r->fetch_assoc(); $deposits += floatval($row['tot'] ?? 0);
}
$loans = 0.0;
$loanTables = ['pinjaman_biasa','pinjaman','pinjaman_kredit'];
foreach ($loanTables as $t) {
    if (!dashboard_table_exists($con, $t)) continue;
    $amt = dashboard_find_column($con, $t, ['jumlah_pinjaman','jumlah','amount','nominal']);
    $status = dashboard_find_column($con, $t, ['status','state','approval_status']);
    if (!$amt || !$status) continue;
    $q = sprintf("SELECT COALESCE(SUM(`%s`),0) AS tot FROM `%s` WHERE LOWER(`%s`) IN ('approved','disetujui','diterima','accepted')", $amt, $t, $status);
    $r = $con->query($q); if (!$r) continue; $row = $r->fetch_assoc(); $loans += floatval($row['tot'] ?? 0);
}
$expectedBalance = $deposits - $loans;
$actualBalance = $kpis['balance'];
if (abs($expectedBalance - $actualBalance) > 0.0001) {
    echo "FAIL: koperasi balance mismatch. expected={$expectedBalance} actual={$actualBalance}\n";
} else {
    echo "PASS: koperasi balance matches deposits - loans.\n";
}

?>