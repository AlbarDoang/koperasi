<?php
// Set headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error handling
error_reporting(E_ALL);
// Allow debugging output when called with ?__debug=1 (temporary)
$debug = false;
if (isset($_GET['__debug']) && $_GET['__debug'] == '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    $debug = true;
} else {
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors and return JSON
register_shutdown_function(function () use (&$debug) {
    $err = error_get_last();
    if ($err !== null) {
        // Only treat true fatal errors as 500 responses (avoid overriding valid responses due to warnings/notices)
        $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if (!in_array($err['type'], $fatal_types)) {
            error_log('[API][login] non-fatal shutdown error ignored: ' . print_r($err, true));
            return;
        }
        http_response_code(500);
        $msg = $debug ? ($err['message'] . ' in ' . $err['file'] . ' on line ' . $err['line']) : 'Internal Server Error';
        error_log('[API][login] fatal error: ' . print_r($err, true));
        echo json_encode(["success" => false, "message" => $msg]);
    }
});

// Exception handler
set_exception_handler(function ($ex) use (&$debug) {
    http_response_code(500);
    error_log('[API][login] exception: ' . $ex->__toString());
    $msg = $debug ? $ex->getMessage() : 'Internal Server Error';
    echo json_encode(["success" => false, "message" => $msg]);
    exit();
});

// Include database connection
require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/storage_config.php';

// Local file logger for API errors (append)
function api_log_error($message) {
    $logPath = dirname(__DIR__) . '/flutter_api/logs/login_errors.log';
    $entry = json_encode(['ts' => date('c'), 'message' => $message]) . PHP_EOL;
    @file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
}

// Get database connection
$con = getConnection();

if (!$con) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit();
}

// Get input data: accept JSON body or form-encoded (from mobile app)
$rawInput = file_get_contents('php://input');
$inputJson = json_decode($rawInput, true);

// Prefer JSON keys if provided
$no_hp = '';
$password = '';
if (is_array($inputJson)) {
    // Accept several possible key names used by different clients
    $no_hp = trim($inputJson['no_hp'] ?? $inputJson['nohp'] ?? $inputJson['username'] ?? '');
    $password = $inputJson['password'] ?? $inputJson['pass'] ?? $inputJson['kata_sandi'] ?? '';
}

// If not JSON or missing, check form-encoded POST (http.post body)
if (empty($no_hp) && !empty($_POST)) {
    $no_hp = trim($_POST['nohp'] ?? $_POST['no_hp'] ?? $_POST['username'] ?? '');
    $password = $_POST['pass'] ?? $_POST['password'] ?? $_POST['kata_sandi'] ?? '';
}

// Validate input
if (empty($no_hp) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Nomor HP dan password wajib diisi"
    ]);
    exit();
}

// Normalize phone number for lookup (local 08 and international 62 for backwards compatibility)
require_once dirname(__DIR__) . '/flutter_api/helpers.php';
$no_hp_local = sanitizePhone($no_hp);
if (empty($no_hp_local)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format nomor HP tidak valid"
    ]);
    exit();
}
$no_hp_int = phone_to_international62($no_hp);

// Query user from pengguna table (try local first but allow international form for legacy data)
$sql = "SELECT * FROM pengguna WHERE no_hp = ? OR no_hp = ? LIMIT 1";

// Debug log for prepared SQL
if ($debug) error_log('[API][login] Raw input no_hp: ' . $no_hp . ' no_hp_local: ' . (isset($no_hp_local) ? $no_hp_local : ''));
$stmt = $con->prepare($sql);
if (!$stmt) {
    $err = $con->error ?? 'Database error during prepare';
    error_log('[API][login] prepare failed: ' . $err);
    api_log_error('prepare failed: ' . $err);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $debug ? 'Database error: ' . $err : 'Database error'
    ]);
    exit();
}

$stmt->bind_param('ss', $no_hp_local, $no_hp_int);
if (!$stmt->execute()) {
    $err = $stmt->error ?? 'Unknown execute error';
    error_log('[API][login] execute failed: ' . $err);
    api_log_error('execute failed: ' . $err);
    $stmt->close();
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $debug ? 'Query failed: ' . $err : 'Query failed'
    ]);
    exit();
}

