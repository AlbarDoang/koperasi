<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_history_by_jenis debug] REQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . " SAPI=" . php_sapi_name() . " POST=" . json_encode($_POST) . "\n", FILE_APPEND);

// Prevent PHP warnings/HTML from breaking JSON responses; buffer unexpected output
ini_set('display_errors', '0');
ob_start();
register_shutdown_function(function() {
    $buf = ob_get_clean();
    if (trim($buf) !== '') {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_history_by_jenis] Unexpected output: " . $buf . "\n", FILE_APPEND);
    }
    $err = error_get_last();
    if ($err) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_history_by_jenis fatal] " . print_r($err, true) . "\n", FILE_APPEND);
        if (empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
            header('Content-Type: application/json; charset=utf-8');
            $fallback = ['success' => false, 'message' => 'Internal server error', 'data' => [], 'meta' => ['total' => 0]];
            echo json_encode($fallback);
            $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
        }
    }
});

require_once 'connection.php';

$id_tabungan = isset($_POST['id_tabungan']) ? trim($_POST['id_tabungan']) : '';
$jenis = isset($_POST['jenis']) ? trim($_POST['jenis']) : '';
// optional filters
$periode = isset($_POST['periode']) ? trim($_POST['periode']) : '30'; // default: 30 days
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 200;

$payload = ['success' => true, 'data' => [], 'meta' => ['total' => 0]];
if (empty($id_tabungan) || $jenis === '') {
    $payload['success'] = false;
    $payload['message'] = 'Parameters id_tabungan and jenis required';
    $payload['data'] = [];
    $payload['meta'] = ['total' => 0];
    echo json_encode($payload);
    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
    exit();
}

// compute since timestamp according to periode
$since = null;
if ($periode === 'today') {
    $since = date('Y-m-d') . ' 00:00:00';
} elseif (ctype_digit((string)$periode) && intval($periode) > 0) {
    $since = date('Y-m-d H:i:s', strtotime("-" . intval($periode) . " days"));
}

