<?php
// api/approval_pinjaman.php
// Single endpoint to return approvals for pinjaman_biasa or pinjaman_kredit

// Simple, strict implementation: return rows from either `pinjaman_biasa` or `pinjaman_kredit`
ini_set('display_errors', '0');
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
// Try to include the date helper if available (non-fatal)
if (file_exists(__DIR__ . '/../koneksi/fungsi_indotgl.php')) {
    include_once __DIR__ . '/../koneksi/fungsi_indotgl.php';
} elseif (file_exists(__DIR__ . '/../login/koneksi/fungsi_indotgl.php')) {
    include_once __DIR__ . '/../login/koneksi/fungsi_indotgl.php';
} else {
    // define a minimal fallback so code doesn't die if helper is missing
    if (!function_exists('tgl_indo')) {
        function tgl_indo($d) { return $d; }
    }
}

$payload = ['data' => []];

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'biasa';
if ($type !== 'kredit') $type = 'biasa';

$table = ($type === 'kredit') ? 'pinjaman_kredit' : 'pinjaman_biasa';

// ensure DB connection
if (!isset($con) || !$con) {
    @ob_end_clean();
    echo json_encode(['data' => [], 'error' => 'Database connection error']);
    exit;
}

// ensure table exists
$check = @mysqli_query($con, "SHOW TABLES LIKE '" . mysqli_real_escape_string($con, $table) . "'");
if (!$check || mysqli_num_rows($check) === 0) {
    @ob_end_clean();
    echo json_encode(['data' => [], 'error' => 'Tabel tidak ditemukan']);
    exit;
}

// Find whether `pengguna` table exists to fetch member names when possible
$has_pengguna = false;
$resPeng = @mysqli_query($con, "SHOW TABLES LIKE 'pengguna'");
if ($resPeng && mysqli_num_rows($resPeng) > 0) {
    $has_pengguna = true;
}

// inspect columns in the target table so we don't reference missing columns
$tblCols = [];
$colRes = @mysqli_query($con, "SHOW COLUMNS FROM `" . mysqli_real_escape_string($con, $table) . "`");
if ($colRes) {
    while ($cr = mysqli_fetch_assoc($colRes)) { $tblCols[] = ($cr['Field'] ?? null); }
}

// build name expression: prefer name columns from the pinjaman table, then pengguna (if present)
// Use unaliased column names when selecting directly from the pinjaman table, and use the
// correct alias when performing a LEFT JOIN to `pengguna` so we don't reference the wrong alias
$pinCandidates = ['nama_anggota','nama','name'];
$pinAliased = [];
$pinUnaliased = [];
foreach ($pinCandidates as $c) {
    if (in_array($c, $tblCols, true)) {
        $pinAliased[] = '%s.' . $c; // placeholder for table alias
        $pinUnaliased[] = $c;
    }
}

// gather pengguna columns (only if pengguna exists)
$pengCols = [];
if ($has_pengguna) {
    $pcolRes = @mysqli_query($con, "SHOW COLUMNS FROM pengguna");
    if ($pcolRes) { while ($pc = mysqli_fetch_assoc($pcolRes)) { $pengCols[] = ($pc['Field'] ?? null); } }
}
$pengParts = [];
if (in_array('nama_lengkap', $pengCols, true)) $pengParts[] = 'u.nama_lengkap';
if (in_array('nama', $pengCols, true)) $pengParts[] = 'u.nama';
if (in_array('name', $pengCols, true)) $pengParts[] = 'u.name';

// nameExpr will be assembled later depending on whether we perform a JOIN (alias available) or not
// For now prepare the fallback unaliased expression if no JOIN will be used
// If we cannot join to `pengguna`, fall back to using only pinjaman's own name-like columns
$nameFallbackParts = $pinUnaliased;
if (empty($nameFallbackParts)) $nameFallbackParts[] = "''";
$nameExprFallback = 'COALESCE(' . implode(', ', $nameFallbackParts) . ", '') AS name";