$result = $stmt->get_result();

// User not found
if ($result->num_rows === 0) {
    $stmt->close();
    // More specific response: phone not registered
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Nomor ponsel belum terdaftar. Silakan daftar untuk membuat akun atau periksa kembali nomor Anda."
    ]);
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['kata_sandi'])) {
    http_response_code(401);
    echo json_encode([
        "success" => false,
        "message" => "Kata sandi tidak sesuai. Silakan coba lagi atau gunakan fitur 'Lupa Kata Sandi' jika Anda lupa kata sandi."
    ]);

    exit();
}

// Check account status according to new flow
$statusLower = strtolower(trim($user['status_akun']));
// Load koperasi setting (default: prevent pending login)
$prevent_pending_login = true;
$rtab = $con->query("SHOW TABLES LIKE 'pengaturan_koperasi'");
if ($rtab && $rtab->num_rows > 0) {
    $rpk = $con->query("SELECT prevent_pending_login FROM pengaturan_koperasi LIMIT 1");
    if ($rpk && $rpk->num_rows > 0) {
        $cfg = $rpk->fetch_assoc(); $prevent_pending_login = isset($cfg['prevent_pending_login']) ? (bool)$cfg['prevent_pending_login'] : true;
    }
}

if ($statusLower === 'rejected') {
    // Account rejected by admin - professional error message
    $msg = "Akun Anda ditolak dan tidak dapat login. ";
    // Try to get rejection reason: prefer pengguna.rejection_reason column, fallback to verifikasi_pengguna_rejects
    $reason = null;
    $hasCol = $con->query("SHOW COLUMNS FROM pengguna LIKE 'rejection_reason'");
    if ($hasCol && $hasCol->num_rows > 0) {
        $q = $con->prepare("SELECT rejection_reason FROM pengguna WHERE id = ? LIMIT 1");
        if ($q) {
            $q->bind_param('i', $user['id']);
            $q->execute();
            $resq = $q->get_result();
            if ($resq && $resq->num_rows > 0) {
                $rrow = $resq->fetch_assoc();
                $reason = $rrow['rejection_reason'] ?? null;
            }
            $q->close();
        }
    }
    if (empty($reason)) {
        $rq = $con->prepare("SELECT alasan FROM verifikasi_pengguna_rejects WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 1");
        if ($rq) {
            $rq->bind_param('i', $user['id']);
            $rq->execute();
            $resrq = $rq->get_result();
            if ($resrq && $resrq->num_rows > 0) {
                $rrow = $resrq->fetch_assoc();
                $reason = $rrow['alasan'] ?? null;
            }
            $rq->close();
        }
    }
    if (!empty($reason)) {
        $msg .= "Alasan: " . $reason . ". ";
    }
    $msg .= "Silakan hubungi administrator untuk informasi lebih lanjut.";
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => $msg,
        "notif_type" => "error"
    ]);
    exit();
} else if ($statusLower === 'pending') {
    // Account pending admin verification
    if ($prevent_pending_login) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Akun Anda sedang menunggu verifikasi dari admin. Silakan tunggu beberapa saat.",
            "notif_type" => "warning"
        ]);
        exit();
    }
    // else: allow pending users to login (limited access)
} else if ($statusLower === 'submitted') {
    // Account submitted but not yet activated - user needs to complete activation
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => "Akun Anda belum diaktivasi. Silakan lakukan aktivasi terlebih dahulu untuk dapat login.",
        "notif_type" => "warning"
    ]);
    exit();
} else if ($statusLower !== 'approved') {
    // Any other unrecognized status
    $msg = "Akun Anda tidak dapat login saat ini. Status: " . ucfirst($statusLower) . ". Silakan hubungi administrator.";
    http_response_code(403);
    echo json_encode([
        "success" => false,
        "message" => $msg,
        "notif_type" => "error"
    ]);
    exit();
}

