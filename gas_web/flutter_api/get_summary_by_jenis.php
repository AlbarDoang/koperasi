<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
// Log basic debug for web requests to diagnose intermittent Internal Server Error
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis debug] REQUEST_METHOD=" . ($_SERVER['REQUEST_METHOD'] ?? 'N/A') . " SAPI=" . php_sapi_name() . " POST=" . json_encode($_POST) . "\n", FILE_APPEND);

// Prevent PHP warnings/HTML from breaking JSON responses; buffer unexpected output
ini_set('display_errors', '0');
ob_start();
register_shutdown_function(function() {
    $buf = ob_get_clean();
    if (trim($buf) !== '') {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis] Unexpected output: " . $buf . "\n", FILE_APPEND);
    }
    $err = error_get_last();
    if ($err) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis fatal] " . print_r($err, true) . "\n", FILE_APPEND);
        // If we haven't emitted JSON yet, return a safe JSON error payload so clients don't get empty/HTML responses
        if (empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
            header('Content-Type: application/json; charset=utf-8');
            $fallback = ['success' => false, 'message' => 'Internal server error', 'data' => [], 'meta' => ['total' => 0]];
            echo json_encode($fallback);
            $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
        }
    }
});

require_once 'connection.php';

// Standard response shape: always include success, data (array) and meta.total (int)
$payload = ['success' => true, 'data' => [], 'meta' => ['total' => 0]];

$id_tabungan = isset($_POST['id_tabungan']) ? trim($_POST['id_tabungan']) : (isset($_GET['id_tabungan']) ? trim($_GET['id_tabungan']) : '');
if (empty($id_tabungan)) {
    $payload['success'] = false;
    $payload['message'] = 'Parameter id_tabungan required';
    $payload['data'] = [];
    $payload['meta'] = ['total' => 0];
    echo json_encode($payload);
    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
    exit();
}

$data = [];


// Wrap the main logic - errors are handled via shutdown handler and careful checks

// Resolve the provided id_tabungan (which is often an external identifier or username)
// to the internal pengguna.id where possible. This ensures queries against
// tabungan_masuk/tabungan_keluar (which store id_pengguna) use the correct id.
$user_id = null;
$id_safe = $connect->real_escape_string($id_tabungan);
// Not all schemas include certain columns; build WHERE clause only with existing columns
$existingCols = [];
$possibleCols = ['id_tabungan', 'username', 'no_hp', 'id', 'id_pengguna'];
foreach ($possibleCols as $col) {
    $chk = $connect->query("SHOW COLUMNS FROM pengguna LIKE '$col'");
    if ($chk && $chk->num_rows > 0) $existingCols[] = $col;
}
$whereParts = [];
foreach ($existingCols as $col) {
    if ($col === 'id') {
        $whereParts[] = "$col = '$id_safe'";
    } else {
        $whereParts[] = "$col = '$id_safe'";
    }
}
$where = implode(' OR ', $whereParts);
if (!empty($where)) {
    $p = $connect->query("SELECT id FROM pengguna WHERE $where LIMIT 1");
    if ($p && $p->num_rows > 0) {
        $urow = $p->fetch_assoc();
        $user_id = intval($urow['id']);
    }
}