// fetch rows. Use a LEFT JOIN to pengguna only if the table exists
if ($has_pengguna) {
    // use the correct alias depending on type: 'p' for biasa, 'k' for kredit
    $alias = ($type === 'kredit') ? 'k' : 'p';
    $joinConds = [];
    // candidate mappings (pengguna_col => pinjaman_col)
    $mappings = [
        ['peng'=>'id', 'pin'=>'id_pengguna'],
        ['peng'=>'id_pengguna', 'pin'=>'id_pengguna'],
        ['peng'=>'id_anggota', 'pin'=>'id_pengguna'],
        ['peng'=>'id', 'pin'=>'id'],
        ['peng'=>'id', 'pin'=>'pengguna_id'],
        ['peng'=>'id', 'pin'=>'anggota_id'],
    ];
    foreach ($mappings as $map) {
        if (in_array($map['peng'], $pengCols, true) && in_array($map['pin'], $tblCols, true)) {
            $joinConds[] = 'u.' . $map['peng'] . ' = ' . $alias . '.' . $map['pin'];
        }
    }

    // Limit: show only top 10 for kredit, and up to 500 for biasa
    $limit = ($type === 'kredit') ? 10 : 500;

    // Prefer ordering by a date column (oldest -> newest). Find a sensible date column if present
    $orderCols = ['tanggal_pengajuan','created_at','date'];
    $orderFieldName = null;
    foreach ($orderCols as $oc) {
        if (in_array($oc, $tblCols, true)) { $orderFieldName = $oc; break; }
    }
    if (!$orderFieldName) $orderFieldName = 'id';

    // Status filter support: accepts 'pending','approved','rejected' or 'all' (default)
    $statusParam = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all';

    if (!empty($joinConds)) {
        $onClause = implode(' OR ', $joinConds);
        // build name expression using aliased pinjaman columns and pengguna columns
        $nameParts = [];
        foreach ($pinAliased as $p) { $nameParts[] = sprintf($p, $alias); }
        $nameParts = array_merge($nameParts, $pengParts);
        if (empty($nameParts)) $nameParts[] = "''";
        $nameExpr = 'COALESCE(' . implode(', ', $nameParts) . ", '') AS name";

        // build status clause (use aliased column reference)
        $statusClause = '';
        if ($statusParam !== '' && $statusParam !== 'all') {
            $statusEsc = mysqli_real_escape_string($con, $statusParam);
            if ($statusParam === 'pending') {
                $statusClause = " (COALESCE(LCASE(COALESCE(" . $alias . ".status, '')), '') = 'pending' OR COALESCE(" . $alias . ".status, '') = '') ";
            } else {
                $statusClause = " LCASE(COALESCE(" . $alias . ".status, '')) = '" . $statusEsc . "' ";
            }
        }

        if ($type === 'biasa') {
            $sql = "SELECT p.*, " . $nameExpr . " FROM `" . $table . "` p LEFT JOIN pengguna u ON (" . $onClause . ") " . ($statusClause ? " WHERE " . $statusClause : "") . " ORDER BY " . $alias . '.' . $orderFieldName . " ASC LIMIT $limit";
        } else {
            $sql = "SELECT k.*, " . $nameExpr . " FROM `" . $table . "` k LEFT JOIN pengguna u ON (" . $onClause . ") " . ($statusClause ? " WHERE " . $statusClause : "") . " ORDER BY " . $alias . '.' . $orderFieldName . " ASC LIMIT $limit";
        }
    } else {
        // no safe join condition found — fall back to selecting only from the pinjaman table
        // use fallback name expression and order by a plain column name (oldest -> newest)
        // build status clause for non-aliased table
        $statusClause = '';
        if ($statusParam !== '' && $statusParam !== 'all') {
            $statusEsc = mysqli_real_escape_string($con, $statusParam);
            if ($statusParam === 'pending') {
                $statusClause = " (LCASE(COALESCE(status, '')) = 'pending' OR COALESCE(status, '') = '') ";
            } else {
                $statusClause = " LCASE(COALESCE(status, '')) = '" . $statusEsc . "' ";
            }
        }

        $sql = sprintf('SELECT *, %s FROM `%s` ' . ($statusClause ? ' WHERE ' . $statusClause : '') . ' ORDER BY %s ASC LIMIT %d', $nameExprFallback, mysqli_real_escape_string($con, $table), mysqli_real_escape_string($con, $orderFieldName), $limit);
    }
} else {
    // no pengguna table - just select from pinjaman table and use available columns for name
    // make ordering oldest->newest (ASC) so admin table ordering matches expectation
    $sql = sprintf('SELECT *, %s FROM `%s` ORDER BY id ASC LIMIT 500', $nameExprFallback, mysqli_real_escape_string($con, $table));
}
@file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " SQL: " . $sql . "\n", FILE_APPEND | LOCK_EX);
$res = @mysqli_query($con, $sql);
@file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " SQL_ERR: " . mysqli_error($con) . " ROWS: " . ($res ? mysqli_num_rows($res) : 0) . "\n", FILE_APPEND | LOCK_EX);
if ($res && mysqli_num_rows($res) > 0) {
    $peek = mysqli_fetch_assoc($res);
    @file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " SAMPLE_ROW: " . json_encode($peek) . "\n", FILE_APPEND | LOCK_EX);
    // rewind result pointer so normal processing still sees all rows
    if (function_exists('mysqli_data_seek')) {
        mysqli_data_seek($res, 0);
    }
}
if (!$res) {
    @ob_end_clean();
    echo json_encode(['data' => [], 'error' => 'Query failed: ' . mysqli_error($con)]);
    exit;
}