// If account is approved, check whether PIN has been set and whether user has tabungan record
$needs_set_pin = empty($user['pin']) || (isset($has_tabungan) && $has_tabungan === false);
// Determine suggested next page: setpin when needs pin or missing tabungan; otherwise dashboard
$next_page = $needs_set_pin ? 'setpin' : 'dashboard';

// Compute saldo from transactions (transaksi / tabungan) for correctness
$saldo_db = 0.0;
$stmt_saldo = $con->prepare("SELECT saldo FROM pengguna WHERE no_hp = ? LIMIT 1");
if ($stmt_saldo) {
    $stmt_saldo->bind_param('s', $user['no_hp']);
    if (!$stmt_saldo->execute()) {
        $err = $stmt_saldo->error;
        error_log('[API][login] saldo select failed: ' . $err);
        api_log_error('saldo select failed: ' . $err);
    }
    $res_saldo = $stmt_saldo->get_result();
    if ($res_saldo && $res_saldo->num_rows > 0) {
        $row_saldo = $res_saldo->fetch_assoc();
        $saldo_db = (double)($row_saldo['saldo'] ?? 0);
    }
    $stmt_saldo->close();
}

// Prefer id_tabungan (NIS) when available for querying transaksi
$id_tabungan_val = $user['id_tabungan'] ?? ($user['nis'] ?? '');
$saldo_calculated = null;
// Prefer 'transaksi' ledger when available; use helper that detects schema
require_once __DIR__ . '/helpers.php';
if (!empty($id_tabungan_val) && function_exists('safe_sum_transaksi')) {
    $safeTrx = safe_sum_transaksi($con, $id_tabungan_val);
    if ($safeTrx !== null) {
        $saldo_calculated = floatval($safeTrx['saldo']);
    }
}

// Fallback: calculate from tabungan table if transaksi missing
$has_tabungan = true; // default to true if tabungan table missing (backwards compatible)
if ($saldo_calculated === null) {
    $check_tabungan = $con->query("SHOW TABLES LIKE 'tabungan'");
    if ($check_tabungan && $check_tabungan->num_rows > 0) {
        $has_tabungan = false; // we'll verify presence
        // Determine which column to use in WHERE clause
        $whereClause = '';
        if (!empty($user['id_pengguna'])) {
            $whereClause = "id_pengguna='" . $con->real_escape_string($user['id_pengguna']) . "'";
        } else if (!empty($user['id'])) {
            $check_col = $con->query("SHOW COLUMNS FROM tabungan LIKE 'id_pengguna'");
            if ($check_col && $check_col->num_rows > 0) {
                $whereClause = "id_pengguna='" . $con->real_escape_string($user['id']) . "'";
            }
        }
        if (empty($whereClause) && !empty($id_tabungan_val)) {
            $whereClause = "id_tabungan='" . $con->real_escape_string($id_tabungan_val) . "'";
        }
        if (!empty($whereClause)) {
            // Check if any tabungan rows exist for this user
            $sql_exists = "SELECT 1 FROM tabungan WHERE " . $whereClause . " LIMIT 1";
            $res_exists = $con->query($sql_exists);
            if ($res_exists && $res_exists->num_rows > 0) {
                $has_tabungan = true;
                // Calculate saldo
                $sql_tab = "SELECT COALESCE(SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE 0 END), 0) as total_masuk, COALESCE(SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END), 0) as total_keluar FROM tabungan WHERE " . $whereClause;
                if ($debug) {
                    api_log_error('Calculating from tabungan SQL: ' . $sql_tab);
                }
                $res_tab = $con->query($sql_tab);
                if ($res_tab === false) {
                    error_log('[API][login] tabungan query failed: ' . $con->error . ' SQL: ' . $sql_tab);
                    api_log_error('tabungan query failed: ' . $con->error . ' SQL: ' . $sql_tab);
                }
                if ($res_tab && $res_tab->num_rows > 0) {
                    $row_tab = $res_tab->fetch_assoc();
                    $saldo_calculated = floatval($row_tab['total_masuk']) - floatval($row_tab['total_keluar']);
                }
            } else {
                $has_tabungan = false;
            }
        }
    }
}

