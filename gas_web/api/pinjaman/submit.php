<?php
// api/pinjaman/submit.php
// Endpoint untuk submit pengajuan pinjaman

declare(strict_types=1);

// Debug / error reporting
// Disable display of errors to avoid corrupting JSON responses in production/testing
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(0);

// Response JSON
header('Content-Type: application/json; charset=utf-8');
// Ensure basic CORS headers are present as early as possible so browser clients
// (Flutter web / JS) will not fail with opaque "Failed to fetch" errors when
// something goes wrong later in the script.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');

// Start output buffering to capture any accidental HTML or warnings
ob_start();

// Log request headers and raw body for debugging (non-sensitive, local)
$logPath = __DIR__ . '/debug.log';
$rawBody = file_get_contents('php://input');
$headersToLog = [];
if (function_exists('getallheaders')) {
    $h = getallheaders();
    if (is_array($h)) {
        foreach ($h as $k => $v) {
            if (strtolower($k) === 'authorization') continue; // skip tokens
            $headersToLog[$k] = $v;
        }
    }
}
@file_put_contents($logPath, date('Y-m-d H:i:s') . " REQ headers: " . json_encode($headersToLog) . " body: " . substr($rawBody,0,2000) . "\n", FILE_APPEND | LOCK_EX);

// Helper to emit a clean JSON error and log captured output
function emit_json_error_and_exit($code, $message, $logPath, $extra = null) {
    // Capture any buffered output (like HTML or warnings)
    $captured = trim((string)ob_get_clean());
    if ($captured !== '') {
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " CAPTURED OUTPUT:\n" . substr($captured,0,4000) . "\n", FILE_APPEND | LOCK_EX);
    }

    // Ensure CORS headers are present even on errors so browser won't block the response
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    } else {
        header('Access-Control-Allow-Origin: *');
    }
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');
    header('Access-Control-Allow-Methods: POST, OPTIONS');

    $payload = ['status' => false, 'message' => $message];
    if ($extra) $payload['detail'] = $extra;

    header('Content-Type: application/json; charset=utf-8', true, $code);
    echo json_encode($payload);
    exit;
}

// Main execution wrapped in try/catch
try {


// CORS untuk testing — echo incoming Origin to satisfy browsers and allow common headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept');

// Handle preflight and log it for debugging
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    @file_put_contents($logPath, date('Y-m-d H:i:s') . " PRELIGHT OPTIONS from origin: " . ($origin ?: 'none') . " headers: " . json_encode(getallheaders()) . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(204);
    exit;
} 

// Non-POST request
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => 'Method Not Allowed. Gunakan POST.'
    ]);
    exit;
}

// Include DB
require_once __DIR__ . '/../../config/db.php';

// Baca input JSON atau form-data
$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (empty($input) && stripos((string)$contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => 'Gagal mengajukan pinjaman',
            'error' => 'JSON tidak valid'
        ]);
        exit;
    }
}

// Debug log
file_put_contents(__DIR__ . '/debug.log', "[" . date('Y-m-d H:i:s') . "] INPUT: " . var_export($input, true) . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/debug.log', "[" . date('Y-m-d H:i:s') . "] DB CON: " . var_export($con, true) . "\n", FILE_APPEND);

// Ambil parameter
$id_pengguna = $input['id_pengguna'] ?? null;
$jumlah_pinjaman = $input['jumlah_pinjaman'] ?? null;

// Validasi
if ($id_pengguna === null || !is_numeric($id_pengguna) || (int)$id_pengguna <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => 'Parameter id_pengguna wajib dan harus angka.'
    ]);
    exit;
}

if ($jumlah_pinjaman === null || !is_numeric($jumlah_pinjaman) || (float)$jumlah_pinjaman <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => 'Parameter jumlah_pinjaman wajib dan harus > 0.'
    ]);
    exit;
}

// Enforce minimum loan amount for 'pinjaman biasa' to avoid client-side bypass
if ((float)$jumlah_pinjaman < 500000.0) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => 'Nominal minimal pinjaman Rp 500.000'
    ]);
    exit;
}

