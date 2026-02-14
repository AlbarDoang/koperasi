<?php
/**
 * Helper Functions untuk Flutter API
 * File ini berisi fungsi-fungsi umum yang digunakan di berbagai endpoint API
 */

/**
 * Get POST data from either $_POST or raw JSON input
 * @param string $key - Key name to get
 * @param mixed $default - Default value if key not found
 * @return mixed
 */
function getPostData($key, $default = '') {
    // First check $_POST
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    
    // Try to get from raw JSON input
    static $jsonData = null;
    if ($jsonData === null) {
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if ($jsonData === null) {
                $jsonData = []; // Invalid JSON
            }
        } else {
            $jsonData = [];
        }
    }
    
    if (isset($jsonData[$key])) {
        return $jsonData[$key];
    }
    
    return $default;
}

/**
 * Send JSON response
 * @param bool $success
 * @param string $message
 * @param array $data
 */
function sendJsonResponse($success, $message, $data = null) {
    // Mark that JSON is being emitted to prevent connection fallback
    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;

    $response = array(
        "success" => $success,
        "status" => $success,
        "message" => $message
    );
    
    if ($data !== null) {
        if (is_array($data)) {
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }
    }

    // Encode and log the exact JSON we will send (helpful to trace double responses)
    $json = json_encode($response);
    $script = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'unknown');
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [helpers] JSON OUT from {$script} {$uri} {$ip}: " . $json . "\n", FILE_APPEND);

    if (!headers_sent()) header('Content-Type: application/json');
    echo $json;
    exit();
}

// A safe wrapper that clears any unexpected buffered output (warnings/HTML)
if (!function_exists('safeJsonResponse')) {
    function safeJsonResponse($success, $message, $data = null) {
        if (ob_get_level() && ($buf = ob_get_clean()) !== null) {
            // Strip potential BOM for clear logging
            $buf = preg_replace('/^\xEF\xBB\xBF/', '', $buf);
            $buf = preg_replace('/^\x{FEFF}/u', '', $buf);
            $trim = trim($buf);
            if ($trim !== '') {
                $script = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['SCRIPT_NAME'] ?? 'unknown');
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $summary = mb_substr($trim, 0, 2000);
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [helpers] Unexpected buffered output before JSON for {$script} {$uri} {$ip}: " . $summary . "\n", FILE_APPEND);
            }
            if (ob_get_level() === 0) ob_start();
        }
        sendJsonResponse($success, $message, $data);
    }
}

/**
 * Validate required fields
 * @param array $fields - Array of field names
 * @param array $labels - Array of field labels (optional)
 * @return array|null - Returns array of errors or null if valid
 */
function validateRequiredFields($fields, $labels = []) {
    $errors = [];
    
    foreach ($fields as $field) {
        $value = getPostData($field);
        if (empty($value)) {
            $label = isset($labels[$field]) ? $labels[$field] : $field;
            $errors[] = "$label wajib diisi";
        }
    }
    
    return empty($errors) ? null : $errors;
}

/**
 * Phone helpers
 * - phone_to_local08($phone): Normalize input into local Indonesian national format (08...)
 * - phone_to_international62($phone): Normalize into international format (62...)
 * - sanitizePhone (legacy): kept as alias to phone_to_local08 for DB saves
 */
function phone_to_local08($phone) {
    if (empty($phone)) return false;
    // Remove non-digit characters
    $p = preg_replace('/[^0-9]/', '', $phone);
    // If starts with +, it was removed above
    if (substr($p, 0, 2) === '62') {
        // Convert leading 62 to 0
        $p = '0' . substr($p, 2);
    }
    // If already starts with 0, keep
    if (substr($p, 0, 1) !== '0') {
        // If it's missing leading 0 but has reasonable length, prefix 0
        if (preg_match('/^\d{8,13}$/', $p)) {
            $p = '0' . $p;
        } else {
            return false;
        }
    }
    // Validate final pattern: 0 followed by 9-13 digits (total length 10-14)
    if (!preg_match('/^0\d{9,13}$/', $p)) {
        return false;
    }
    return $p;
}