// Wrap main processing so any errors become structured JSON
try {


// Resolve to internal pengguna.id if possible so we can query tabungan_masuk and tabungan_keluar
$user_id = null;
$id_safe = $connect->real_escape_string($id_tabungan);
$existingCols = [];
$possibleCols = ['id_tabungan', 'username', 'no_hp', 'id', 'id_pengguna'];
foreach ($possibleCols as $col) {
    $chk = $connect->query("SHOW COLUMNS FROM pengguna LIKE '$col'");
    if ($chk && $chk->num_rows > 0) $existingCols[] = $col;
}
$whereParts = [];
foreach ($existingCols as $col) {
    $whereParts[] = "$col = '$id_safe'";
}
if (!empty($whereParts)) {
    $p = $connect->query("SELECT id FROM pengguna WHERE " . implode(' OR ', $whereParts) . " LIMIT 1");
    if ($p && $p->num_rows > 0) {
        $user_id = intval($p->fetch_assoc()['id']);
    }
}

$items = [];

// Try numeric jenis (id_jenis_tabungan)
if (ctype_digit($jenis)) {
    $idJenis = intval($jenis);
    // If we resolved an internal user_id, query tabungan tables; otherwise attempt multiple fallbacks
    if ($user_id !== null) {
        // get masuk
        $stmt = $connect->prepare("SELECT jumlah, keterangan, created_at AS tanggal FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY created_at DESC");
        $stmt->bind_param('ii', $user_id, $idJenis);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $title = $r['keterangan'] ?? 'Setoran';
            $lct = strtolower($title);
            $itemType = 'masuk';
            if (stripos($lct, 'topup') !== false || stripos($lct, 'mulai_nabung') !== false || stripos($lct, 'setoran') !== false) {
                $itemType = 'topup';
            }
            $items[] = ['date' => $r['tanggal'], 'title' => $title, 'amount' => intval($r['jumlah']), 'type' => $itemType];
        }
        $stmt->close();

        // get keluar
        $stmt = $connect->prepare("SELECT jumlah, keterangan, created_at AS tanggal FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY created_at DESC");
        $stmt->bind_param('ii', $user_id, $idJenis);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $title = $r['keterangan'] ?? 'Penarikan';
            $lct = strtolower($title);
            $itemType = 'keluar';
            if (stripos($lct, 'cairkan') !== false || stripos($lct, 'penarikan') !== false) {
                $itemType = 'cairkan';
            }
            $items[] = ['date' => $r['tanggal'], 'title' => $title, 'amount' => -intval($r['jumlah']), 'type' => $itemType];
        }
        $stmt->close();

            // Also include transaksi rows for this user (loans, pencairan, transfers, repayments)
            $txCols = [];
            $rtc = $connect->query("SHOW COLUMNS FROM transaksi");
            if ($rtc) { while ($rc = $rtc->fetch_assoc()) $txCols[] = $rc['Field']; }
            $txWhere = [];
            if (!empty($user_id)) {
                if (in_array('id_pengguna', $txCols)) $txWhere[] = "id_pengguna = " . intval($user_id);
                if (in_array('id_pengguna', $txCols)) $txWhere[] = "id_pengguna = " . intval($user_id);
            }
            if (in_array('id_tabungan', $txCols)) $txWhere[] = "id_tabungan = '" . $connect->real_escape_string($id_tabungan) . "'";
            if (!empty($txWhere)) {
                $sqlt = "SELECT jenis_transaksi, jumlah, jumlah_masuk, jumlah_keluar, keterangan, tanggal FROM transaksi WHERE (" . implode(' OR ', $txWhere) . ") ORDER BY tanggal DESC LIMIT 200";
                $rt = $connect->query($sqlt);
                if ($rt) {
                    while ($rr = $rt->fetch_assoc()) {
                        $amt = 0;
                        if (isset($rr['jumlah']) && $rr['jumlah'] !== null) $amt = intval($rr['jumlah']);
                        else $amt = intval($rr['jumlah_masuk'] ?? 0) - intval($rr['jumlah_keluar'] ?? 0);
                        $title = $rr['keterangan'] ?? ($rr['jenis_transaksi'] ?? 'Transaksi');
                        $tt = strtolower($rr['jenis_transaksi'] ?? '');
                        // Map known jenis_transaksi to app-friendly type values
                        $map = [
                            'transfer_masuk' => 'transfer',
                            'transfer_keluar' => 'transfer',
                            'pencairan_approved' => 'cairkan',
                            'pencairan' => 'cairkan',
                            'topup' => 'topup',
                            'setoran' => 'topup',
                            'pinjaman_kredit' => 'pinjaman',
                            'pinjaman' => 'pinjaman',
                        ];
                        $itemType = $amt >= 0 ? 'masuk' : 'keluar';
                        if (isset($map[$tt])) $itemType = $map[$tt];
                        // Fallback: infer from title text
                        $lt = strtolower($title);
                        if (stripos($lt, 'transfer') !== false) $itemType = 'transfer';
                        if (stripos($lt, 'pencairan') !== false || stripos($lt, 'pencairan disetujui') !== false) $itemType = 'cairkan';
                        if (stripos($lt, 'topup') !== false || stripos($lt, 'setoran') !== false) $itemType = 'topup';

                        $items[] = ['date' => $rr['tanggal'] ?? null, 'title' => $title, 'amount' => $amt, 'type' => $itemType];
                    }
                }
            }

        // If nothing found in tabungan_masuk/keluar, try approved mulai_nabung records for this jenis
        if (empty($items)) {
            $bm = $connect->prepare("SELECT jumlah, created_at FROM mulai_nabung WHERE id_tabungan = ? AND status = 'berhasil' AND jenis_tabungan = ? ORDER BY created_at DESC");
            if ($bm) {
                // use original id_tabungan string to match legacy mulai_nabung.id_tabungan
                $bm->bind_param('ss', $id_tabungan, $idJenis);
                $bm->execute();
                $bres = $bm->get_result();
                while ($br = $bres->fetch_assoc()) {
                    $items[] = ['date' => $br['created_at'], 'title' => 'Topup', 'amount' => intval($br['jumlah']), 'type' => 'masuk'];
                }
                $bm->close();
            }
        }

    } else {
        // No internal mapping -> try legacy queries using id_tabungan as provided (string id)
        // Attempt to query `tabungan_masuk` / `tabungan_keluar` with id_pengguna = id_tabungan (legacy behavior)
        $stmt = $connect->prepare("SELECT jumlah, keterangan, created_at AS tanggal FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY created_at DESC");
        if ($stmt) {
            $stmt->bind_param('si', $id_tabungan, $idJenis);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $items[] = ['date' => $r['tanggal'], 'title' => $r['keterangan'] ?? 'Setoran', 'amount' => intval($r['jumlah']), 'type' => 'masuk'];
            }
            $stmt->close();
        }
        $stmt = $connect->prepare("SELECT jumlah, keterangan, created_at AS tanggal FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY created_at DESC");
        if ($stmt) {
            $stmt->bind_param('si', $id_tabungan, $idJenis);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $items[] = ['date' => $r['tanggal'], 'title' => $r['keterangan'] ?? 'Penarikan', 'amount' => -intval($r['jumlah']), 'type' => 'keluar'];
            }
            $stmt->close();
        }

        // If still empty, try to return approved mulai_nabung records for this jenis
        if (empty($items)) {
            $bm = $connect->prepare("SELECT jumlah, created_at FROM mulai_nabung WHERE id_tabungan = ? AND status = 'berhasil' AND jenis_tabungan = ? ORDER BY created_at DESC");
            if ($bm) {
                $bm->bind_param('ss', $id_tabungan, $idJenis);
                $bm->execute();
                $bres = $bm->get_result();
                while ($br = $bres->fetch_assoc()) {
                    $items[] = ['date' => $br['created_at'], 'title' => 'Topup', 'amount' => intval($br['jumlah']), 'type' => 'masuk'];
                }
                $bm->close();
            }
        }
    }
} else {
    // jenis given as name => try to map to jenis_tabungan name first
    $nameCol = null;
    $chk1 = $connect->query("SHOW COLUMNS FROM jenis_tabungan LIKE 'nama'");
    $chk2 = $connect->query("SHOW COLUMNS FROM jenis_tabungan LIKE 'nama_jenis'");
    if ($chk1 && $chk1->num_rows > 0) {
        $nameCol = 'nama';
    } elseif ($chk2 && $chk2->num_rows > 0) {
        $nameCol = 'nama_jenis';
    }

    if ($nameCol !== null) {
        $jstmt = $connect->prepare("SELECT id FROM jenis_tabungan WHERE $nameCol = ? LIMIT 1");
        if ($jstmt) {
            $jstmt->bind_param('s', $jenis);
            $jstmt->execute();
            $jr = $jstmt->get_result();
            if ($jr && $jr->num_rows > 0) {
                $row = $jr->fetch_assoc();
                $idJenis = intval($row['id']);
                // re-run numeric branch: prefer resolved $user_id if present, otherwise use legacy id_tabungan
                $who = ($user_id !== null) ? $user_id : $id_tabungan;
                $stmt = $connect->prepare("SELECT jumlah, keterangan, created_at AS tanggal FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY created_at DESC");
                if ($stmt) {
                    if ($user_id !== null) {
                        $stmt->bind_param('ii', $who, $idJenis);
                    } else {
                        $stmt->bind_param('si', $who, $idJenis);
                    }
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $items[] = ['date' => $r['tanggal'], 'title' => $r['keterangan'] ?? 'Setoran', 'amount' => intval($r['jumlah']), 'type' => 'masuk'];
                    }
                    $stmt->close();
                }
                $stmt = $connect->prepare("SELECT jumlah, keterangan, created_at AS tanggal FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY created_at DESC");
                if ($stmt) {
                    if ($user_id !== null) {
                        $stmt->bind_param('ii', $who, $idJenis);
                    } else {
                        $stmt->bind_param('si', $who, $idJenis);
                    }
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) {
                        $items[] = ['date' => $r['tanggal'], 'title' => $r['keterangan'] ?? 'Penarikan', 'amount' => -intval($r['jumlah']), 'type' => 'keluar'];
                    }
                    $stmt->close();
                }
            } else {
                // Fallback: search mulai_nabung / transaksi where jenis name appears in jenis_tabungan / kegiatan
                $stmt = $connect->prepare("SELECT jumlah, jenis_tabungan, created_at FROM mulai_nabung WHERE id_tabungan = ? AND jenis_tabungan = ? AND status = 'berhasil' ORDER BY created_at DESC");
                $stmt->bind_param('ss', $id_tabungan, $jenis);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $items[] = ['date' => $r['created_at'] ?? $r['tanggal'], 'title' => $r['jenis_tabungan'] ?? 'Topup', 'amount' => intval($r['jumlah']), 'type' => 'masuk'];
                }
                // Also try transaksi table where kegiatan matches jenis
                $stmt = $connect->prepare("SELECT jumlah_masuk AS nominal_m, jumlah_keluar AS nominal_k, kegiatan, tanggal FROM transaksi WHERE id_tabungan = ? AND kegiatan = ? ORDER BY tanggal DESC");
                $stmt->bind_param('ss', $id_tabungan, $jenis);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $amt = intval($r['nominal_m'] ?? 0) - intval($r['nominal_k'] ?? 0);
                    $items[] = ['date' => $r['tanggal'], 'title' => $r['kegiatan'] ?? '-', 'amount' => $amt, 'type' => $amt >= 0 ? 'masuk' : 'keluar'];
                }
                // Additionally allow callers to pass a transaction type filter (tx_type) to include transaksi rows where jenis_transaksi matches
                if (!empty($_POST['tx_type'])) {
                    $txType = trim($_POST['tx_type']);
                    $stmt2 = $connect->prepare("SELECT jumlah AS jumlah, jenis_transaksi, keterangan, tanggal FROM transaksi WHERE id_tabungan = ? AND jenis_transaksi = ? ORDER BY tanggal DESC");
                    if ($stmt2) {
                        $stmt2->bind_param('ss', $id_tabungan, $txType);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        while ($r2 = $res2->fetch_assoc()) {
                            $amt = intval($r2['jumlah'] ?? 0);
                            $items[] = ['date' => $r2['tanggal'], 'title' => $r2['keterangan'] ?? ($r2['jenis_transaksi'] ?? '-'), 'amount' => $amt, 'type' => $amt >= 0 ? 'masuk' : 'keluar'];
                        }
                        $stmt2->close();
                    }
                }
            }
        }
    } else {
        // name column not present; fallback to searching mulai_nabung / transaksi as best-effort
        $stmt = $connect->prepare("SELECT jumlah, jenis_tabungan, created_at FROM mulai_nabung WHERE id_tabungan = ? AND jenis_tabungan = ? AND status = 'berhasil' ORDER BY created_at DESC");
        $stmt->bind_param('ss', $id_tabungan, $jenis);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $items[] = ['date' => $r['created_at'] ?? $r['tanggal'], 'title' => $r['jenis_tabungan'] ?? 'Topup', 'amount' => intval($r['jumlah']), 'type' => 'masuk'];
        }
        // Also try transaksi table where kegiatan matches jenis
        $stmt = $connect->prepare("SELECT jumlah_masuk AS nominal_m, jumlah_keluar AS nominal_k, kegiatan, tanggal FROM transaksi WHERE id_tabungan = ? AND kegiatan = ? ORDER BY tanggal DESC");
        $stmt->bind_param('ss', $id_tabungan, $jenis);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $amt = intval($r['nominal_m'] ?? 0) - intval($r['nominal_k'] ?? 0);
            $items[] = ['date' => $r['tanggal'], 'title' => $r['kegiatan'] ?? '-', 'amount' => $amt, 'type' => $amt >= 0 ? 'masuk' : 'keluar'];
        }
    }
}