$id_pengguna = (int)$id_pengguna;
$jumlah_val = (float)$jumlah_pinjaman;

// Detect which table to use: prefer 'pinjaman', then 'pinjaman_biasa', then any table like 'pinjaman%'
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
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => 'Tabel pinjaman tidak ditemukan pada database.'
    ]);
    exit;
}
// Safe quoted table name
$tableQuoted = '`' . str_replace('`', '', $escapedTable) . '`';
@file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " USING TABLE: $escapedTable\n", FILE_APPEND | LOCK_EX);

// === Insert ke DB ===
// Accept optional tenor and tujuan_penggunaan to make submissions identifiable
$tenor_val = isset($input['tenor']) && is_numeric($input['tenor']) ? (int)$input['tenor'] : 0;
$tujuan_val = isset($input['tujuan_penggunaan']) ? trim((string)$input['tujuan_penggunaan']) : '';

// Compute cicilan_per_bulan server-side using FLAT syariah rules (no interest, no margin)
// Rules: cicilan = floor(total_pinjaman / tenor). Any remainder is intentionally waived (ignored).
if ($tenor_val <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => 'Tenor harus lebih besar dari 0 (bulan).'
    ]);
    exit;
}
// Use downward rounding to integer rupiah
$total_int = (int)floor($jumlah_val);
if ($total_int <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => 'Jumlah pinjaman harus lebih besar dari 0.'
    ]);
    exit;
}
$base = intdiv($total_int, $tenor_val);
if ($base < 0) $base = 0;
// Under syariah rule: monthly installment is the floored base and is the same for every month.
// Any leftover (total_int - base*tenor) is waived and not collected.
$cicilan_val = (int)$base; // flat monthly installment (may be 0 if total < tenor)

// Check whether the pinjaman table actually has cicilan_per_bulan column (backwards compatible)
$has_cicilan_col = false;
// Check whether the chosen table actually has cicilan_per_bulan column (backwards compatible)
$chk = mysqli_query($con, "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . mysqli_real_escape_string($con, $escapedTable) . "' AND COLUMN_NAME = 'cicilan_per_bulan'");
if ($chk) {
    $row_chk = mysqli_fetch_assoc($chk);
    if (isset($row_chk['c']) && (int)$row_chk['c'] > 0) $has_cicilan_col = true;
    mysqli_free_result($chk);
}

// If the target is explicit 'pinjaman_biasa' and column is missing, attempt to add it automatically
// This makes the system start persisting `cicilan_per_bulan` for future submissions without manual migration.
if (!$has_cicilan_col && $escapedTable === 'pinjaman_biasa') {
    $alterSql = "ALTER TABLE `" . mysqli_real_escape_string($con, $escapedTable) . "` ADD COLUMN `cicilan_per_bulan` BIGINT UNSIGNED NOT NULL DEFAULT 0";
    // Try altering the table, but do not fail the whole request if ALTER is not permitted; just log
    if (mysqli_query($con, $alterSql)) {
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " INFO: Added cicilan_per_bulan column to $escapedTable\n", FILE_APPEND | LOCK_EX);
        $has_cicilan_col = true;
    } else {
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " WARN: Failed to add cicilan_per_bulan to $escapedTable: " . mysqli_error($con) . "\n", FILE_APPEND | LOCK_EX);
    }
}

