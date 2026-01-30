<?php

// ========================================================================
// DEBUG: Verify this endpoint is actually called
// ========================================================================
error_log("=== CAIRKAN_TABUNGAN API CALLED ===");
error_log("METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST RAW: " . file_get_contents('php://input'));

// Ensure only JSON output and suppress PHP warnings from appearing in the body
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// Use strict error/reporting settings; we will use an exception handler to respond with JSON on uncaught exceptions
ini_set('display_errors', '0');

// Convert uncaught exceptions into a safe JSON response for the client (single-responder)
set_exception_handler(function($e) {
    // Log uncaught exceptions to PHP error log (centralized monitoring)
    error_log("[cairkan_tabungan] Uncaught exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (!empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
        exit();
    }
    send_json_and_exit(['status' => false, 'message' => 'Internal server error']);
});

require_once 'connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_exit(['status' => false, 'message' => 'Method not allowed. Use POST']);
}

// Convert PHP warnings/notices into exceptions so we return clean JSON on error
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Read incoming raw body (no debug file writes in production)
$rawBody = file_get_contents('php://input');

// DEBUG flag: when enabled, any error response will return a standardized debug JSON
// WARNING: Disabled in production to avoid exposing internal debug markers to clients.
$GLOBALS['DEBUG_BACKEND_FAILURE'] = false;

// Simple helper to send a single JSON response and immediately stop execution
function send_json_and_exit($arr) {
    if (!empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
        // already responded; ensure we don't attempt a second response
        exit();
    }

    // When debugging backend failures, convert any failure response into the
    // standardized debug format requested by ops: {status:false,message:'FAILED_AT_BACKEND',error: <msg>}
    if (!empty($GLOBALS['DEBUG_BACKEND_FAILURE']) && isset($arr['status']) && $arr['status'] === false) {
        $errMsg = '';
        if (isset($arr['error'])) $errMsg = (string)$arr['error'];
        elseif (isset($arr['message'])) $errMsg = (string)$arr['message'];
        $out = ['status' => false, 'message' => 'FAILED_AT_BACKEND', 'error' => $errMsg];
        $payload = json_encode($out);
    } else {
        $payload = json_encode($arr);
    }

    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo $payload;
    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
    exit();
}


// Expected request body (POST):
//   - id_pengguna (string or numeric identifier; e.g. '95' or '081234...')
//   - id_jenis_tabungan (integer id from `jenis_tabungan` table)  <-- REQUIRED
//   - nominal (integer > 0)
// Example payload (application/x-www-form-urlencoded):
//   id_pengguna=95&id_jenis_tabungan=5&nominal=10000
// Responses (examples):
//  - Missing/invalid id_jenis_tabungan => {"status":false,"message":"Jenis tabungan tidak valid"}
//  - id_jenis_tabungan not found      => {"status":false,"message":"Jenis tabungan tidak ditemukan"}
//  - Insufficient funds                => {"status":false,"message":"Saldo tidak mencukupi","available":0}
//  - Success                           => {"status":true,"message":"Tabungan berhasil dicairkan","saldo":10000}

// Use JSON body for request data (Flutter sends Content-Type: application/json)
// Parse raw body and decode JSON; if not valid JSON, fallback to $_POST or parse_str so form-data and x-www-form-urlencoded work
$requestJson = @json_decode($rawBody, true);
if (!is_array($requestJson)) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [cairkan_tabungan] INFO: JSON decode failed or not JSON; falling back to _POST or parse_str\n", FILE_APPEND);
    if (!empty($_POST)) {
        $requestJson = $_POST;
    } else {
        parse_str($rawBody, $parsed);
        $requestJson = is_array($parsed) ? $parsed : [];
    }
}

// Use strict parameter names required by client: id_pengguna, id_jenis_tabungan, nominal
$id_pengguna = isset($requestJson['id_pengguna']) ? trim($requestJson['id_pengguna']) : '';
$id_jenis_tabungan = isset($requestJson['id_jenis_tabungan']) ? trim((string)$requestJson['id_jenis_tabungan']) : '';
$nominal_raw = isset($requestJson['nominal']) ? trim((string)$requestJson['nominal']) : '';
$keterangan = isset($requestJson['keterangan']) ? trim($requestJson['keterangan']) : '';
// id_jenis_tabungan is mandatory and must be an integer id
if ($id_jenis_tabungan === '' || !ctype_digit((string)$id_jenis_tabungan)) {
    send_json_and_exit(['status' => false, 'message' => 'Jenis tabungan tidak valid']);
}
$id_jenis = intval($id_jenis_tabungan);