// Prefer using tabungan_masuk / tabungan_keluar and jenis_tabungan if available
$checkJenis = $connect->query("SHOW TABLES LIKE 'jenis_tabungan'");
if ($checkJenis && $checkJenis->num_rows > 0) {
    // Determine the correct name column (some schemas use `nama`, others `nama_jenis`)
    $nameCol = 'nama';
    $chkName = $connect->query("SHOW COLUMNS FROM jenis_tabungan LIKE 'nama'");
    if (!($chkName && $chkName->num_rows > 0)) {
        $nameCol = 'nama_jenis';
    }
    // Get all jenis
    $jenisRes = $connect->query("SELECT id, $nameCol AS nama FROM jenis_tabungan ORDER BY id");
    while ($j = $jenisRes->fetch_assoc()) {
        $jid = intval($j['id']);
        $jname = $j['nama'];

            // Use resolved internal user id when possible; fallback to provided id (legacy) only for best-effort
        if ($user_id !== null) {
            $qIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
            $qIn->bind_param('ii', $user_id, $jid);
            $qIn->execute();
            $rIn = $qIn->get_result()->fetch_assoc();
            $totalIn = intval($rIn['total'] ?? 0);

            // Respect approval workflow: if tabungan_keluar supports `status`, only count APPROVED rows
            $has_status_col = false;
            $chks = $connect->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'status'"); if ($chks && $chks->num_rows > 0) $has_status_col = true;
            $statusClause = $has_status_col ? " AND status = 'approved'" : "";
            $sqlOut = "SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClause;
            $qOut = $connect->prepare($sqlOut);
            $qOut->bind_param('ii', $user_id, $jid);
            $qOut->execute();
            $rOut = $qOut->get_result()->fetch_assoc();
            $totalOut = intval($rOut['total'] ?? 0);
        } else {
            // No internal mapping found; attempt legacy behavior (may return zero)
            $qIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?");
            $qIn->bind_param('si', $id_tabungan, $jid);
            $qIn->execute();
            $rIn = $qIn->get_result()->fetch_assoc();
            $totalIn = intval($rIn['total'] ?? 0);

            // Apply same approval-aware logic for legacy mapping
            $has_status_col = false;
            $chks = $connect->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'status'"); if ($chks && $chks->num_rows > 0) $has_status_col = true;
            $statusClause = $has_status_col ? " AND status = 'approved'" : "";
            $sqlOut = "SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClause;
            $qOut = $connect->prepare($sqlOut);
            $qOut->bind_param('si', $id_tabungan, $jid);
            $qOut->execute();
            $rOut = $qOut->get_result()->fetch_assoc();
            $totalOut = intval($rOut['total'] ?? 0);
        }

        $balance = $totalIn;
        // Do NOT subtract $totalOut because approved withdrawals are already deducted from tabungan_masuk
        // during the approval process. Only show what remains in tabungan_masuk.
        // Ensure we don't return negative balances
        if ($balance < 0) $balance = 0;
        $data[] = [
            'id' => $jid,
            'id_jenis_tabungan' => $jid,
            'jenis' => $jname,
            'balance' => $balance,
            'total_masuk' => $totalIn,
            'total_keluar' => $totalOut
        ];
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis] INFO: jenis id $jid, nama='$jname', balance=$balance\n", FILE_APPEND);
    }
} else {
    // Fallback: aggregate mulai_nabung by jenis_tabungan string
    // Only include entries that were approved (status = 'berhasil') so we only show credited amounts
    $stmt = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) as total, jenis_tabungan FROM mulai_nabung WHERE id_tabungan = ? AND status = 'berhasil' GROUP BY jenis_tabungan ORDER BY jenis_tabungan");
    $stmt->bind_param('s', $id_tabungan);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $jenis = $row['jenis_tabungan'] ?: 'Tabungan Reguler';
        $data[] = ['id' => null, 'id_jenis_tabungan' => null, 'jenis' => $jenis, 'balance' => intval($row['total']), 'source' => 'mulai_nabung'];
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis] WARNING: fallback mulai_nabung jenis '$jenis' has no id\n", FILE_APPEND);
    }
} 