$rows = [];
while ($r = mysqli_fetch_assoc($res)) {
    // build date label from common date columns
    $dateRaw = $r['tanggal_pengajuan'] ?? $r['created_at'] ?? $r['date'] ?? null;
    if ($dateRaw) {
        $t = strtotime($dateRaw);
        $dateLabel = $t ? (function($d){ return tgl_indo(date('Y-m-d', $d)).' '.date('H:i', $d);})($t) : $dateRaw;
    } else {
        $dateLabel = '-';
    }

    // Normalize status: empty or missing status should be treated as 'pending'
    $rawStatus = trim((string)($r['status'] ?? ''));
    $status = ($rawStatus !== '') ? strtolower($rawStatus) : 'pending';
    $statusHtml = ($status === 'approved') ? '<span class="badge bg-success">Disetujui</span>' : (($status === 'rejected') ? '<span class="badge bg-danger">Ditolak</span>' : '<span class="badge bg-warning">Menunggu</span>');

    if ($type === 'biasa') {
        $name_raw = $r['nama_anggota'] ?? $r['name'] ?? $r['nama'] ?? '';
        $name = (trim((string)$name_raw) !== '') ? $name_raw : '-';
        // Fallback: if name is missing, try to find a pengguna id column and look up the user name
        if ($name === '-') {
            $userIdColCandidates = ['id_pengguna','pengguna_id','anggota_id','id_user','user_id'];
            $foundUid = null;
            foreach ($userIdColCandidates as $c) {
                if (in_array($c, $tblCols, true) && !empty($r[$c])) { $foundUid = $r[$c]; break; }
            }
            if ($foundUid) {
                $uid = intval($foundUid);
                // Safe lookup: SELECT * to avoid querying non-existent columns, and tolerate SQL exceptions
                try {
                    $q = @mysqli_query($con, "SELECT * FROM pengguna WHERE id = " . $uid . " LIMIT 1");
                } catch (Exception $e) {
                    $q = false;
                    @file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " USER_LOOKUP_ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                }
                if ($q && mysqli_num_rows($q) > 0) {
                    $urow = mysqli_fetch_assoc($q);
                    $uname = '';
                    foreach (['nama_lengkap','nama','name','no_hp','nis','email','username'] as $k) {
                        if (!empty($urow[$k]) && trim((string)$urow[$k]) !== '') { $uname = $urow[$k]; break; }
                    }
                    if ($uname !== '') { $name = $uname; }
                    else { $name = 'User #' . $uid; }
                }
            }
        }
        $tenor = intval($r['tenor'] ?? 0);
        $amountRaw = $r['jumlah_pinjaman'] ?? $r['jumlah'] ?? $r['nominal'] ?? 0;
        // keep numeric raw value; client-side will format for display
        $amount = (float)$amountRaw;
        $tujuan = $r['tujuan'] ?? $r['tujuan_penggunaan'] ?? $r['keterangan'] ?? 'Tidak diisi';

        $detailBtn = '<button class="btn btn-sm btn-secondary btn-detail" data-id="' . htmlspecialchars($r['id'], ENT_QUOTES) . '"><i class="fe fe-file"></i> Detail</button>';
        $approveBtn = '<button class="btn btn-sm btn-success btn-approve" data-id="' . htmlspecialchars($r['id'], ENT_QUOTES) . '"><i class="fe fe-check"></i> Setuju</button>';
        $rejectBtn = '<button class="btn btn-sm btn-danger btn-reject" data-id="' . htmlspecialchars($r['id'], ENT_QUOTES) . '"><i class="fe fe-x"></i> Tolak</button>';

        $row = [
            'id' => $r['id'],
            'date' => ($t ? date('Y-m-d H:i:s', $t) : ($dateRaw ? $dateRaw : null)),
            'name' => $name,
            'tenor' => $tenor,
            'amount' => $amount,
            'tujuan' => $tujuan,
            'status' => $status,
            'status_html' => $statusHtml,
            'actions' => ($status === 'pending') ? ($detailBtn . ' ' . $approveBtn . ' ' . $rejectBtn) : ($detailBtn . ' ' . $statusHtml)
        ];
    } else {
        // Map to actual pinjaman_kredit schema (no limit_kredit/bunga/jangka_waktu)
        $name_raw = $r['nama_anggota'] ?? $r['name'] ?? $r['nama'] ?? '';
        $name = (trim((string)$name_raw) !== '') ? $name_raw : '-';
        // Fallback lookup for pengguna id in kredit table
        if ($name === '-') {
            $userIdColCandidates = ['id_pengguna','pengguna_id','anggota_id','id_user','user_id'];
            $foundUid = null;
            foreach ($userIdColCandidates as $c) {
                if (in_array($c, $tblCols, true) && !empty($r[$c])) { $foundUid = $r[$c]; break; }
            }
            if ($foundUid) {
                $uid = intval($foundUid);
                // Safe lookup: SELECT * to avoid querying non-existent columns, and tolerate SQL exceptions
                try {
                    $q = @mysqli_query($con, "SELECT * FROM pengguna WHERE id = " . $uid . " LIMIT 1");
                } catch (Exception $e) {
                    $q = false;
                    @file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " USER_LOOKUP_ERROR: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                }
                if ($q && mysqli_num_rows($q) > 0) {
                    $urow = mysqli_fetch_assoc($q);
                    $uname = '';
                    foreach (['nama_lengkap','nama','name','no_hp','nis','email','username'] as $k) {
                        if (!empty($urow[$k]) && trim((string)$urow[$k]) !== '') { $uname = $urow[$k]; break; }
                    }
                    if ($uname !== '') { $name = $uname; }
                    else { $name = 'User #' . $uid; }
                }
            }
        }

        $nama_barang = $r['nama_barang'] ?? '-';
        $hargaRaw = $r['harga'] ?? 0;
        $dpRaw = $r['dp'] ?? 0;
        $pokokRaw = $r['pokok'] ?? ($hargaRaw - $dpRaw);
        $tenorVal = intval($r['tenor'] ?? 0);
        $cicilanRaw = $r['cicilan_per_bulan'] ?? 0;
        $totalBayarRaw = $r['total_bayar'] ?? (($pokokRaw));
        $foto_db = $r['foto_barang'] ?? null;
        // If DB contains only a filename (no path), expose a proxy URL for admin UI; otherwise do not expose direct file URLs
        $foto_path = null;
        if (!empty($foto_db) && strpos($foto_db, '/') === false && strpos($foto_db, '\\') === false && strpos($foto_db, 'http://') !== 0 && strpos($foto_db, 'https://') !== 0) {
            $foto_path = '/gas/gas_web/login/admin/pinjaman_kredit/foto_barang_image.php?id=' . urlencode($r['id']);
        }

        $row = [
            'id' => $r['id'],
            'date' => ($t ? date('Y-m-d H:i:s', $t) : ($dateRaw ? $dateRaw : null)),
            'name' => $name,
            'nama_barang' => $nama_barang,
            'harga' => (float)$hargaRaw,
            'dp' => (float)$dpRaw,
            'pokok' => (float)$pokokRaw,
            'tenor' => $tenorVal,
            'cicilan_per_bulan' => (float)$cicilanRaw,
            'total_bayar' => (float)$totalBayarRaw,
            'foto_barang' => $foto_path,
            'status' => $status,
            'status_html' => $statusHtml,
            'actions' => ($status === 'pending') ? '<button class="btn btn-sm btn-success btn-approve" data-id="' . htmlspecialchars($r['id'], ENT_QUOTES) . '"><i class="fe fe-check"></i> Terima</button> <button class="btn btn-sm btn-danger btn-reject" data-id="' . htmlspecialchars($r['id'], ENT_QUOTES) . '"><i class="fe fe-x"></i> Tolak</button> <button class="btn btn-sm btn-secondary btn-detail" data-id="' . htmlspecialchars($r['id'], ENT_QUOTES) . '"><i class="fe fe-file"></i> Detail</button>' : '<button class="btn btn-sm btn-secondary btn-detail" data-id="' . htmlspecialchars($r['id'], ENT_QUOTES) . '"><i class="fe fe-file"></i> Detail</button>'
        ];
    }
    $rows[] = $row;
}

@ob_end_clean();
echo json_encode(['data' => $rows]);
exit;
