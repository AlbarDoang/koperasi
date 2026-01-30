<?php
// api/pinjaman/ajukan.php
// Strict Bearer-token only endpoint. PHP native, mysqli (procedural).
// Returns JSON only. No sessions, no PDO, no debug output.

declare(strict_types=1);

// Suppress warnings in responses
ini_set('display_errors', '0');
error_reporting(0);

// Headers
header('Content-Type: application/json; charset=utf-8');
// Echo origin where present so browser preflight succeeds; allow common headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " PRELIGHT OPTIONS from origin: " . ($origin ?: 'none') . " headers: " . json_encode(function_exists('getallheaders') ? getallheaders() : []) . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(204);
    exit;
} 

// Only POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'message' => 'Method Not Allowed. Gunakan POST.']);
    exit;
}

// Require Content-Type: application/json
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos((string)$contentType, 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Content-Type harus application/json']);
    exit;
}

// Legacy/duplicate handler removed — the main try/catch flow below handles validation, auth, and insertion.
// If you still see successful responses without rows in DB, run the checks described below and re-run the request.


// Disable PHP notices/warnings in responses
ini_set('display_errors', '0');
error_reporting(0);

// JSON + CORS headers (keep at top)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => false, 'message' => 'Method Not Allowed. Gunakan POST.']);
        exit;
    }

    // Require Content-Type: application/json
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos((string)$contentType, 'application/json') === false) {
        // Ensure CORS on error responses too
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        } else {
            header('Access-Control-Allow-Origin: *');
        }
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
        header('Access-Control-Allow-Methods: POST, OPTIONS');

        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Content-Type harus application/json']);
        exit;
    }

    // Read raw JSON body from php://input and sanitize (strict JSON required)
    $raw = file_get_contents('php://input');
    if ($raw !== null) {
        $raw = trim((string)$raw);
        $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
    }

    // Require JSON body for this endpoint
    if ($raw === null || $raw === '') {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Body JSON kosong. Harap kirim Content-Type: application/json dan body JSON.']);
        exit;
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $err = json_last_error_msg();
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'JSON decode error: ' . $err]);
        exit;
    }

    $data = $decoded;

    // Normalize inputs
    $jumlah_pinjaman = $data['jumlah_pinjaman'] ?? null;
    $tenor = $data['tenor'] ?? null;
    $tujuan_penggunaan = $data['tujuan_penggunaan'] ?? null;

    // Basic presence validation
    if ($jumlah_pinjaman === null || $jumlah_pinjaman === '' || !is_numeric($jumlah_pinjaman)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Field "jumlah_pinjaman" wajib dan harus berupa angka.']);
        exit;
    }

    if ($tenor === null || $tenor === '' || !is_numeric($tenor)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Field "tenor" wajib dan harus berupa angka.']);
        exit;
    }

    $tujuan_penggunaan = isset($tujuan_penggunaan) ? trim((string)$tujuan_penggunaan) : '';
    if ($tujuan_penggunaan === '') {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Field "tujuan_penggunaan" wajib dan tidak boleh kosong.']);
        exit;
    }

    // Cast to integer and validate
    $jumlah_pinjaman = (int)$jumlah_pinjaman;
    $tenor = (int)$tenor;

    if ($jumlah_pinjaman <= 0) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Jumlah pinjaman harus lebih dari 0.']);
        exit;
    }
    if ($tenor <= 0) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Tenor harus lebih dari 0.']);
        exit;
    }
    if (mb_strlen($tujuan_penggunaan) > 255) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Panjang "tujuan_penggunaan" maksimal 255 karakter.']);
        exit;
    }

    // Business fields
    $status = 'pending';

    // DB connection (mysqli procedural $con expected in config/database.php)
    require_once __DIR__ . '/../../config/database.php';
    if (!isset($con) || !($con instanceof mysqli)) {
        error_log('ajukan.php: missing $con mysqli connection');
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Server database belum terkonfigurasi.']);
        exit;
    }

    // Start output buffering to catch accidental HTML output
ob_start();