// If we have no data (or all zeros) from tabungan_masuk/mulai_nabung, try a
// fallback using the `mulai_nabung` table (approved entries) first and then
// fall back to `pengguna` when nothing is available.
$hasUsefulData = false;
foreach ($data as $d) {
    if (isset($d['balance']) && intval($d['balance']) !== 0) { $hasUsefulData = true; break; }
}
if (!$hasUsefulData) {
    // Try to aggregate approved mulai_nabung entries grouped by jenis_tabungan
    $bn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total, jenis_tabungan FROM mulai_nabung WHERE id_tabungan = ? AND status = 'berhasil' GROUP BY jenis_tabungan ORDER BY jenis_tabungan");
    if ($bn) {
        $bn->bind_param('s', $id_tabungan);
        $bn->execute();
        $bres = $bn->get_result();
        $found = false;
        while ($brow = $bres->fetch_assoc()) {
            $found = true;
            $jenis_label = $brow['jenis_tabungan'] ?: 'Tabungan Reguler';
            // If jenis_tabungan is numeric id, try to map to actual name
            if (ctype_digit((string)$brow['jenis_tabungan'])) {
                $jid = intval($brow['jenis_tabungan']);
                $jr = $connect->prepare("SELECT nama FROM jenis_tabungan WHERE id = ? LIMIT 1");
                if ($jr) {
                    $jr->bind_param('i', $jid);
                    $jr->execute();
                    $rjr = $jr->get_result();
                    if ($rjr && $rjr->num_rows > 0) {
                        $jenis_label = $rjr->fetch_assoc()['nama'];
                    }
                    $jr->close();
                }
            }
            $data[] = ['id' => null, 'jenis' => $jenis_label, 'balance' => intval($brow['total']), 'source' => 'mulai_nabung'];
        }
        $bn->close();
        if ($found) {
            $hasUsefulData = true;
        }
    }

    // If still nothing, fallback to pengguna.saldo as a last resort
    if (!$hasUsefulData) {
        $id_safe = $connect->real_escape_string($id_tabungan);
        $p = $connect->query("SELECT id, saldo, jenis_tabungan, id_jenis_tabungan, id_tabungan FROM pengguna WHERE id = '$id_safe' OR id_tabungan = '$id_safe' LIMIT 1");
        if ($p && $p->num_rows > 0) {
            $u = $p->fetch_assoc();
            $peng_saldo = intval($u['saldo'] ?? 0);
            $jenis_name = 'Tabungan Reguler';
            // Prefer id_jenis_tabungan if present
            if (!empty($u['id_jenis_tabungan'])) {
                $jid = intval($u['id_jenis_tabungan']);
                $jr = $connect->prepare("SELECT nama FROM jenis_tabungan WHERE id = ? LIMIT 1");
                if ($jr) {
                    $jr->bind_param('i', $jid);
                    $jr->execute();
                    $resjr = $jr->get_result();
                    if ($resjr && $resjr->num_rows > 0) {
                        $jenis_name = $resjr->fetch_assoc()['nama'];
                    }
                    $jr->close();
                }
            } else if (!empty($u['jenis_tabungan'])) {
                $jenis_name = $u['jenis_tabungan'];
            }
            // Replace $data with single-entry using pengguna.saldo so UI shows a value
            $data[] = ['id' => $u['id'] ?? null, 'id_jenis_tabungan' => $u['id_jenis_tabungan'] ?? null, 'jenis' => $jenis_name, 'balance' => $peng_saldo, 'source' => 'pengguna'];
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis] INFO: pengguna fallback jenis='$jenis_name' id_jenis_tabungan={$u['id_jenis_tabungan']}\n", FILE_APPEND);
        }
    }
}

// Clean any buffered output (log for investigation) and then return JSON
$buf = ob_get_clean();
if (trim($buf) !== '') {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis] Buffered output before JSON: " . $buf . "\n", FILE_APPEND);
}
// Compute total balance across all jenis and provide a jenis list for dropdowns
$total = 0;
foreach ($data as $d) { $total += intval($d['balance'] ?? 0); }
$jenis_list = [];
$checkJenis2 = $connect->query("SHOW TABLES LIKE 'jenis_tabungan'");
if ($checkJenis2 && $checkJenis2->num_rows > 0) {
    // Determine available name column
    $nameCol = null;
    $chk1 = $connect->query("SHOW COLUMNS FROM jenis_tabungan LIKE 'nama'");
    $chk2 = $connect->query("SHOW COLUMNS FROM jenis_tabungan LIKE 'nama_jenis'");
    if ($chk1 && $chk1->num_rows > 0) {
        $nameCol = 'nama';
    } elseif ($chk2 && $chk2->num_rows > 0) {
        $nameCol = 'nama_jenis';
    }
    if ($nameCol !== null) {
        $jr = $connect->query("SELECT id, $nameCol AS nama FROM jenis_tabungan ORDER BY id");
    } else {
        // no name columns available, still return ids
        $jr = $connect->query("SELECT id, '' AS nama FROM jenis_tabungan ORDER BY id");
    }
    if ($jr) {
        while ($row = $jr->fetch_assoc()) {
            $jenis_list[] = ['id' => intval($row['id']), 'nama' => $row['nama']];
        }
    }
}

// Build standard payload
$payload['success'] = true;
$payload['data'] = $data;
$payload['meta'] = ['total' => $total];
// Maintain backward compatibility for existing clients
$payload['total_tabungan'] = $total;
$payload['jenis_list'] = $jenis_list;

$jsonOut = json_encode($payload);
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_summary_by_jenis] Emitting JSON: " . ($jsonOut === false ? '<json-error>' : substr($jsonOut, 0, 1000)) . "\n", FILE_APPEND);
$GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
echo $jsonOut;
exit();


