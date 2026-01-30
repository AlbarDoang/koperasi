<?php
// api/pinjaman/debug_status.php
// Local-only diagnostic endpoint to inspect DB and latest pending pinjaman rows.
// Usage: access from localhost (127.0.0.1) only.

declare(strict_types=1);

// Restrict to localhost for safety
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1', '::ffff:127.0.0.1'])) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
if (!isset($con) || !($con instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Koneksi DB tidak tersedia']);
    exit;
}

$out = ['status' => true];
// DB name
$res = mysqli_query($con, 'SELECT DATABASE() as db');
if ($res) {
    $row = mysqli_fetch_assoc($res);
    $out['database'] = $row['db'] ?? null;
    mysqli_free_result($res);
}

// Last pending rows
$pending = [];
// Detect which pinjaman table exists (pinjaman, pinjaman_biasa, pinjaman_kredit, or any pinjaman%)
$escapedTable = null;
$tableCandidates = ['pinjaman', 'pinjaman_biasa', 'pinjaman_kredit'];
foreach ($tableCandidates as $t) {
    $chk = mysqli_query($con, "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'");
    if ($chk) {
        $rchk = mysqli_fetch_assoc($chk);
        mysqli_free_result($chk);
        if (isset($rchk['c']) && (int)$rchk['c'] > 0) {
            $escapedTable = $t;
            break;
        }
    }
}
if ($escapedTable === null) {
    $r = mysqli_query($con, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'pinjaman%' LIMIT 1");
    if ($r && $rw = mysqli_fetch_assoc($r)) {
        $escapedTable = $rw['TABLE_NAME'];
        mysqli_free_result($r);
    }
}
if ($escapedTable === null) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Tabel pinjaman tidak ditemukan']);
    exit;
}
$tbl = mysqli_real_escape_string($con, $escapedTable);

// Detect amount column existence to avoid errors against legacy schemas
$has_jumlah_pinjaman = false;
$has_jumlah = false;
$resCols = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $tbl . "' AND COLUMN_NAME IN ('jumlah_pinjaman','jumlah')");
if ($resCols) {
    while ($col = mysqli_fetch_assoc($resCols)) {
        if ($col['COLUMN_NAME'] === 'jumlah_pinjaman') $has_jumlah_pinjaman = true;
        if ($col['COLUMN_NAME'] === 'jumlah') $has_jumlah = true;
    }
    mysqli_free_result($resCols);
}

if ($has_jumlah_pinjaman && $has_jumlah) {
    $q = "SELECT id, id_pengguna, COALESCE(jumlah_pinjaman, jumlah) AS jumlah, tenor, tujuan_penggunaan, status, created_at FROM `" . $tbl . "` WHERE status = 'pending' ORDER BY created_at DESC LIMIT 20";
} elseif ($has_jumlah_pinjaman) {
    $q = "SELECT id, id_pengguna, jumlah_pinjaman AS jumlah, tenor, tujuan_penggunaan, status, created_at FROM `" . $tbl . "` WHERE status = 'pending' ORDER BY created_at DESC LIMIT 20";
} elseif ($has_jumlah) {
    $q = "SELECT id, id_pengguna, jumlah AS jumlah, tenor, tujuan_penggunaan, status, created_at FROM `" . $tbl . "` WHERE status = 'pending' ORDER BY created_at DESC LIMIT 20";
} else {
    $q = "SELECT id, id_pengguna, 0 AS jumlah, tenor, tujuan_penggunaan, status, created_at FROM `" . $tbl . "` WHERE status = 'pending' ORDER BY created_at DESC LIMIT 20";
}

$r = mysqli_query($con, $q);
if ($r) {
    while ($rr = mysqli_fetch_assoc($r)) $pending[] = $rr;
    mysqli_free_result($r);
}
$out['pending_count'] = count($pending);
$out['pending_rows'] = $pending;

// Tail debug log if exists
$logPath = __DIR__ . '/debug.log';
if (file_exists($logPath)) {
    $lines = array_slice(explode("\n", trim(file_get_contents($logPath))), -30);
    $out['debug_log_tail'] = $lines;
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;