if ($has_cicilan_col) {
    $sql = "INSERT INTO " . $tableQuoted . " (id_pengguna, jumlah_pinjaman, tenor, tujuan_penggunaan, cicilan_per_bulan, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $err = mysqli_error($con);
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Gagal mengajukan pinjaman',
            'error' => $err
        ]);
        exit;
    }

    // Bind parameters: id_pengguna (int), jumlah_pinjaman (double), tenor (int), tujuan_penggunaan (string), cicilan_per_bulan (int)
    // NOTE: The SQL lists tujuan_penggunaan before cicilan_per_bulan, so bind must match that order
    $cicilan_bind = (int)$cicilan_val;
    if (!mysqli_stmt_bind_param($stmt, 'idisi', $id_pengguna, $jumlah_val, $tenor_val, $tujuan_val, $cicilan_bind)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Gagal mengajukan pinjaman',
            'error' => $err
        ]);
        exit;
    }
} else {
    // Fallback for older schemas that don't have cicilan_per_bulan column
    $sql = "INSERT INTO " . $tableQuoted . " (id_pengguna, jumlah_pinjaman, tenor, tujuan_penggunaan, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $err = mysqli_error($con);
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Gagal mengajukan pinjaman',
            'error' => $err
        ]);
        exit;
    }

    // Bind parameters: id_pengguna (int), jumlah_pinjaman (double), tenor (int), tujuan_penggunaan (string)
    if (!mysqli_stmt_bind_param($stmt, 'idis', $id_pengguna, $jumlah_val, $tenor_val, $tujuan_val)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Gagal mengajukan pinjaman',
            'error' => $err
        ]);
        exit;
    }
}

// Execute
if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal mengajukan pinjaman',
        'error' => $err
    ]);
    exit;
}

$affected = mysqli_stmt_affected_rows($stmt);
$insertId = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