if (empty($id_pengguna) || $nominal_raw === '') {
    send_json_and_exit(['status' => false, 'message' => 'Parameter id_pengguna and nominal are required']);
}

$jumlah = intval($nominal_raw);
if ($jumlah <= 0) {
    send_json_and_exit(['status' => false, 'message' => 'Nominal harus lebih dari nol']);
} 

// Resolve user id (only allow pengguna.id or pengguna.no_hp)
$user_id = null;
$id_safe = $connect->real_escape_string($id_pengguna);
// If the provided identifier is purely numeric, treat it as pengguna.id
if (ctype_digit((string)$id_pengguna)) {
    $id_int = intval($id_pengguna);
    $stmt = $connect->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $id_int);
        $stmt->execute(); $r = $stmt->get_result();
        if ($r && $r->num_rows > 0) { $u = $r->fetch_assoc(); $user_id = intval($u['id']); }
        $stmt->close();
    }
}
// If not found yet, try lookup by phone number (no_hp) if that column exists
if ($user_id === null) {
    $chk = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'no_hp'");
    if ($chk && $chk->num_rows > 0) {
        $stmt2 = $connect->prepare("SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1");
        if ($stmt2) {
            $stmt2->bind_param('s', $id_safe);
            $stmt2->execute(); $r2 = $stmt2->get_result();
            if ($r2 && $r2->num_rows > 0) { $u2 = $r2->fetch_assoc(); $user_id = intval($u2['id']); }
            $stmt2->close();
        }
    }
}

if ($user_id === null) {
    send_json_and_exit(['status' => false, 'message' => 'Pengguna tidak ditemukan untuk identifier: ' . $id_pengguna]);
} 

// Minimal start log
error_log("[cairkan_tabungan] start user_id={$user_id}");