// Merge transaksi rows for resolved user / id_tabungan (dedupe)
$seen = [];
foreach ($items as $it) { $seen[md5(($it['date'] ?? '') . '|' . ($it['title'] ?? '') . '|' . ($it['amount'] ?? ''))] = true; }
$txCols = [];
$rtc = $connect->query("SHOW COLUMNS FROM transaksi");
if ($rtc) { while ($rcc = $rtc->fetch_assoc()) $txCols[] = $rcc['Field']; }
$txWhere = [];
if (!empty($user_id)) {
    if (in_array('id_pengguna', $txCols)) $txWhere[] = "id_pengguna = " . intval($user_id);
    if (in_array('id_pengguna', $txCols)) $txWhere[] = "id_pengguna = " . intval($user_id);
}
if (in_array('id_tabungan', $txCols) && !empty($id_tabungan)) $txWhere[] = "id_tabungan = '" . $connect->real_escape_string($id_tabungan) . "'";
if (!empty($txWhere)) {
    $sqlt = "SELECT jenis_transaksi, jumlah, jumlah_masuk, jumlah_keluar, keterangan, tanggal FROM transaksi WHERE (" . implode(' OR ', $txWhere) . ") ORDER BY tanggal DESC LIMIT 500";
    $rt = $connect->query($sqlt);
    if ($rt) {
        while ($rr = $rt->fetch_assoc()) {
            $amt = isset($rr['jumlah']) ? intval($rr['jumlah']) : (intval($rr['jumlah_masuk'] ?? 0) - intval($rr['jumlah_keluar'] ?? 0));
            $date = $rr['tanggal'] ?? null;
            $title = $rr['keterangan'] ?? ($rr['jenis_transaksi'] ?? 'Transaksi');
            $key = md5(($date ?? '') . '|' . $title . '|' . $amt);
            if (!isset($seen[$key])) { $items[] = ['date' => $date, 'title' => $title, 'amount' => $amt, 'type' => $amt >= 0 ? 'masuk' : 'keluar']; $seen[$key] = true; }
        }
    }
}