// Response sukses
if ($affected > 0) {
    // Fetch inserted row to confirm and return to client
    $row = null;
    $safeId = (int)$insertId;

    // Detect existing amount and cicilan columns to avoid selecting non-existent legacy columns
    $has_jumlah_pinjaman = false;
    $has_jumlah = false;
    $has_cicilan = false;
    $tbl = mysqli_real_escape_string($con, $escapedTable);
    $resCols = mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl' AND COLUMN_NAME IN ('jumlah_pinjaman','jumlah','cicilan_per_bulan')");
    if ($resCols) {
        while ($col = mysqli_fetch_assoc($resCols)) {
            if ($col['COLUMN_NAME'] === 'jumlah_pinjaman') $has_jumlah_pinjaman = true;
            if ($col['COLUMN_NAME'] === 'jumlah') $has_jumlah = true;
            if ($col['COLUMN_NAME'] === 'cicilan_per_bulan') $has_cicilan = true;
        }
        mysqli_free_result($resCols);
    }

    // Build select list dynamically depending on which columns exist
    $selectCols = ['id', 'id_pengguna'];
    if ($has_jumlah_pinjaman && $has_jumlah) {
        $selectCols[] = 'COALESCE(jumlah_pinjaman, jumlah) AS jumlah_pinjaman';
    } elseif ($has_jumlah_pinjaman) {
        $selectCols[] = 'jumlah_pinjaman AS jumlah_pinjaman';
    } elseif ($has_jumlah) {
        $selectCols[] = 'jumlah AS jumlah_pinjaman';
    } else {
        $selectCols[] = '0 AS jumlah_pinjaman';
    }

    if ($has_cicilan) {
        $selectCols[] = 'cicilan_per_bulan';
    }

    $selectCols[] = 'tenor';
    $selectCols[] = 'tujuan_penggunaan';
    $selectCols[] = 'status';
    $selectCols[] = 'created_at';

    $q = "SELECT " . implode(', ', $selectCols) . " FROM `" . $tbl . "` WHERE id = $safeId LIMIT 1";

    $r = mysqli_query($con, $q);
    if ($r) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
    } else {
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " WARNING: fetch row failed: " . mysqli_error($con) . " query: " . $q . "\n", FILE_APPEND | LOCK_EX);
    }

    // If the insert created a row where a numeric cicilan accidentally landed in tujuan_penggunaan
    // (previous bug where binding order was incorrect), move numeric-only tujuan into cicilan_per_bulan.
    if ($has_cicilan_col) {
        // Fix the just-inserted row if needed
        $fix_sql_single = "UPDATE `" . $tbl . "` SET cicilan_per_bulan = CAST(tujuan_penggunaan AS UNSIGNED), tujuan_penggunaan = '' WHERE id = $insertId AND (cicilan_per_bulan IS NULL OR cicilan_per_bulan = 0) AND tujuan_penggunaan REGEXP '^[0-9]+$'";
        @mysqli_query($con, $fix_sql_single);
        $fixed_single = mysqli_affected_rows($con);
        if ($fixed_single > 0) {
            @file_put_contents($logPath, date('Y-m-d H:i:s') . " FIXED single inserted id={$insertId}, moved numeric tujuan to cicilan_per_bulan\n", FILE_APPEND | LOCK_EX);
        }

        // Backfill historically: move numeric-only tujuan_penggunaan into cicilan_per_bulan for all rows where cicilan_per_bulan is empty/0
        $fix_sql_all = "UPDATE `" . $tbl . "` SET cicilan_per_bulan = CAST(tujuan_penggunaan AS UNSIGNED), tujuan_penggunaan = '' WHERE (cicilan_per_bulan IS NULL OR cicilan_per_bulan = 0) AND tujuan_penggunaan REGEXP '^[0-9]+$'";
        @mysqli_query($con, $fix_sql_all);
        $fixed_all = mysqli_affected_rows($con);
        if ($fixed_all > 0) {
            @file_put_contents($logPath, date('Y-m-d H:i:s') . " BACKFILL FIXED rows={$fixed_all} (numeric tujuan -> cicilan_per_bulan)\n", FILE_APPEND | LOCK_EX);
        }

        // Re-fetch the row after potential fix so we return the corrected data
        $r2 = mysqli_query($con, $q);
        if ($r2) {
            $row = mysqli_fetch_assoc($r2);
            mysqli_free_result($r2);
        }
    }

    // Capture and log any accidental output before returning JSON
    $captured = trim((string)ob_get_clean());
    if ($captured !== '') @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " CAPTURED OUTPUT BEFORE SUCCESS: " . substr($captured,0,4000) . "\n", FILE_APPEND | LOCK_EX);

    @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " inserted id={$insertId} row=" . json_encode($row) . "\n", FILE_APPEND | LOCK_EX);

    // Log the origin we observed and that we're returning a successful response
    @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " SUCCESS RESPONSE origin={$origin} inserted_id={$insertId}\n", FILE_APPEND | LOCK_EX);

    // Notify user (non-blocking) so the submission appears in Notifikasi
    $notif_id = null;
    if (file_exists(__DIR__ . '/../../flutter_api/notif_helper.php')) {
        @include_once __DIR__ . '/../../flutter_api/notif_helper.php';
        if (function_exists('safe_create_notification')) {
            try {
                $notif_id = @safe_create_notification($con, (int)$id_pengguna, 'pinjaman', 'Pengajuan Pinjaman Diajukan', 'Pengajuan Pinjaman sebesar ' . ('Rp ' . number_format($total_int, 0, ',', '.')) . ' untuk tenor ' . intval($tenor_val) . ' bulan sedang menunggu persetujuan admin.', json_encode(['application_id' => (int)$insertId, 'amount' => (int)$total_int, 'tenor' => intval($tenor_val), 'status' => 'menunggu']));
            } catch (Throwable $_e) {
                @file_put_contents(__DIR__ . '/debug.log', date('Y-m-d H:i:s') . " NOTIF FAILED: " . $_e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            }
        }
    }

    http_response_code(201);
    echo json_encode([
        'status' => true,
        'message' => 'Pengajuan pinjaman berhasil',
        'id' => $insertId,
        'row' => $row,
        'notif_id' => $notif_id
    ]);
    exit;
}

// Fallback
ob_start();
// If we reach here, something unexpected happened; log captured output and return safe JSON
emit_json_error_and_exit(500, 'Gagal mengajukan pinjaman', $logPath, 'Unknown error');

} catch (Throwable $e) {
    // Log exception, headers and any captured output to debug log for easier diagnosis
    $err = '['.get_class($e).'] '.$e->getMessage();
    $headers_dump = function_exists('getallheaders') ? json_encode(getallheaders()) : json_encode([]);
    @file_put_contents($logPath, date('Y-m-d H:i:s') . " EXCEPTION: " . $err . "\nHeaders: " . $headers_dump . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND | LOCK_EX);
    emit_json_error_and_exit(500, 'Terjadi kesalahan server. Silakan coba lagi.', $logPath);
}