// If still null, fallback to DB value
if ($saldo_calculated === null) $saldo_calculated = $saldo_db;

// Update pengguna.saldo in DB if different
if (intval($saldo_calculated) !== intval($saldo_db)) {
    $idCol = isset($user['id']) ? 'id' : (isset($user['id_pengguna']) ? 'id_pengguna' : null);
    if ($idCol !== null) {
        $safeId = $con->real_escape_string($user[$idCol]);
        $updated = $con->query("UPDATE pengguna SET saldo='" . intval($saldo_calculated) . "' WHERE $idCol='" . $safeId . "' LIMIT 1");
            if ($updated === false) {
            error_log('[API][login] update saldo failed: ' . $con->error);
            api_log_error('update saldo failed: ' . $con->error);
        }
        $saldo_db = $saldo_calculated; // reflect to return
    }
}

// final saldo to return
$saldo = $saldo_db;

// Build profile photo URL via proxy (short-lived signed URL). Do not expose direct file paths.
$foto_url = null;
if (!empty($user['foto_profil'])) {
    $filename = $user['foto_profil'];
    // Prefer per-user profile storage location
    $per_user_candidate = PROFILE_STORAGE_PHOTO . ($user['id'] ?? '') . DIRECTORY_SEPARATOR . $filename;
    $profile_candidate = PROFILE_STORAGE_PHOTO . $filename; // flat legacy storage
    $legacy_candidate = dirname(__DIR__) . '/uploads/foto_profil/' . $filename;
    if (file_exists($per_user_candidate) || file_exists($profile_candidate) || file_exists($legacy_candidate)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (php_sapi_name() === 'cli' ? 'localhost' : $_SERVER['SERVER_NAME']);
        $exp = time() + 300;
        $payload = $user['id'] . ':' . $filename . ':' . $exp;
        $sig = hash_hmac('sha256', $payload, PROFILE_IMAGE_SECRET);
        $foto_url = $protocol . $host . '/gas/gas_web/login/user/foto_profil_image.php?id=' . urlencode($user['id']) . '&exp=' . $exp . '&sig=' . $sig;
    } else {
        $foto_url = null;
    }
}

// Success response
// Optionally include a web redirect if the client requests it (useful for browser logins)
$extra = [];
$wantWeb = false;
if ((isset($_REQUEST['client']) && $_REQUEST['client'] === 'web') || (isset($_REQUEST['web']) && $_REQUEST['web'] === '1')) {
    $wantWeb = true;
}
if ($wantWeb) {
    // compute base URL for this installation
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base = $protocol . '://' . $host . '/gas/gas_web';
    if ($needs_set_pin) {
        $extra['redirect'] = $base . '/set_pin.php?no_hp=' . rawurlencode($user['no_hp']);
        $extra['set_pin_url'] = $base . '/set_pin.php?no_hp=' . rawurlencode($user['no_hp']);
    } else {
        // default web landing page for users (profile page)
        $extra['redirect'] = $base . '/profil.php';
        $extra['landing_url'] = $base . '/profil.php';
    }
}

http_response_code(200);
$response = [
    "success" => true,
    "message" => "Login berhasil",
    "needs_set_pin" => $needs_set_pin ? true : false,
    "next_page" => $next_page,
    "user" => [
        "id" => $user['id'],
        "no_hp" => $user['no_hp'],
        "nama" => $user['nama_lengkap'],
        "nama_lengkap" => $user['nama_lengkap'],
        "alamat" => $user['alamat_domisili'],
        "tanggal_lahir" => $user['tanggal_lahir'],
        "status_akun" => $user['status_akun'],
        "created_at" => $user['created_at'],
        // Return calculated saldo (not DB cached value) so client shows realtime value
        "saldo" => intval($saldo_calculated),
        "foto" => $foto_url,
        "saldo_calculated" => intval($saldo_calculated)
    ]
];
if (!empty($extra)) {
    $response = array_merge($response, $extra);
}

echo json_encode($response);
exit();