try {
    // Begin transaction
    $connect->begin_transaction();

    // Lock pengguna row to prevent concurrent balance races (DO NOT read pengguna.saldo here)
    $s = $connect->prepare("SELECT id FROM pengguna WHERE id = ? FOR UPDATE");
    $s->bind_param('i', $user_id);
    if (!$s->execute()) throw new Exception('Gagal query pengguna: ' . $s->error);
    $res = $s->get_result();
    if (!$res || $res->num_rows == 0) { $s->close(); throw new Exception('Pengguna tidak ditemukan saat lock'); }
    $s->close();

    // Validate jenis_tabungan by numeric id (id_jenis must exist in jenis_tabungan)
    $has_jenis = $connect->query("SHOW TABLES LIKE 'jenis_tabungan'");
    if (!($has_jenis && $has_jenis->num_rows > 0)) {
        send_json_and_exit(['status' => false, 'message' => 'Jenis tabungan tidak tersedia pada server']);
    }

    // Check the provided id exists (strict integer id check)
    $chk = $connect->prepare("SELECT id FROM jenis_tabungan WHERE id = ? LIMIT 1");
    $chk->bind_param('i', $id_jenis);
    $chk->execute(); $cres = $chk->get_result(); $chk->close();
    if (!($cres && $cres->num_rows > 0)) {
        // Clear, consistent error when the id isn't found
        send_json_and_exit(['status' => false, 'message' => 'Jenis tabungan tidak ditemukan']);
    }
    $validated_jenis = $id_jenis;

    // Compute available balance per user & jenis by preferring tabungan_masuk (status='berhasil') and falling back to mulai_nabung if necessary
    $total_in = 0; $total_out = 0;
    $available_before = 0;

    // Detect presence of tabungan_masuk (preferred modern table) and mulai_nabung (legacy table)
    $has_tabungan_masuk_table = false;
    $tchk = $connect->query("SHOW TABLES LIKE 'tabungan_masuk'");
    if ($tchk && $tchk->num_rows > 0) $has_tabungan_masuk_table = true;
    $has_mulai_nabung_table = false;
    $tchk2 = $connect->query("SHOW TABLES LIKE 'mulai_nabung'");
    if ($tchk2 && $tchk2->num_rows > 0) $has_mulai_nabung_table = true;

    // Determine mapping column on pengguna (id_tabungan) which some legacy installs use
    $has_id_tabungan_col = false;
    $colchk = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'id_tabungan'");
    if ($colchk && $colchk->num_rows > 0) $has_id_tabungan_col = true;

    if ($has_tabungan_masuk_table) {
        // Inspect columns on tabungan_masuk to pick query strategy
        $has_id_pengguna_col = false; $has_id_jenis_col = false; $has_id_tabungan_in_masuk = false; $has_nomor_hp_in_masuk = false; $has_jenis_str_in_masuk = false; $has_status_in_masuk = false;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'id_pengguna'"); if ($c && $c->num_rows > 0) $has_id_pengguna_col = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'id_jenis_tabungan'"); if ($c && $c->num_rows > 0) $has_id_jenis_col = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'id_tabungan'"); if ($c && $c->num_rows > 0) $has_id_tabungan_in_masuk = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'nomor_hp'"); if ($c && $c->num_rows > 0) $has_nomor_hp_in_masuk = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'jenis_tabungan'"); if ($c && $c->num_rows > 0) $has_jenis_str_in_masuk = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'status'"); if ($c && $c->num_rows > 0) $has_status_in_masuk = true;
        $statusClause = $has_status_in_masuk ? " AND status = 'berhasil'" : '';

        if ($has_id_pengguna_col && $has_id_jenis_col) {
            $sql = "SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClause;
            $stmtIn = $connect->prepare($sql);
            $stmtIn->bind_param('ii', $user_id, $validated_jenis);
            $stmtIn->execute(); $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); $stmtIn->close();
        } elseif ($has_id_tabungan_in_masuk && $has_id_jenis_col) {
            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND m.id_jenis_tabungan = ?" . $statusClause;
            $stmtIn = $connect->prepare($sql);
            $stmtIn->bind_param('ii', $user_id, $validated_jenis);
            $stmtIn->execute(); $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); $stmtIn->close();
        } elseif ($has_nomor_hp_in_masuk && $has_jenis_str_in_masuk) {
            // Resolve nama_jenis and try to match by nomor_hp and jenis string
            $nama_jenis = null;
            $stmtJ = $connect->prepare("SELECT nama_jenis FROM jenis_tabungan WHERE id = ? LIMIT 1");
            $stmtJ->bind_param('i', $validated_jenis);
            if ($stmtJ->execute()) {
                $rj = $stmtJ->get_result(); if ($rj && $rj->num_rows > 0) { $jrow = $rj->fetch_assoc(); $nama_jenis = $jrow['nama_jenis']; }
            }
            if (isset($stmtJ) && is_object($stmtJ)) $stmtJ->close();

            if ($nama_jenis !== null) {
                // fetch user's nomor_hp
                $no_hp = null;
                $chk_nohp = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'no_hp'");
                if ($chk_nohp && $chk_nohp->num_rows > 0) {
                    $sno = $connect->prepare("SELECT no_hp FROM pengguna WHERE id = ? LIMIT 1");
                    $sno->bind_param('i', $user_id);
                    if ($sno->execute()) {
                        $rno = $sno->get_result(); if ($rno && $rno->num_rows > 0) { $nr = $rno->fetch_assoc(); $no_hp = trim($nr['no_hp']); }
                    }
                    if (isset($sno) && is_object($sno)) $sno->close();
                }

                if (!empty($no_hp)) {
                    $sql = "SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk WHERE nomor_hp = ? AND jenis_tabungan = ?" . $statusClause;
                    $stmtIn = $connect->prepare($sql);
                    $stmtIn->bind_param('ss', $no_hp, $nama_jenis);
                    if ($stmtIn->execute()) { $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); }
                    if (isset($stmtIn) && is_object($stmtIn)) $stmtIn->close();
                    if ($total_in <= 0) {
                        $likePattern = '%' . $nama_jenis . '%';
                        $sql2 = "SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk WHERE nomor_hp = ? AND CAST(jenis_tabungan AS CHAR) LIKE ?" . $statusClause;
                        $stmtIn2 = $connect->prepare($sql2);
                        $stmtIn2->bind_param('ss', $no_hp, $likePattern);
                        if ($stmtIn2->execute()) { $rin = $stmtIn2->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); }
                        if (isset($stmtIn2) && is_object($stmtIn2)) $stmtIn2->close();
                    }
                } else {
                    // fallback to matching by jenis string joined by id_tabungan when available
                    try {
                        if ($has_id_tabungan_col) {
                            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND m.jenis_tabungan = ?" . $statusClause;
                            $stmtIn = $connect->prepare($sql);
                            $stmtIn->bind_param('is', $user_id, $nama_jenis);
                        } else {
                            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m WHERE m.id_tabungan = ? AND m.jenis_tabungan = ?" . $statusClause;
                            $stmtIn = $connect->prepare($sql);
                            $stmtIn->bind_param('is', $user_id, $nama_jenis);
                        }
                        $stmtIn->execute(); $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0);
                        if (isset($stmtIn) && is_object($stmtIn)) $stmtIn->close();
                        if ($total_in <= 0) {
                            // try LIKE-based match on jenis_tabungan
                            if ($has_id_tabungan_col) {
                                $sql2 = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND CAST(m.jenis_tabungan AS CHAR) LIKE ?" . $statusClause;
                                $stmtIn2 = $connect->prepare($sql2);
                                $likePattern = '%' . $nama_jenis . '%';
                                $stmtIn2->bind_param('is', $user_id, $likePattern);
                            } else {
                                $sql2 = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m WHERE m.id_tabungan = ? AND m.jenis_tabungan COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci" . $statusClause;
                                $stmtIn2 = $connect->prepare($sql2);
                                $likePattern = '%' . $nama_jenis . '%';
                                $stmtIn2->bind_param('is', $user_id, $likePattern);
                            }
                            $stmtIn2->execute(); $rin = $stmtIn2->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0);
                            if (isset($stmtIn2) && is_object($stmtIn2)) $stmtIn2->close();
                        }
                    } catch (Exception $e) {
                        error_log("[cairkan_tabungan] WARN tabungan_masuk name-match failed: " . $e->getMessage());
                        $total_in = 0;
                    }
                }
            }
        } else {
            // No useful columns found on tabungan_masuk; fallthrough to legacy logic
            $total_in = 0;
        }

    } else {
        // Only compute saldo from tabungan_masuk (status='berhasil'). We do NOT fall back to mulai_nabung.
        // If tabungan_masuk table doesn't exist or doesn't have usable columns, incoming total defaults to zero.
        $total_in = 0;
    }

    // Sum withdrawals from tabungan_keluar for this user & jenis (ONLY approved withdrawals count toward saldo reduction)
    // NOTE: Approved withdrawals have ALREADY been deducted from tabungan_masuk during approval,
    // so we do NOT subtract them again. The available balance is simply what remains in tabungan_masuk.
    $stmtOut = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_out FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ? AND status = 'approved'");
    $stmtOut->bind_param('ii', $user_id, $validated_jenis);
    $stmtOut->execute(); $rout = $stmtOut->get_result(); $rrow2 = $rout->fetch_assoc(); $total_out = floatval($rrow2['total_out'] ?? 0); $stmtOut->close();

    // Available balance is ONLY what remains in tabungan_masuk (approved withdrawals already deducted during approval)
    $available_before = $total_in;

    // Log via error_log per requirement
    error_log("[cairkan_tabungan] total_masuk={$total_in} total_keluar_approved={$total_out} available_saldo={$available_before} nominal={$jumlah}");
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [cairkan_tabungan] DEBUG computed balances user={$user_id} jenis={$validated_jenis} total_in={$total_in} total_out_approved={$total_out} available={$available_before} requested={$jumlah}\n", FILE_APPEND);

    // Load Pengaturan Tabungan (if table exists) and apply validation rules
    $require_status_check = true; // default
    $approval_required_cfg = true; // default
    $min_penarikan_cfg = null;
    $saldo_minimum_cfg = null;
    $notify_on_withdrawal_cfg = true;
    $ctcfg = $connect->query("SHOW TABLES LIKE 'pengaturan_tabungan'");
    if ($ctcfg && $ctcfg->num_rows > 0) {
        $rset = $connect->query("SELECT * FROM pengaturan_tabungan LIMIT 1");
        if ($rset && $rset->num_rows > 0) {
            $srow = $rset->fetch_assoc();
            $require_status_check = isset($srow['require_status_akun']) ? (bool)$srow['require_status_akun'] : true;
            $approval_required_cfg = isset($srow['approval_required']) ? (bool)$srow['approval_required'] : true;
            $min_penarikan_cfg = (isset($srow['min_penarikan']) && $srow['min_penarikan'] !== '') ? floatval($srow['min_penarikan']) : null;
            $saldo_minimum_cfg = (isset($srow['saldo_minimum']) && $srow['saldo_minimum'] !== '') ? floatval($srow['saldo_minimum']) : null;
            $notify_on_withdrawal_cfg = isset($srow['notify_on_withdrawal']) ? (bool)$srow['notify_on_withdrawal'] : true;
        }
    }

    // If configured, enforce minimal nominal per withdrawal
    if ($min_penarikan_cfg !== null && $jumlah < $min_penarikan_cfg) {
        $connect->rollback();
        send_json_and_exit(['status' => false, 'message' => 'Nominal penarikan terlalu kecil. Minimum: ' . number_format($min_penarikan_cfg,0,',','.')]);
    }

    // If configured, enforce minimal remaining saldo after pencairan
    if ($saldo_minimum_cfg !== null && ($available_before - $jumlah) < $saldo_minimum_cfg) {
        $connect->rollback();
        send_json_and_exit(['status' => false, 'message' => 'Penarikan ini akan membuat saldo di bawah minimum yang disyaratkan']);
    }

    // If required, check user's account status (only allow approved/active accounts)
    if ($require_status_check) {
        // Build a tolerant select for status
        $selParts = [];
        $rcolsp = $connect->query("SHOW COLUMNS FROM pengguna");
        if ($rcolsp) {
            $colsTmp = [];
            while ($c = $rcolsp->fetch_assoc()) $colsTmp[] = $c['Field'];
            if (in_array('status', $colsTmp)) $selParts[] = 'status';
            if (in_array('status_akun', $colsTmp)) $selParts[] = 'status_akun';
        }
        $selCols = !empty($selParts) ? ('COALESCE(' . implode(', ', $selParts) . ", '') as status_val") : "'' as status_val";
        $stmtS = $connect->prepare("SELECT {$selCols} FROM pengguna WHERE id = ? LIMIT 1");
        if ($stmtS) {
            $stmtS->bind_param('i', $user_id);
            $stmtS->execute(); $rs = $stmtS->get_result();
            if ($rs && $rs->num_rows > 0) {
                $st = strtolower(trim($rs->fetch_assoc()['status_val'] ?? ''));
                $ok_status = (strpos($st, 'aktif') !== false) || (strpos($st, 'verifik') !== false) || trim($st) === '1' || $st === 'active' || $st === 'approved' || $st === '';
                if (!$ok_status) {
                    $connect->rollback();
                    send_json_and_exit(['status' => false, 'message' => 'Akun pengguna belum disetujui atau tidak aktif']);
                }
            }
            $stmtS->close();
        }
    }

    // Provide clients with awareness whether this installation requires admin approval
    // (We do not auto-approve here; auto-approve behavior may be implemented later.)
    $client_approval_required = $approval_required_cfg;

    // Validate using computed per-jenis balance
    if ($available_before < $jumlah) {
        $connect->rollback();
        // Do NOT insert or update anything; return a clear validation message per requirement
        send_json_and_exit(['status' => false, 'message' => 'Saldo tabungan tidak mencukupi', 'available' => intval($available_before)]);
    }

    // Insert withdrawal ledger (prefer insert_ledger_keluar helper to support legacy schemas)
    include_once __DIR__ . '/../login/function/ledger_helpers.php';
    $okInsert = false;
    if (function_exists('insert_ledger_keluar')) {
        $pendingKeterangan = 'pending: ' . ($keterangan ?: 'Cairkan tabungan (via API)');
        $okInsert = insert_ledger_keluar($connect, $user_id, $jumlah, $pendingKeterangan, $validated_jenis ?? 1, null);
        if (!$okInsert) throw new Exception('Gagal mencatat penarikan ke ledger (insert_ledger_keluar)');
    } else {
        // direct insert as last resort
        $created = date('Y-m-d H:i:s');
        $stmt = $connect->prepare("INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception('Gagal prepare insert tabungan_keluar: ' . $connect->error);
        $id_j = intval($validated_jenis ?? 1);
        // bind types: i(user_id), i(id_jenis), s(jumlah), s(keterangan), s(created), s(updated)
        $stmt->bind_param('iissss', $user_id, $id_j, $jumlah, $keterangan, $created, $created);
        if (!$stmt->execute()) { $stmt->close(); throw new Exception('Gagal insert tabungan_keluar: ' . $stmt->error); }
        $stmt->close();
    }

    // Insert succeeded. For safety and to enforce admin approval workflow, we mark this
    // withdrawal request as PENDING and DO NOT update `pengguna.saldo` here. Admin must approve
    // the pending request before any wallet (pengguna.saldo) changes are applied.
    // We encode the pending semantics into the keterangan field when the table lacks a status column.

    // If insert_ledger_keluar was used above, we passed a keterangan that may already include
    // sufficient context; however to be explicit we will update the inserted row's keterangan
    // to include the 'pending' marker when table supports tabungan_keluar.
    $has_tabungan_keluar_table = false;
    $ct = $connect->query("SHOW TABLES LIKE 'tabungan_keluar'");
    if ($ct && $ct->num_rows > 0) $has_tabungan_keluar_table = true;

    if ($has_tabungan_keluar_table) {
        // If insert_ledger_keluar used a descriptive keterangan, append pending marker only if missing
        $pending_marker = 'pending';
        $stmtLast = $connect->prepare("SELECT id, keterangan FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        if ($stmtLast) {
            $stmtLast->bind_param('ii', $user_id, $validated_jenis);
            $stmtLast->execute(); $rl = $stmtLast->get_result();
            if ($rl && $rl->num_rows > 0) {
                $rowLast = $rl->fetch_assoc();
                $lastId = intval($rowLast['id']);
                $lastK = trim($rowLast['keterangan'] ?? '');
                if (stripos($lastK, $pending_marker) === false) {
                    $nk = trim(($lastK ? $lastK . ' - ' : '') . 'pending');
                    $up = $connect->prepare("UPDATE tabungan_keluar SET keterangan = ? WHERE id = ?");
                    if ($up) {
                        $up->bind_param('si', $nk, $lastId);
                        $up->execute(); $up->close();
                    }
                }
            }
            $stmtLast->close();
        }
    } else {
        // If tabungan_keluar is not present, we still want to avoid changing pengguna.saldo here.
        // The successful insert likely went into legacy `tabungan` or `transaksi`. For safety, we
        // will not perform any pengguna.saldo mutation and rely on admin flow for further steps.
    }

    // ========================================================================
    // FAIL-HARD NOTIFICATION CREATION - BEFORE COMMIT
    // This ensures transaction is rolled back if notification fails
    // ========================================================================
    
    // LOAD NOTIFICATION HELPERS FIRST - fail hard if not available
    require_once 'notif_helper.php';

    // Get inserted tabungan_keluar ID for notification reference
    error_log("[cairkan_tabungan] DEBUG: sebelum cari tab_keluar_id");
    $tab_keluar_id = null;
    $stmtGetId = $connect->prepare("SELECT id FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ? ORDER BY id DESC LIMIT 1");
    if (!$stmtGetId) throw new Exception("Gagal prepare SELECT tab_keluar_id: " . $connect->error);
    $stmtGetId->bind_param('ii', $user_id, $validated_jenis);
    if (!$stmtGetId->execute()) throw new Exception("Gagal execute SELECT tab_keluar_id: " . $stmtGetId->error);
    $rGetId = $stmtGetId->get_result();
    if (!$rGetId) throw new Exception("Gagal get result tab_keluar_id: " . $stmtGetId->error);
    if ($rGetId->num_rows > 0) {
        $rowId = $rGetId->fetch_assoc();
        $tab_keluar_id = intval($rowId['id']);
    }
    $stmtGetId->close();
    
    if ($tab_keluar_id === null) {
        throw new Exception("Gagal mendapatkan tabungan_keluar ID setelah INSERT");
    }
    error_log("[cairkan_tabungan] DEBUG: ditemukan tab_keluar_id={$tab_keluar_id}");
    
    // Get jenis name for notification message
    error_log("[cairkan_tabungan] DEBUG: sebelum cari jenis_label");
    $jenis_label = null;
    $sj = $connect->prepare("SELECT nama_jenis FROM jenis_tabungan WHERE id = ? LIMIT 1");
    if (!$sj) throw new Exception("Gagal prepare SELECT jenis_label: " . $connect->error);
    $sj->bind_param('i', $validated_jenis);
    if (!$sj->execute()) throw new Exception("Gagal execute SELECT jenis_label: " . $sj->error);
    $rj = $sj->get_result();
    if (!$rj) throw new Exception("Gagal get result jenis_label: " . $sj->error);
    if ($rj->num_rows > 0) {
        $jrow = $rj->fetch_assoc();
        $jenis_label = $jrow['nama_jenis'] ?? null;
    }
    $sj->close();
    
    if ($jenis_label === null) {
        throw new Exception("Gagal mendapatkan nama jenis tabungan");
    }
    error_log("[cairkan_tabungan] DEBUG: ditemukan jenis_label={$jenis_label}");
    
    // Call withdrawal pending notification helper - FAIL HARD on any error
    error_log("[cairkan_tabungan] DEBUG: sebelum create_withdrawal_pending_notification");
    error_log("[cairkan_tabungan] DEBUG: calling with user_id={$user_id}, jenis_name={$jenis_label}, amount={$jumlah}, tab_keluar_id={$tab_keluar_id}");
    
    $nid = create_withdrawal_pending_notification($connect, $user_id, $jenis_label, $jumlah, $tab_keluar_id);
    
    error_log("[cairkan_tabungan] DEBUG: create_withdrawal_pending_notification returned: " . ($nid === false ? 'FALSE' : $nid));
    
    if ($nid === false) {
        throw new Exception("Gagal membuat notifikasi withdrawal: create_withdrawal_pending_notification returned FALSE");
    }
    error_log("[cairkan_tabungan] DEBUG: sesudah create_withdrawal_pending_notification, nid={$nid}");
    
    // Only commit AFTER successful notification creation
    if (!$connect->commit()) {
        throw new Exception("Gagal commit transaction: " . $connect->error);
    }
    error_log("[cairkan_tabungan] INFO: transaction committed successfully with notification nid={$nid} for user={$user_id}");

    // Compute response values. Because this request is only a PENDING request we do NOT change
    // the user's wallet balance. For UI clarity we return the pre-withdraw available per-jenis
    // balance and the current pengguna.saldo.
    $available_after = $available_before; // unchanged until approved
    $saldo_after = 0;
    $sstmt = $connect->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
    if ($sstmt) {
        $sstmt->bind_param('i', $user_id);
        $sstmt->execute();
        $rs = $sstmt->get_result();
        if ($rs && $rs->num_rows > 0) {
            $saldo_after = intval($rs->fetch_assoc()['saldo']);
        }
        $sstmt->close();
    }

    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [cairkan_tabungan] SUCCESS user={$user_id} jenis={$validated_jenis} amount={$jumlah} tab_keluar_id={$tab_keluar_id} notification_id={$nid}\n", FILE_APPEND);

    // Respond with pending status — do NOT reveal internal DB IDs
    send_json_and_exit(['status' => true, 'message' => 'Permintaan pencairan diajukan, menunggu persetujuan admin', 'available' => intval($available_after), 'saldo' => $saldo_after, 'approval_required' => (bool)$client_approval_required]);

    // Example failure responses used in this API:
    // { "status": false, "message": "Saldo tidak mencukupi", "available": 0 }
    // { "status": false, "message": "Jenis tabungan tidak valid" }

} catch (Exception $e) {
    // Log full details for server operators
    error_log("[cairkan_tabungan] FAILED: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    try { $connect->rollback(); } catch (Exception $_) {}

    // Only expose safe validation messages to client. For unexpected errors, return a generic message.
    $msg = $e->getMessage();
    // Treat explicit validation messages and fail-hard errors as safe to return to client
    if (!preg_match('/^(Pengguna|Jenis tabungan|Nominal|Saldo tabungan tidak mencukupi|Saldo tidak mencukupi|Parameter|Gagal|Notifikasi)/i', $msg)) {
        $msg = 'Gagal mencairkan tabungan. Silakan coba lagi atau hubungi admin.';
    }

    send_json_and_exit(['status' => false, 'message' => $msg]);
}

