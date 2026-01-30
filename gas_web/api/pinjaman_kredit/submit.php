<?php
// api/pinjaman_kredit/submit.php
// Submit a pinjaman_kredit application (multi-step form final submit)
// - Validates inputs
// - Computes pokok, cicilan_per_bulan, total_bayar
// - Stores row in pinjaman_kredit table
// - Handles optional foto_barang upload

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

$logPath = __DIR__ . '/debug.log';
ob_start();

require_once __DIR__ . '/../../config/db.php';

function emit_json($code, $payload) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        emit_json(405, ['status' => false, 'message' => 'Method Not Allowed']);
    }

    // Accept JSON or form-data
    $input = $_POST;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (empty($input) && stripos((string)$contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $input = $decoded;
    }

    // Required fields
    $id_pengguna = isset($input['id_pengguna']) ? (int)$input['id_pengguna'] : 0;
    $nama_barang = isset($input['nama_barang']) ? trim((string)$input['nama_barang']) : '';
    $harga = isset($input['harga']) ? (float)$input['harga'] : 0.0;
    $dp = isset($input['dp']) ? (float)$input['dp'] : 0.0;
    $tenor = isset($input['tenor']) ? (int)$input['tenor'] : 0;
    $accepted_terms = isset($input['accepted_terms']) ? (bool)$input['accepted_terms'] : false;

    // Validate inputs
    if ($id_pengguna <= 0) emit_json(400, ['status' => false, 'message' => 'Invalid id_pengguna']);
    // Ensure pengguna exists
    $checkUser = @mysqli_query($con, "SELECT 1 FROM pengguna WHERE id = " . intval($id_pengguna) . " LIMIT 1");
    if (!$checkUser || mysqli_num_rows($checkUser) === 0) emit_json(400, ['status'=>false,'message'=>'ID pengguna tidak ditemukan']);
    if ($nama_barang === '') emit_json(400, ['status' => false, 'message' => 'Nama barang wajib diisi']);
    if ($harga <= 0) emit_json(400, ['status' => false, 'message' => 'Harga barang harus > 0']);
    if ($dp < 0) emit_json(400, ['status' => false, 'message' => 'DP tidak boleh negatif']);
    if ($dp >= $harga) emit_json(400, ['status' => false, 'message' => 'DP harus lebih kecil dari harga barang']);
    if ($tenor <= 0) emit_json(400, ['status' => false, 'message' => 'Tenor tidak valid']);
    if ($tenor > 12) emit_json(400, ['status' => false, 'message' => 'Tenor maksimal 12 bulan']);
    if (!$accepted_terms) emit_json(400, ['status' => false, 'message' => 'Anda harus menyetujui Syarat & Ketentuan']);

    // Compute fields under SYARIAH FLAT rules (no interest/margin)
    // Validations
    if ($tenor <= 0) emit_json(400, ['status'=>false,'message'=>'Tenor harus lebih besar dari 0']);
    if ($harga <= 0) emit_json(400, ['status'=>false,'message'=>'Harga barang harus lebih besar dari 0']);
    if ($dp < 0) emit_json(400, ['status'=>false,'message'=>'DP tidak boleh negatif']);
    if ($dp > $harga) emit_json(400, ['status'=>false,'message'=>'DP tidak boleh lebih besar dari harga barang']);

    // Use integer arithmetic with floor rounding (pembulatan ke bawah)
    $pokok = (int)floor(max(0.0, $harga - $dp));
    if ($pokok <= 0) emit_json(400, ['status'=>false,'message'=>'Total pembiayaan (harga - dp) harus > 0']);

    $base = intdiv($pokok, max(1, $tenor));
$cicilan_per_bulan = (int)$base; // flat monthly installment (same every month)
// Per syariah rule: ignore any remainder; total_dibayar for the financed part is cicilan_per_bulan * tenor
$total_bayar = ($cicilan_per_bulan * max(1, $tenor)) + (int)$dp;
// Ensure non-negative
if ($cicilan_per_bulan < 0) emit_json(400, ['status'=>false,'message'=>'Cicilan tidak boleh negatif']);
// Log calc
@file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: Computed cicilan base={$base} dp={$dp} total_bayar={$total_bayar}\n", FILE_APPEND);
    if (!$accepted_terms) emit_json(400, ['status'=>false,'message'=>'Anda harus menyetujui Syarat & Ketentuan']);

    // Handle optional file upload (foto_barang) and optional link
    $foto_path_db = null;
    if (!empty($_FILES['foto_barang']) && is_uploaded_file($_FILES['foto_barang']['tmp_name'])) {
        $f = $_FILES['foto_barang'];
        // Allowed MIME types (strict)
        $allowed = ['image/jpeg', 'image/png'];
        $detected = null;
        if (!empty($f['tmp_name']) && is_uploaded_file($f['tmp_name'])) {
            if (function_exists('finfo_file')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detected = finfo_file($finfo, $f['tmp_name']);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $detected = mime_content_type($f['tmp_name']);
            } else {
                $detected = $f['type'] ?? '';
            }
        }
        $detected = strtolower((string)$detected);
        // Log upload detection details for debugging
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: user={$id_pengguna} name={$f['name']} size={$f['size']} client_type={$f['type']} detected={$detected}\n", FILE_APPEND);

        // Validate MIME or extension
        if (!in_array($detected, $allowed, true)) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png'], true)) {
                emit_json(400, ['status'=>false,'message'=>'Format foto barang tidak didukung (jpeg/png)']);
            }
            // normalize detected mime from extension
            $detected = ($ext === 'png') ? 'image/png' : 'image/jpeg';
        }

        // Enforce size limit using LOAN_MAX_FILE_SIZE
        if ($f['size'] > (defined('LOAN_MAX_FILE_SIZE') ? LOAN_MAX_FILE_SIZE : 5 * 1024 * 1024)) emit_json(400, ['status'=>false,'message'=>'Foto barang terlalu besar (maks ' . ((defined('LOAN_MAX_FILE_SIZE') ? LOAN_MAX_FILE_SIZE : 5 * 1024 * 1024)/(1024*1024)) . 'MB)']);

        // Load storage helpers
        require_once __DIR__ . '/../../flutter_api/storage_config.php';

        // Use id_pengguna-based folder (do NOT rely on username). This keeps storage consistent by id.
        $uid = intval($id_pengguna);
        $userFolder = LOAN_STORAGE_BASE . $uid . DIRECTORY_SEPARATOR;
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: Ensuring loan folder user={$uid} folder={$userFolder}\n", FILE_APPEND);
        if (!loan_ensure_dir($userFolder)) {
            @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: Failed to create loan folder user={$uid} folder={$userFolder}\n", FILE_APPEND);
            emit_json(500, ['status'=>false,'message'=>'Gagal membuat folder penyimpanan foto barang']);
        }
        // Best-effort: try to migrate root loan files into this user's folder
        try {
            $m = loan_migrate_root_files_to_user($uid);
            if (!empty($m)) @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: Migrated existing loan files into user folder: " . json_encode($m) . "\n", FILE_APPEND);
        } catch (Throwable $e) { @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: loan migration failed: " . $e->getMessage() . "\n", FILE_APPEND); }

        // Generate safe filename: barang_<timestamp>[_<rand>].ext (no raw user input)
        $ext = ($detected === 'image/png') ? 'png' : 'jpg';
        try { $rand = bin2hex(random_bytes(6)); } catch (Throwable $e) { $rand = substr(md5(uniqid('', true)), 0, 12); }
        $safeName = 'barang_' . time() . '_' . $rand . '.' . $ext;
        $dst = $userFolder . $safeName;

        @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: Moving foto_barang tmp={$f['tmp_name']} -> dst={$dst}\n", FILE_APPEND);
        if (!move_uploaded_file($f['tmp_name'], $dst)) {
            @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: move_uploaded_file failed for dst={$dst} error=" . var_export(error_get_last(), true) . "\n", FILE_APPEND);
            emit_json(500, ['status'=>false,'message'=>'Gagal menyimpan foto barang ke storage eksternal']);
        }
        @chmod($dst, 0644);

        // store only the filename in DB (not path) to preserve existing schema and admin proxies
        $foto_path_db = $safeName;
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " UPLOAD: Saved foto_barang as {$safeName} for user={$uid}\n", FILE_APPEND);
    }

    // link_bukti_harga column doesn't exist in schema; omit from insert
    // $link_bukti = null;

    // Insert into database (dynamic columns to support DB variants)
    // Detect which columns are available in the pinjaman_kredit table and build the INSERT accordingly
    $colsRes = @mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pinjaman_kredit'");
    $availableCols = [];
    if ($colsRes) {
        while ($cr = mysqli_fetch_assoc($colsRes)) {
            $availableCols[] = $cr['COLUMN_NAME'];
        }
    }

    $baseCols = ['id_pengguna','nama_barang','harga','dp','pokok','tenor','cicilan_per_bulan','total_bayar'];
    $optionalCols = [];
    if (in_array('foto_barang', $availableCols, true)) $optionalCols[] = 'foto_barang';
    // link_bukti_harga not in current schema; skip it

    $allCols = array_merge($baseCols, $optionalCols);
    $placeholders = implode(',', array_fill(0, count($allCols), '?'));
    $colList = '`' . implode('`,`', $allCols) . '`';

    $sql = "INSERT INTO `pinjaman_kredit` ($colList, status, created_at) VALUES ($placeholders, 'pending', NOW())";
    @file_put_contents($logPath, date('Y-m-d H:i:s') . " DEBUG: Preparing INSERT SQL: " . $sql . " params=" . json_encode($params) . "\n", FILE_APPEND);
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " DB prepare failed: " . mysqli_error($con) . "\n", FILE_APPEND);
        emit_json(500, ['status'=>false,'message'=>'DB prepare failed','error'=>mysqli_error($con)]);
    }

    // Build bind types and params in the correct order
    $types = '';
    $params = [];
    foreach ($allCols as $c) {
        switch ($c) {
            case 'id_pengguna': $types .= 'i'; $params[] = $id_pengguna; break;
            case 'nama_barang': $types .= 's'; $params[] = $nama_barang; break;
            case 'harga': $types .= 'd'; $params[] = $harga; break;
            case 'dp': $types .= 'd'; $params[] = $dp; break;
            case 'pokok': $types .= 'i'; $params[] = $pokok; break;
            case 'tenor': $types .= 'i'; $params[] = $tenor; break;
            case 'cicilan_per_bulan': $types .= 'i'; $params[] = $cicilan_per_bulan; break;
            case 'total_bayar': $types .= 'i'; $params[] = $total_bayar; break;
            case 'foto_barang': $types .= 's'; $params[] = $foto_path_db; break;
            // link_bukti_harga not in schema; skip
            default: break;
        }
    }

    // Bind parameters (call_user_func_array requires references)
    $bindParams = [];
    $bindParams[] = $stmt;
    $bindParams[] = $types;
    for ($i = 0; $i < count($params); $i++) {
        // use references for each param
        $bindParams[] = &$params[$i];
    }
    $bindResult = call_user_func_array('mysqli_stmt_bind_param', $bindParams);
    if ($bindResult === false) {
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " DB bind failed: " . mysqli_error($con) . "\n", FILE_APPEND);
        emit_json(500, ['status'=>false,'message'=>'DB bind failed','error'=>mysqli_error($con)]);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $stmtErr = mysqli_stmt_error($stmt);
        @file_put_contents($logPath, date('Y-m-d H:i:s') . " DB execute failed: " . $stmtErr . "\n", FILE_APPEND);
        emit_json(500, ['status'=>false,'message'=>'Gagal menyimpan pengajuan','error'=>$stmtErr]);
    }

    $insertId = mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    // Insert an initial log record to capture the creation event (immutable audit trail)
    $safeId = (int)$insertId;
    // Ensure log table exists (create if missing) to avoid errors on older DBs
    $checkLog = @mysqli_query($con, "SHOW TABLES LIKE 'pinjaman_kredit_log'");
    if (!$checkLog || mysqli_num_rows($checkLog) === 0) {
        $ctSql = "CREATE TABLE IF NOT EXISTS `pinjaman_kredit_log` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `pinjaman_id` INT NOT NULL,
            `previous_status` VARCHAR(64) DEFAULT NULL,
            `new_status` VARCHAR(64) DEFAULT NULL,
            `changed_by` INT DEFAULT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `note` TEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`pinjaman_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!@mysqli_query($con, $ctSql)) {
            @file_put_contents($logPath, date('Y-m-d H:i:s') . " Failed creating log table: " . mysqli_error($con) . "\n", FILE_APPEND);
        } else {
            @file_put_contents($logPath, date('Y-m-d H:i:s') . " Created missing pinjaman_kredit_log table\n", FILE_APPEND);
        }
    }

    $insLog = "INSERT INTO pinjaman_kredit_log (pinjaman_id, previous_status, new_status, changed_by, reason, note, created_at) VALUES ($safeId, 'created', 'pending', NULL, NULL, 'Initial submission', NOW())";
    @mysqli_query($con, $insLog);

    // Notify user (non-blocking)
    if (file_exists(__DIR__ . '/../../flutter_api/notif_helper.php')) {
        @include_once __DIR__ . '/../../flutter_api/notif_helper.php';
        if (function_exists('safe_create_notification')) {
            @safe_create_notification($con, $id_pengguna, 'pinjaman_kredit', 'Pengajuan kredit Anda sedang diverifikasi oleh admin.', 'Pengajuan kredit Anda sedang diverifikasi oleh admin.', json_encode(['application_id' => $safeId]));
        }
    }

    emit_json(201, ['status'=>true,'message'=>'Pengajuan kredit Anda sedang diverifikasi oleh admin.','id'=>$insertId]);

} catch (Throwable $e) {
    $captured = trim((string)ob_get_clean());
    @file_put_contents($logPath, date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n" . $captured . "\n", FILE_APPEND);
    emit_json(500, ['status'=>false,'message'=>'Terjadi kesalahan server. Silakan coba lagi.']);
}
