<?php
<?php

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

// Simple helper to send a single JSON response and immediately stop execution
function send_json_and_exit($arr) {
    if (!empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
        // already responded; ensure we don't attempt a second response
        exit();
    }
    $payload = json_encode($arr);
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
        $has_id_pengguna_col = false; $has_id_jenis_col = false; $has_id_tabungan_in_masuk = false; $has_nomor_hp_in_masuk = false; $has_jenis_str_in_masuk = false;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'id_pengguna'"); if ($c && $c->num_rows > 0) $has_id_pengguna_col = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'id_jenis_tabungan'"); if ($c && $c->num_rows > 0) $has_id_jenis_col = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'id_tabungan'"); if ($c && $c->num_rows > 0) $has_id_tabungan_in_masuk = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'nomor_hp'"); if ($c && $c->num_rows > 0) $has_nomor_hp_in_masuk = true;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'jenis_tabungan'"); if ($c && $c->num_rows > 0) $has_jenis_str_in_masuk = true;

        if ($has_id_pengguna_col && $has_id_jenis_col) {
            $stmtIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ? AND status = 'berhasil'");
            $stmtIn->bind_param('ii', $user_id, $validated_jenis);
            $stmtIn->execute(); $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); $stmtIn->close();
        } elseif ($has_id_tabungan_in_masuk && $has_id_jenis_col) {
            $stmtIn = $connect->prepare("SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND m.id_jenis_tabungan = ? AND m.status = 'berhasil'");
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
                    $stmtIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk WHERE nomor_hp = ? AND jenis_tabungan = ? AND status = 'berhasil'");
                    $stmtIn->bind_param('ss', $no_hp, $nama_jenis);
                    if ($stmtIn->execute()) { $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); }
                    if (isset($stmtIn) && is_object($stmtIn)) $stmtIn->close();
                    if ($total_in <= 0) {
                        $likePattern = '%' . $nama_jenis . '%';
                        $stmtIn2 = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk WHERE nomor_hp = ? AND CAST(jenis_tabungan AS CHAR) LIKE ? AND status = 'berhasil'");
                        $stmtIn2->bind_param('ss', $no_hp, $likePattern);
                        if ($stmtIn2->execute()) { $rin = $stmtIn2->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); }
                        if (isset($stmtIn2) && is_object($stmtIn2)) $stmtIn2->close();
                    }
                } else {
                    // fallback to matching by jenis string joined by id_tabungan when available
                    try {
                        if ($has_id_tabungan_col) {
                            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND m.jenis_tabungan = ? AND m.status = 'berhasil'";
                            $stmtIn = $connect->prepare($sql);
                            $stmtIn->bind_param('is', $user_id, $nama_jenis);
                        } else {
                            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m WHERE m.id_tabungan = ? AND m.jenis_tabungan = ? AND m.status = 'berhasil'";
                            $stmtIn = $connect->prepare($sql);
                            $stmtIn->bind_param('is', $user_id, $nama_jenis);
                        }
                        $stmtIn->execute(); $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0);
                        if (isset($stmtIn) && is_object($stmtIn)) $stmtIn->close();
                        if ($total_in <= 0) {
                            // try LIKE-based match on jenis_tabungan
                            if ($has_id_tabungan_col) {
                                $sql2 = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND CAST(m.jenis_tabungan AS CHAR) LIKE ? AND m.status = 'berhasil'";
                                $stmtIn2 = $connect->prepare($sql2);
                                $likePattern = '%' . $nama_jenis . '%';
                                $stmtIn2->bind_param('is', $user_id, $likePattern);
                            } else {
                                $sql2 = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m WHERE m.id_tabungan = ? AND m.jenis_tabungan COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci AND m.status = 'berhasil'";
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

    } elseif ($has_mulai_nabung_table) {
        // Legacy fallback: existing mulai_nabung logic (status='berhasil')
        // Check if mulai_nabung contains explicit id_jenis_tabungan
        $chk_id_jenis = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'id_jenis_tabungan'");
        $has_id_jenis_in_mulai = ($chk_id_jenis && $chk_id_jenis->num_rows > 0);

        if ($has_id_jenis_in_mulai) {
            if ($has_id_tabungan_col) {
                $stmtIn = $connect->prepare("SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM mulai_nabung m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND m.id_jenis_tabungan = ? AND m.status = 'berhasil'");
                $stmtIn->bind_param('ii', $user_id, $validated_jenis);
            } else {
                $stmtIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_in FROM mulai_nabung WHERE id_tabungan = ? AND id_jenis_tabungan = ? AND status = 'berhasil'");
                $stmtIn->bind_param('ii', $user_id, $validated_jenis);
            }
            $stmtIn->execute(); $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0); $stmtIn->close();
        } else {
            // Fallback: mulai_nabung records jenis as a string and associates entries by nomor_hp.
            $nama_jenis = null;
            $stmtJ = $connect->prepare("SELECT nama_jenis FROM jenis_tabungan WHERE id = ? LIMIT 1");
            $stmtJ->bind_param('i', $validated_jenis);
            if ($stmtJ->execute()) {
                $rj = $stmtJ->get_result();
                if ($rj && $rj->num_rows > 0) {
                    $jrow = $rj->fetch_assoc();
                    $nama_jenis = $jrow['nama_jenis'];
                }
            }
            if (isset($stmtJ) && is_object($stmtJ)) $stmtJ->close();

            $total_in = 0;
            if ($nama_jenis !== null) {
                // Fetch user's nomor_hp (preferred) and use it to match mulai_nabung.nomor_hp
                $no_hp = null;
                $chk_nohp = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'no_hp'");
                if ($chk_nohp && $chk_nohp->num_rows > 0) {
                    $sno = $connect->prepare("SELECT no_hp FROM pengguna WHERE id = ? LIMIT 1");
                    $sno->bind_param('i', $user_id);
                    if ($sno->execute()) {
                        $rno = $sno->get_result();
                        if ($rno && $rno->num_rows > 0) {
                            $nr = $rno->fetch_assoc();
                            $no_hp = trim($nr['no_hp']);
                        }
                    }
                    if (isset($sno) && is_object($sno)) $sno->close();
                }

                if (!empty($no_hp)) {
                    $stmtIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_in FROM mulai_nabung WHERE nomor_hp = ? AND jenis_tabungan = ? AND status = 'berhasil'");
                    $stmtIn->bind_param('ss', $no_hp, $nama_jenis);
                    if ($stmtIn->execute()) {
                        $rin = $stmtIn->get_result();
                        $rrow = $rin->fetch_assoc();
                        $total_in = floatval($rrow['total_in'] ?? 0);
                    }
                    if (isset($stmtIn) && is_object($stmtIn)) $stmtIn->close();
                    if ($total_in <= 0) {
                        $likePattern = '%' . $nama_jenis . '%';
                        $stmtIn2 = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_in FROM mulai_nabung WHERE nomor_hp = ? AND CAST(jenis_tabungan AS CHAR) LIKE ? AND status = 'berhasil'");
                        $stmtIn2->bind_param('ss', $no_hp, $likePattern);
                        if ($stmtIn2->execute()) {
                            $rin = $stmtIn2->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0);
                        }
                        if (isset($stmtIn2) && is_object($stmtIn2)) $stmtIn2->close();
                    }
                } else {
                    try {
                        if ($has_id_tabungan_col) {
                            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM mulai_nabung m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND m.jenis_tabungan = ? AND m.status = 'berhasil'";
                            $stmtIn = $connect->prepare($sql);
                            $stmtIn->bind_param('is', $user_id, $nama_jenis);
                        } else {
                            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM mulai_nabung m WHERE m.id_tabungan = ? AND m.jenis_tabungan = ? AND m.status = 'berhasil'";
                            $stmtIn = $connect->prepare($sql);
                            $stmtIn->bind_param('is', $user_id, $nama_jenis);
                        }
                        $stmtIn->execute(); $rin = $stmtIn->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0);
                        if (isset($stmtIn) && is_object($stmtIn)) $stmtIn->close();
                        if ($total_in <= 0) {
                            if ($has_id_tabungan_col) {
                                $sql2 = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM mulai_nabung m JOIN pengguna p ON p.id_tabungan = m.id_tabungan WHERE p.id = ? AND CAST(m.jenis_tabungan AS CHAR) LIKE ? AND m.status = 'berhasil'";
                                $stmtIn2 = $connect->prepare($sql2);
                                $likePattern = '%' . $nama_jenis . '%';
                                $stmtIn2->bind_param('is', $user_id, $likePattern);
                            } else {
                                $sql2 = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM mulai_nabung m WHERE m.id_tabungan = ? AND m.jenis_tabungan COLLATE utf8mb4_unicode_ci LIKE ? COLLATE utf8mb4_unicode_ci AND m.status = 'berhasil'";
                                $stmtIn2 = $connect->prepare($sql2);
                                $likePattern = '%' . $nama_jenis . '%';
                                $stmtIn2->bind_param('is', $user_id, $likePattern);
                            }
                            $stmtIn2->execute(); $rin = $stmtIn2->get_result(); $rrow = $rin->fetch_assoc(); $total_in = floatval($rrow['total_in'] ?? 0);
                            if (isset($stmtIn2) && is_object($stmtIn2)) $stmtIn2->close();
                        }
                    } catch (Exception $e) {
                        error_log("[cairkan_tabungan] WARN fallback mulai_nabung name-match failed: " . $e->getMessage());
                        $total_in = 0;
                    }
                }
            }
        } else {
            // jenis id not found; treat incoming as zero
            $total_in = 0;
        }

    } else {
        // No tabungan_masuk or mulai_nabung present; assume zero incoming
        $total_in = 0;
    }

?>