function phone_to_international62($phone) {
    // First normalize to local 08 format
    $local = phone_to_local08($phone);
    if (!$local) return false;
    // Replace leading 0 with 62
    return '62' . substr($local, 1);
}

/**
 * Legacy alias used across codebase. Now returns local 08 format (suitable for DB storage)
 */
function sanitizePhone($phone) {
    $res = phone_to_local08($phone);
    return $res === false ? '' : $res;
}

/**
 * Compatibility wrapper: normalizePhoneNumber -> international 62 format
 * Returns '62...' or false on invalid input. Prefer using phone_to_international62 directly when appropriate.
 */
function normalizePhoneNumber($phone) {
    $res = phone_to_international62($phone);
    return $res === false ? false : $res;
}

/**
 * Safely compute transaction sums for a given id_tabungan or id_pengguna
 * Returns associative array: ['total_masuk' => float, 'total_keluar' => float, 'saldo' => float]
 * Returns null if cannot calculate due to missing columns
 */
function safe_sum_transaksi($conn, $id_tabungan = null) {
    // Check if transaksi table exists
    $check = $conn->query("SHOW TABLES LIKE 'transaksi'");
    if (!$check || $check->num_rows === 0) {
        return null;
    }

    $where = '';
    $params = '';
    // Detect which id column exists in `transaksi` to filter by (avoid Unknown column errors)
    $id_col = null;
    foreach (['id_tabungan','id_pengguna','id_pengguna','id'] as $c) {
        $resC = $conn->query("SHOW COLUMNS FROM `transaksi` LIKE '" . $conn->real_escape_string($c) . "'");
        if ($resC && $resC->num_rows > 0) { $id_col = $c; break; }
    }
    if ($id_col && $id_tabungan !== null && $id_tabungan !== '') {
        $safe = $conn->real_escape_string($id_tabungan);
        $where = " WHERE `{$id_col}`='{$safe}'";
    }

    // Ensure helper for checking columns exists in this context
    $table_column_exists = function($conn, $table, $column) {
        $escaped = $conn->real_escape_string($table);
        $col = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$escaped}` LIKE '{$col}'");
        return $res && $res->num_rows > 0;
    };

    // Case 1: columns jumlah_masuk and jumlah_keluar exist
    if (
        $table_column_exists($conn, 'transaksi', 'jumlah_masuk') &&
        $table_column_exists($conn, 'transaksi', 'jumlah_keluar')
    ) {
        $sql = "SELECT COALESCE(SUM(COALESCE(jumlah_masuk,0)),0) AS total_masuk, COALESCE(SUM(COALESCE(jumlah_keluar,0)),0) AS total_keluar FROM transaksi" . $where;
        if ($r = $conn->query($sql)) {
                $row = $r->fetch_assoc();
                $m = floatval($row['total_masuk']);
                $k = floatval($row['total_keluar']);
                // count and last
                $count = 0; $last = null;
                if ($id_tabungan !== null && $id_tabungan !== '') {
                    $safe = $conn->real_escape_string($id_tabungan);
                    $rCount = $conn->query("SELECT COUNT(*) as cnt FROM transaksi WHERE id_tabungan='{$safe}'");
                    if ($rCount && $rCount->num_rows > 0) { $cntRow = $rCount->fetch_assoc(); $count = intval($cntRow['cnt']); }
                    $rLast = $conn->query("SELECT * FROM transaksi WHERE id_tabungan='{$safe}' ORDER BY id_transaksi DESC LIMIT 1");
                    if ($rLast && $rLast->num_rows > 0) { $last = $rLast->fetch_assoc(); }
                }
                return ['total_masuk' => $m, 'total_keluar' => $k, 'saldo' => ($m - $k), 'count' => $count, 'last' => $last];
            }
    }

    // Case 2: generic jumlah/nominal with jenis/type
    $amountCol = null;
    foreach (['jumlah','nominal','amount'] as $c) {
        if ($table_column_exists($conn, 'transaksi', $c)) { $amountCol = $c; break; }
    }
    $typeCol = null;
    foreach (['jenis','tipe','type','keterangan'] as $c) {
        if ($table_column_exists($conn, 'transaksi', $c)) { $typeCol = $c; break; }
    }
    if ($amountCol && $typeCol) {
        // Accept common inbound/outbound labels
        $inVals = "'masuk','credit','in'";
        $outVals = "'keluar','debit','out'";
        $sql = sprintf(
            "SELECT COALESCE(SUM(CASE WHEN `%s` IN (%s) THEN `%s` ELSE 0 END),0) AS total_masuk, COALESCE(SUM(CASE WHEN `%s` IN (%s) THEN `%s` ELSE 0 END),0) AS total_keluar FROM transaksi%s",
            $typeCol, $inVals, $amountCol,
            $typeCol, $outVals, $amountCol,
            $where
        );
        if ($r = $conn->query($sql)) {
            $row = $r->fetch_assoc();
            $m = floatval($row['total_masuk'] ?? 0);
            $k = floatval($row['total_keluar'] ?? 0);
            $count = 0; $last = null;
            if ($id_tabungan !== null && $id_tabungan !== '') {
                $safe = $conn->real_escape_string($id_tabungan);
                $rCount = $conn->query("SELECT COUNT(*) as cnt FROM transaksi WHERE id_tabungan='{$safe}'");
                if ($rCount && $rCount->num_rows > 0) { $cntRow = $rCount->fetch_assoc(); $count = intval($cntRow['cnt']); }
                $rLast = $conn->query("SELECT * FROM transaksi WHERE id_tabungan='{$safe}' ORDER BY id_transaksi DESC LIMIT 1");
                if ($rLast && $rLast->num_rows > 0) { $last = $rLast->fetch_assoc(); }
            }
            return ['total_masuk' => $m, 'total_keluar' => $k, 'saldo' => ($m - $k), 'count' => $count, 'last' => $last];
        }
    }

    // Case X: legacy t_masuk / t_keluar tables
    $has_t_masuk = $conn->query("SHOW TABLES LIKE 't_masuk'");
    $has_t_keluar = $conn->query("SHOW TABLES LIKE 't_keluar'");
    if (($has_t_masuk && $has_t_masuk->num_rows > 0) || ($has_t_keluar && $has_t_keluar->num_rows > 0)) {
        $m = 0.0; $k = 0.0; $count = 0; $last = null;
        if ($id_tabungan !== null && $id_tabungan !== '') {
            $safe = $conn->real_escape_string($id_tabungan);
            if ($has_t_masuk && $has_t_masuk->num_rows > 0) {
                $r = $conn->query("SELECT COALESCE(SUM(jumlah),0) AS total_masuk FROM t_masuk WHERE id_tabungan='{$safe}'");
                if ($r && $r->num_rows > 0) { $rr = $r->fetch_assoc(); $m = floatval($rr['total_masuk']); }
            }
            if ($has_t_keluar && $has_t_keluar->num_rows > 0) {
                $r = $conn->query("SELECT COALESCE(SUM(jumlah),0) AS total_keluar FROM t_keluar WHERE id_tabungan='{$safe}'");
                if ($r && $r->num_rows > 0) { $rr = $r->fetch_assoc(); $k = floatval($rr['total_keluar']); }
            }
            // count via t_masuk + t_keluar
            $rCount1 = $conn->query("SELECT COUNT(*) as cnt FROM t_masuk WHERE id_tabungan='{$safe}'");
            $rCount2 = $conn->query("SELECT COUNT(*) as cnt FROM t_keluar WHERE id_tabungan='{$safe}'");
            $cnt1 = ($rCount1 && $rCount1->num_rows > 0) ? intval($rCount1->fetch_assoc()['cnt']) : 0;
            $cnt2 = ($rCount2 && $rCount2->num_rows > 0) ? intval($rCount2->fetch_assoc()['cnt']) : 0;
            $count = $cnt1 + $cnt2;
            // last transaction: find latest by timestamp if available
            $rLast1 = $conn->query("SELECT * FROM t_masuk WHERE id_tabungan='{$safe}' ORDER BY id_masuk DESC LIMIT 1");
            $rLast2 = $conn->query("SELECT * FROM t_keluar WHERE id_tabungan='{$safe}' ORDER BY id_keluar DESC LIMIT 1");
            $cand1 = ($rLast1 && $rLast1->num_rows > 0) ? $rLast1->fetch_assoc() : null;
            $cand2 = ($rLast2 && $rLast2->num_rows > 0) ? $rLast2->fetch_assoc() : null;
            if ($cand1 && $cand2) {
                $ts1 = isset($cand1['tanggal']) ? strtotime($cand1['tanggal']) : 0;
                $ts2 = isset($cand2['tanggal']) ? strtotime($cand2['tanggal']) : 0;
                $last = ($ts1 >= $ts2) ? $cand1 : $cand2;
            } else if ($cand1) { $last = $cand1; } else if ($cand2) { $last = $cand2; }
            return ['total_masuk' => $m, 'total_keluar' => $k, 'saldo' => ($m - $k), 'count' => $count, 'last' => $last];
        }
    }

    // Case 3: single column 'masuk' or 'keluar'
    if ($table_column_exists($conn, 'transaksi', 'masuk') || $table_column_exists($conn, 'transaksi', 'keluar')) {
        $mCol = $table_column_exists($conn, 'transaksi', 'masuk') ? 'masuk' : null;
        $kCol = $table_column_exists($conn, 'transaksi', 'keluar') ? 'keluar' : null;
        $m = 0.0; $k = 0.0;
        if ($mCol) {
            $sqlM = "SELECT COALESCE(SUM(`{$mCol}`),0) AS total_masuk FROM transaksi" . $where;
            if ($r = $conn->query($sqlM)) { $row = $r->fetch_assoc(); $m = floatval($row['total_masuk'] ?? 0); }
        }
        if ($kCol) {
            $sqlK = "SELECT COALESCE(SUM(`{$kCol}`),0) AS total_keluar FROM transaksi" . $where;
            if ($r = $conn->query($sqlK)) { $row = $r->fetch_assoc(); $k = floatval($row['total_keluar'] ?? 0); }
            $count = 0; $last = null;
            if ($id_tabungan !== null && $id_tabungan !== '') {
                $safe = $conn->real_escape_string($id_tabungan);
                $rCount = $conn->query("SELECT COUNT(*) as cnt FROM transaksi WHERE id_tabungan='{$safe}'");
                if ($rCount && $rCount->num_rows > 0) { $cntRow = $rCount->fetch_assoc(); $count = intval($cntRow['cnt']); }
                $rLast = $conn->query("SELECT * FROM transaksi WHERE id_tabungan='{$safe}' ORDER BY id_transaksi DESC LIMIT 1");
                if ($rLast && $rLast->num_rows > 0) { $last = $rLast->fetch_assoc(); }
            }
            return ['total_masuk' => $m, 'total_keluar' => $k, 'saldo' => ($m - $k), 'count' => $count, 'last' => $last];
        }
        return ['total_masuk' => $m, 'total_keluar' => $k, 'saldo' => ($m - $k)];
    }

    return null;
}

// ============================================================================
// VALIDASI ENUM
// ============================================================================

/**
 * Validasi status_akun sesuai ENUM tabel pengguna
 * ENUM: 'draft','submitted','pending','approved','rejected'
 *
 * @param string $status Nilai yang ingin divalidasi
 * @return string|false Nilai lowercase yang valid, atau false jika tidak valid
 */
function validateStatusAkun($status) {
    $allowed = ['draft', 'submitted', 'pending', 'approved', 'rejected'];
    $status = strtolower(trim($status));
    return in_array($status, $allowed) ? $status : false;
}

/**
 * Validasi status otp_codes sesuai ENUM tabel otp_codes
 * ENUM: 'belum','sudah'
 *
 * @param string $status Nilai yang ingin divalidasi
 * @return string|false Nilai lowercase yang valid, atau false jika tidak valid
 */
function validateStatusOtp($status) {
    $allowed = ['belum', 'sudah'];
    $status = strtolower(trim($status));
    return in_array($status, $allowed) ? $status : false;
}
?>