// --- Authentication: Bearer token from Authorization header ONLY ---
    $token = null;
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (is_array($h)) {
            foreach ($h as $k => $v) {
                if (strtolower($k) === 'authorization') {
                    if (preg_match('/Bearer\s+(.+)/i', $v, $m)) $token = trim($m[1]);
                    break;
                }
            }
        }
    }
    if ($token === null) {
        $ah = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
        if ($ah && preg_match('/Bearer\s+(.+)/i', $ah, $m)) $token = trim($m[1]);
    }

    if ($token === null || $token === '') {
        // Clean buffer and return JSON
        $captured = trim((string)ob_get_clean());
        if ($captured !== '') @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " CAPTURED BEFORE AUTH: " . substr($captured,0,2000) . "\n", FILE_APPEND | LOCK_EX);
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Autentikasi diperlukan. Sertakan header Authorization: Bearer <token>']);
        exit;
    }

    // Verify token exists in pengguna.api_token
    $colSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengguna' AND COLUMN_NAME = 'api_token' LIMIT 1";
    $colRes = mysqli_query($con, $colSql);
    if (!$colRes) {
        error_log('ajukan.php: failed to query INFORMATION_SCHEMA: ' . mysqli_error($con));
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Server database belum terkonfigurasi.']);
        exit;
    }
    $colRow = mysqli_fetch_assoc($colRes);
    if (!($colRow && (int)$colRow['cnt'] > 0)) {
        error_log('ajukan.php: api_token column missing');
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Server belum dikonfigurasi untuk otentikasi token.']);
        exit;
    }

    $tstmt = mysqli_prepare($con, "SELECT id FROM pengguna WHERE api_token = ? LIMIT 1");
    if (!$tstmt) {
        error_log('ajukan.php: token lookup prepare failed: ' . mysqli_error($con));
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Server database error.']);
        exit;
    }
    mysqli_stmt_bind_param($tstmt, 's', $token);
    mysqli_stmt_execute($tstmt);
    mysqli_stmt_bind_result($tstmt, $foundId);
    mysqli_stmt_fetch($tstmt);
    mysqli_stmt_close($tstmt);

    if (empty($foundId) || !is_numeric($foundId)) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Token tidak valid.']);
        exit;
    }
    $userId = (int)$foundId;

    // Dynamic column detection and robust insert with logging
    // Determine which amount/status column names exist to match DB schema
    $colCheckSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pinjaman' AND COLUMN_NAME IN ('jumlah_pinjaman','jumlah','status','status_pinjaman','tenor','tujuan_penggunaan')";
    $colRes = mysqli_query($con, $colCheckSql);
    $cols = [];
    if ($colRes) {
        while ($row = mysqli_fetch_assoc($colRes)) {
            $cols[$row['COLUMN_NAME']] = true;
        }
        mysqli_free_result($colRes);
    }

    // Map logical to physical columns
    $amountCol = isset($cols['jumlah_pinjaman']) ? 'jumlah_pinjaman' : (isset($cols['jumlah']) ? 'jumlah' : null);
    $statusCol = isset($cols['status']) ? 'status' : (isset($cols['status_pinjaman']) ? 'status_pinjaman' : null);
    $hasTenor = isset($cols['tenor']);
    $hasTujuan = isset($cols['tujuan_penggunaan']);

    if ($amountCol === null || $statusCol === null) {
        error_log('ajukan.php: required columns missing in pinjaman table');
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Server belum terkonfigurasi untuk menyimpan pinjaman.']);
        exit;
    }

    // Build insert dynamically
    $insertCols = ['id_pengguna', $amountCol];
    $placeholders = ['?', '?'];
    $types = 'ii';
    $values = [$userId, $jumlah_pinjaman];

    if ($hasTenor) {
        $insertCols[] = 'tenor';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $tenor;
    }
    if ($hasTujuan) {
        $insertCols[] = 'tujuan_penggunaan';
        $placeholders[] = '?';
        $types .= 's';
        $values[] = $tujuan_penggunaan;
    }

    $insertCols[] = $statusCol;
    $placeholders[] = '?';
    $types .= 's';
    $values[] = $status;

    $insertCols[] = 'created_at';
    $placeholders[] = 'NOW()';

    $sql = "INSERT INTO pinjaman (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        error_log('ajukan.php: prepare failed: ' . mysqli_error($con));
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Gagal menyimpan pengajuan pinjaman. Silakan coba lagi.']);
        exit;
    }

    // Helper to bind params by reference
    $refs = [];
    foreach ($values as $k => $v) {
        $refs[$k] = &$values[$k];
    }
    array_unshift($refs, $types);
    array_unshift($refs, $stmt);

    if (!call_user_func_array('mysqli_stmt_bind_param', $refs)) {
        error_log('ajukan.php: bind_param failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Gagal menyimpan pengajuan pinjaman. Silakan coba lagi.']);
        exit;
    }

    if (!mysqli_stmt_execute($stmt)) {
        error_log('ajukan.php: execute failed: ' . mysqli_stmt_error($stmt));
        // Also write to local debug log
        @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " execute failed: " . mysqli_stmt_error($stmt) . "\n", FILE_APPEND | LOCK_EX);
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Gagal menyimpan pengajuan pinjaman. Silakan coba lagi.']);
        exit;
    }

    $affected = mysqli_stmt_affected_rows($stmt);
    $insertId = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    if ($affected > 0) {
        // Fetch inserted row and return it so client can verify
        $row = null;
        $safeId = (int)$insertId;
        // Detect existing amount column to avoid selecting a non-existent legacy column
        $has_jumlah_pinjaman = false;
        $has_jumlah = false;
        $resCols = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pinjaman' AND COLUMN_NAME IN ('jumlah_pinjaman','jumlah')");
        if ($resCols) {
            while ($col = mysqli_fetch_assoc($resCols)) {
                if ($col['COLUMN_NAME'] === 'jumlah_pinjaman') $has_jumlah_pinjaman = true;
                if ($col['COLUMN_NAME'] === 'jumlah') $has_jumlah = true;
            }
            mysqli_free_result($resCols);
        }

        if ($has_jumlah_pinjaman && $has_jumlah) {
            $q = "SELECT id, id_pengguna, COALESCE(jumlah_pinjaman, jumlah) AS jumlah_pinjaman, tenor, tujuan_penggunaan, status, created_at FROM pinjaman WHERE id = $safeId LIMIT 1";
        } elseif ($has_jumlah_pinjaman) {
            $q = "SELECT id, id_pengguna, jumlah_pinjaman AS jumlah_pinjaman, tenor, tujuan_penggunaan, status, created_at FROM pinjaman WHERE id = $safeId LIMIT 1";
        } elseif ($has_jumlah) {
            $q = "SELECT id, id_pengguna, jumlah AS jumlah_pinjaman, tenor, tujuan_penggunaan, status, created_at FROM pinjaman WHERE id = $safeId LIMIT 1";
        } else {
            $q = "SELECT id, id_pengguna, 0 AS jumlah_pinjaman, tenor, tujuan_penggunaan, status, created_at FROM pinjaman WHERE id = $safeId LIMIT 1";
        }

        $r = mysqli_query($con, $q);
        if ($r) {
            $row = mysqli_fetch_assoc($r);
            mysqli_free_result($r);
        }

        // Capture and log any accidental output before returning JSON
        $captured = trim((string)ob_get_clean());
        if ($captured !== '') @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " CAPTURED OUTPUT BEFORE SUCCESS: " . substr($captured,0,4000) . "\n", FILE_APPEND | LOCK_EX);

        @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " inserted id={$insertId} user={$userId} amount={$jumlah_pinjaman} status={$status} row=" . json_encode($row) . "\n", FILE_APPEND | LOCK_EX);

        // Create a notification for the user so it appears in the Notifikasi page
        $notif_id = null;
        $notif_msg_amount = 'Rp ' . number_format($jumlah_pinjaman, 0, ',', '.');
        $notif_title = 'Pengajuan Pinjaman Diajukan';
        $notif_message = 'Pengajuan pinjaman sebesar ' . $notif_msg_amount . ' untuk tenor ' . intval($tenor) . ' bulan telah diajukan. Menunggu persetujuan admin.';
        if (file_exists(__DIR__ . '/../../flutter_api/notif_helper.php')) {
            require_once __DIR__ . '/../../flutter_api/notif_helper.php';
            if (function_exists('safe_create_notification')) {
                $notif_id = @safe_create_notification($con, $userId, 'pinjaman', $notif_title, $notif_message, json_encode(['id' => $insertId, 'amount' => $jumlah_pinjaman, 'tenor' => $tenor]));
            }
        }

        header('Content-Type: application/json; charset=utf-8', true, 201);
        echo json_encode(['status' => true, 'message' => 'Pengajuan pinjaman berhasil', 'id' => $insertId, 'row' => $row, 'notif_id' => $notif_id]);
        exit;
    }

    error_log('ajukan.php: insert affected rows = ' . $affected);
    @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " insert affected={$affected} user={$userId} amount={$jumlah_pinjaman}\n", FILE_APPEND | LOCK_EX);
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Gagal menyimpan pengajuan pinjaman. Silakan coba lagi.']);
    exit;

} catch (Exception $ex) {
    error_log('ajukan.php Exception: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Terjadi kesalahan. Silakan coba lagi nanti.']);
    exit;
}