// Apply date filter (if requested)
if ($since !== null) {
    $items = array_values(array_filter($items, function($it) use ($since) {
        return strtotime($it['date']) >= strtotime($since);
    }));
}
// Record total count before applying limit
$total_count = count($items);
// Sort items by date descending
usort($items, function($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});
if ($limit > 0) {
    $items = array_slice($items, 0, $limit);
}

// Clean any buffered output (log for investigation) and then return JSON
$buf = ob_get_clean();
if (trim($buf) !== '') {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_history_by_jenis] Buffered output before JSON: " . $buf . "\n", FILE_APPEND);
}
// Build standard payload
$payload['success'] = true;
$payload['data'] = $items;
$payload['meta'] = ['total' => $total_count];

$json = json_encode($payload);
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_history_by_jenis] Emitting JSON (len=" . strlen($json) . "): " . substr($json, 0, 2000) . "\n", FILE_APPEND);
$GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
echo $json;
exit();

} catch (Throwable $e) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_history_by_jenis error] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    $payload['success'] = false;
    $payload['message'] = 'Internal server error: ' . $e->getMessage();
    $payload['data'] = [];
    $payload['meta'] = ['total' => 0];
    $json = json_encode($payload);
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_history_by_jenis] Emitting JSON error: " . substr($json, 0, 2000) . "\n", FILE_APPEND);
    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
    echo $json;
    exit();
}


