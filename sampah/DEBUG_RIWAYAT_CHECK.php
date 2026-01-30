<?php
require 'gas_web/flutter_api/connection.php';

// Discover pengguna table columns and then try to locate the user by phone/name
$cols = [];
$desc = $connect->query("DESCRIBE pengguna");
if ($desc) {
    while ($rc = $desc->fetch_assoc()) { $cols[] = $rc['Field']; }
}

echo "=== PENGGUNA COLUMNS ===\n" . json_encode($cols) . "\n\n";

$candidates = ['jtbttn','jtbtn','081990608817'];
$user = null;
// Build possible column names to search
$possibleNameCols = array_intersect(['username','nama_lengkap','nama','name'], $cols);
$possiblePhoneCols = array_intersect(['no_hp','nohp','nomor_hp','phone','telepon'], $cols);

foreach ($candidates as $q) {
    $safe = $connect->real_escape_string($q);
    $where = [];
    foreach ($possibleNameCols as $c) { $where[] = "`$c` = '$safe'"; }
    foreach ($possiblePhoneCols as $c) { $where[] = "`$c` = '$safe'"; }
    if (empty($where)) continue;
    $sql = "SELECT * FROM pengguna WHERE (" . implode(' OR ', $where) . ") LIMIT 1";
    $r = $connect->query($sql);
    if ($r && $r->num_rows > 0) { $user = $r->fetch_assoc(); break; }
}

echo "=== USER LOOKUP ===\n";
if (!$user) { echo "User not found for candidates: " . json_encode($candidates) . "\n"; }
else { echo json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"; }

// If user found, inspect related tables
if ($user) {
    $uid = intval($user['id'] ?? 0);
    $id_tabungan = $user['id_tabungan'] ?? null;
    echo "\nUser numeric id: $uid\n";
    echo "id_tabungan field: " . ($id_tabungan ?? 'NULL') . "\n\n";

    // Check transaksi rows by various matching columns
    echo "=== TRANSAKSI (id_anggota = user.id) ===\n";
    $sql = sprintf("SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi WHERE id_anggota = %d ORDER BY tanggal DESC LIMIT 50", $uid);
    $res = $connect->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n";
        }
    } else { echo "Query error: " . $connect->error . "\n"; }

    echo "\n=== TRANSAKSI (id_anggota = id_tabungan) ===\n";
    if ($id_tabungan) {
        $safeTab = $connect->real_escape_string($id_tabungan);
        $sql2 = "SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi WHERE id_anggota = '".$safeTab."' OR id_anggota = ".(int)$safeTab." ORDER BY tanggal DESC LIMIT 50";
        $res2 = $connect->query($sql2);
        if ($res2) {
            while ($r2 = $res2->fetch_assoc()) { echo json_encode($r2, JSON_UNESCAPED_SLASHES) . "\n"; }
        } else { echo "Query2 error: " . $connect->error . "\n"; }
    }

    echo "\n=== TRANSAKSI WHERE KETERANGAN LIKE mulai_nabung or tabungan_masuk ===\n";
    $patterns = ['%mulai_nabung%', '%tabungan_masuk%'];
    foreach ($patterns as $p) {
        $safeP = $connect->real_escape_string($p);
        $q = "SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi WHERE keterangan LIKE '".$safeP."' ORDER BY tanggal DESC LIMIT 50";
        $r = $connect->query($q);
        echo "-- Pattern: $p --\n";
        if ($r) {
            while ($row = $r->fetch_assoc()) { echo json_encode($row, JSON_UNESCAPED_SLASHES) . "\n"; }
        } else { echo "Pattern query error: " . $connect->error . "\n"; }
    }

    echo "\n=== MULAI_NABUNG rows for this user (match by id_tabungan or phone/name) ===\n";
    // Discover columns in mulai_nabung to match correct phone/name column names
    $mn_cols = [];
    $d1 = $connect->query("DESCRIBE mulai_nabung");
    if ($d1) { while ($rr = $d1->fetch_assoc()) { $mn_cols[] = $rr['Field']; } }
    $mn_conds = [];
    if ($id_tabungan && in_array('id_tabungan', $mn_cols)) $mn_conds[] = "id_tabungan = '" . $connect->real_escape_string($id_tabungan) . "'";
    // phone column variants in mulai_nabung
    foreach (['nomor_hp','no_hp','nohp','phone'] as $pc) {
        if (in_array($pc, $mn_cols) && !empty($user[$pc] ?? null)) {
            $mn_conds[] = "$pc = '" . $connect->real_escape_string($user[$pc]) . "'";
        }
    }
    // name column in mulai_nabung
    if (in_array('nama_pengguna', $mn_cols) && !empty($user['nama_lengkap'] ?? null)) {
        $mn_conds[] = "nama_pengguna = '" . $connect->real_escape_string($user['nama_lengkap']) . "'";
    }
    if (!empty($mn_conds)) {
        $sqlmn = "SELECT * FROM mulai_nabung WHERE (" . implode(' OR ', $mn_conds) . ") ORDER BY tanggal DESC LIMIT 50";
        $rmn = $connect->query($sqlmn);
        if ($rmn) { while ($r = $rmn->fetch_assoc()) { echo json_encode($r, JSON_UNESCAPED_SLASHES) . "\n"; } } else { echo "mulai_nabung query error: " . $connect->error . "\n"; }
    } else { echo "No match conditions for mulai_nabung (no suitable columns)\n"; }

    echo "\n=== TABUNGAN_MASUK rows for this user (match by id_pengguna or phone) ===\n";
    // Discover columns in tabungan_masuk
    $tm_cols = [];
    $d2 = $connect->query("DESCRIBE tabungan_masuk");
    if ($d2) { while ($rr = $d2->fetch_assoc()) { $tm_cols[] = $rr['Field']; } }
    $tm_conds = [];
    if (in_array('id_pengguna', $tm_cols)) $tm_conds[] = "id_pengguna = " . $uid;
    foreach (['nomor_hp','no_hp','nohp','phone'] as $pc) {
        if (in_array($pc, $tm_cols) && !empty($user[$pc] ?? null)) {
            $tm_conds[] = "$pc = '" . $connect->real_escape_string($user[$pc]) . "'";
        }
    }
    if (!empty($tm_conds)) {
        $sqltm = "SELECT * FROM tabungan_masuk WHERE (" . implode(' OR ', $tm_conds) . ") ORDER BY created_at DESC LIMIT 50";
        $rtm = $connect->query($sqltm);
        if ($rtm) { while ($r = $rtm->fetch_assoc()) { echo json_encode($r, JSON_UNESCAPED_SLASHES) . "\n"; } } else { echo "tabungan_masuk query error: " . $connect->error . "\n"; }
    } else { echo "No match conditions for tabungan_masuk (no suitable columns)\n"; }

}

echo "\nDone.\n";
?>