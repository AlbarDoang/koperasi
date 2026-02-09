<?php
/**
 * ============================================================================
 * API: Get Profil Pengguna
 * ============================================================================
 * 
 * File: flutter_api/get_profil.php
 * Tujuan: Mengambil data profil lengkap pengguna termasuk URL foto profil
 * Method: POST/GET
 * 
 * Parameters:
 *   - id_pengguna: ID pengguna (required)
 *   OR
 *   - no_hp: Nomor HP pengguna (alternative)
 * 
 * Response Success (200):
 * {
 *   "status": true,
 *   "message": "Data profil berhasil diambil",
 *   "data": {
 *     "id": "123",
 *     "no_hp": "62812345678",
 *     "nama_lengkap": "Budi Santoso",
 *     "alamat_domisili": "Jl. Merdeka No. 123",
 *     "tanggal_lahir": "2000-01-15",
 *     "status_akun": "approved",
 *     "created_at": "2024-01-10 10:30:00",
 *     "saldo": 150000,
 *     "foto_profil": "https://domain.com/uploads/foto_profil/123_1702000000.jpg",
 *     "foto_profil_thumb": "https://domain.com/uploads/foto_profil/thumb_123_1702000000.jpg"
 *   }
 * }
 * 
 * Response Jika tidak ada foto:
 * {
 *   "status": true,
 *   "data": {
 *     ...
 *     "foto_profil": null,
 *     "foto_profil_thumb": null
 *   }
 * }
 * 
 * Response Error (400/404):
 * {
 *   "status": false,
 *   "message": "Deskripsi error"
 * }
 * 
 * ============================================================================
 */

// ============================================================================
// 1. SETUP DAN KONFIGURASI
// ============================================================================

// Set header untuk JSON response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/api_bootstrap.php';

// Handle OPTIONS request (untuk CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Connection and helpers are available via api_bootstrap
    $conn = $connect ?? null;

// ============================================================================
// 2. VALIDASI KONEKSI DATABASE
// ============================================================================

$conn = getConnection();
if (!$conn) {
    sendJsonResponse(false, 'Koneksi database gagal');
}

// ============================================================================
// 3. AMBIL DAN VALIDASI INPUT
// ============================================================================

// Terima dari POST atau GET
$id_pengguna = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : 
               (isset($_GET['id_pengguna']) ? trim($_GET['id_pengguna']) : '');

$no_hp = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : 
         (isset($_GET['no_hp']) ? trim($_GET['no_hp']) : '');

// Validasi minimal satu parameter harus ada
if (empty($id_pengguna) && empty($no_hp)) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(422);
    echo json_encode(['status' => false, 'success' => false, 'message' => 'ID pengguna atau nomor HP harus disediakan']);
    exit();
}

// ============================================================================
// 4. QUERY DATABASE UNTUK AMBIL DATA PROFIL
// ============================================================================

// Helper to run queries and log SQL+error on failure (do not expose SQL to clients)
function runQueryWithLog($conn, $sql) {
    $res = @mysqli_query($conn, $sql);
    if ($res === false) {
        $err = mysqli_error($conn);
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_profil] Query failed: " . $err . "\nQUERY: " . $sql . "\n", FILE_APPEND);
    }
    return $res;
}

// Escape input untuk keamanan SQL injection
$id_escaped = mysqli_real_escape_string($conn, $id_pengguna);
$no_hp_escaped = mysqli_real_escape_string($conn, $no_hp);

// Tentukan kolom identifier yang tersedia di tabel `pengguna` (agar kompatibel dengan skema berbeda)
$identifier_column = 'id';
$cols_res = runQueryWithLog($conn, "SHOW COLUMNS FROM pengguna");
$cols = [];
if ($cols_res) {
    while ($c = mysqli_fetch_assoc($cols_res)) $cols[] = $c['Field'];
}
if (in_array('id', $cols)) $identifier_column = 'id';
elseif (in_array('id_pengguna', $cols)) $identifier_column = 'id_pengguna';
elseif (in_array('id_pengguna', $cols)) $identifier_column = 'id_pengguna';
elseif (count($cols) > 0) $identifier_column = $cols[0];
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_profil] Using identifier column: " . $identifier_column . "\n", FILE_APPEND);

// Build WHERE clause based on available parameter
$where_clause = '';
if (!empty($id_pengguna)) {
    // Use the discovered identifier column when matching by id_pengguna param
    $where_clause = "`" . $identifier_column . "` = '$id_escaped'";
} else {
    // Normalisasi nomor HP
    $no_hp_clean = preg_replace('/[^0-9]/', '', $no_hp);
    if (strlen($no_hp_clean) < 9) {
        sendJsonResponse(false, 'Format nomor HP tidak valid');
    }
    if (substr($no_hp_clean, 0, 1) === '0') {
        $no_hp_clean = '62' . substr($no_hp_clean, 1);
    }
    $no_hp_clean_escaped = mysqli_real_escape_string($conn, $no_hp_clean);
    $where_clause = "no_hp = '$no_hp_clean_escaped'";
}

// Prepare the id selection so downstream code can always rely on 'id' key
$id_select = "`" . $identifier_column . "` AS id";

// Query untuk mengambil data dari tabel pengguna
// SELECT kolom-kolom yang relevan untuk profil pengguna (tambahkan status_verifikasi jika tersedia)
$select_extra = '';
$has_verif = runQueryWithLog($conn, "SHOW COLUMNS FROM pengguna LIKE 'status_verifikasi'");
if ($has_verif && mysqli_num_rows($has_verif) > 0) {
    $select_extra = ', status_verifikasi';
}
$query = "SELECT 
            " . $id_select . ",
            no_hp,
            nama_lengkap,
            alamat_domisili,
            tanggal_lahir,
            status_akun,
            created_at,
            saldo,
            foto_profil" . $select_extra . "
          FROM pengguna
          WHERE $where_clause
          LIMIT 1";

// Eksekusi query
$result = runQueryWithLog($conn, $query);

// Validasi query berhasil
if (!$result) {
    sendJsonResponse(false, 'Error query database: ' . mysqli_error($conn));
}

// Validasi ada data yang ditemukan
if (mysqli_num_rows($result) === 0) {
    sendJsonResponse(false, 'Pengguna tidak ditemukan');
}

// Ambil data pengguna
$user_data = mysqli_fetch_assoc($result);

// ============================================================================
// 5. PROSES FOTO PROFIL - KONVERSI KE URL LENGKAP
// ============================================================================

// Jika ada nama file foto_profil, konversi ke URL lengkap
$foto_profil_url = null;
$foto_profil_thumb_url = null;

if (!empty($user_data['foto_profil'])) {
    // Ambil nama file dari kolom foto_profil
    $filename = $user_data['foto_profil'];
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];

    // Prefer per-user profile storage (outside document root) -> PROFILE_STORAGE_PHOTO/<user_id>/<filename>
    $per_user_candidate = PROFILE_STORAGE_PHOTO . ($user_data['id'] ?? '') . DIRECTORY_SEPARATOR . $filename;
    $profile_candidate = PROFILE_STORAGE_PHOTO . $filename; // older flat storage
    $legacy_candidate = dirname(__DIR__) . '/uploads/foto_profil/' . $filename; // legacy inside project

    $file_exists = false;
    $found_path = null;
    if (file_exists($per_user_candidate) && is_file($per_user_candidate)) {
        $file_exists = true;
        $found_path = $per_user_candidate;
    } elseif (file_exists($profile_candidate) && is_file($profile_candidate)) {
        $file_exists = true;
        $found_path = $profile_candidate;
    } elseif (file_exists($legacy_candidate) && is_file($legacy_candidate)) {
        $file_exists = true;
        $found_path = $legacy_candidate;
    }

    if ($file_exists) {
        // If we found the file in flat or legacy storage, try best-effort to copy/migrate it into per-user folder
        if ($found_path !== $per_user_candidate) {
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_profil] migrating file to per-user folder user_id=" . ($user_data['id'] ?? '') . " src=" . $found_path . " dst=" . $per_user_candidate . "\n", FILE_APPEND | LOCK_EX);
            // Ensure per-user dir exists
            @mkdir(dirname($per_user_candidate), 0755, true);
            // Attempt to copy (do not overwrite if exists)
            if (!file_exists($per_user_candidate)) {
                @copy($found_path, $per_user_candidate);
                @chmod($per_user_candidate, 0644);
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_profil] migrate_result=" . (file_exists($per_user_candidate) ? 'ok' : 'fail') . " user_id=" . ($user_data['id'] ?? '') . " filename={$filename}\n", FILE_APPEND | LOCK_EX);
                // Optional: remove old flat file (best-effort, do not fail on error)
                // @unlink($found_path);
                // Update found_path to per-user path so subsequent logic uses it
                $found_path = $per_user_candidate;
            }
        }

        // Log which path we found (per-user / flat / legacy) to help debugging storage location issues
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_profil] found_path={$found_path} user_id=" . ($user_data['id'] ?? '') . " filename={$filename}\n", FILE_APPEND | LOCK_EX);

        // Issue a short-lived signed URL to the proxy endpoint so mobile clients can fetch images
        $exp = time() + 300; // 5 minutes
        $payload = ($user_data['id'] ?? '') . ':' . $filename . ':' . $exp;
        $sig = hash_hmac('sha256', $payload, PROFILE_IMAGE_SECRET);
        $foto_profil_url = $protocol . $host . '/gas/gas_web/login/user/foto_profil_image.php?id=' . urlencode($user_data['id']) . '&exp=' . $exp . '&sig=' . $sig;

        // Thumbnail support: check per-user folder first, then flat and legacy locations
        $thumb_filename = 'thumb_' . $filename;
        $thumb_per_user = PROFILE_STORAGE_PHOTO . ($user_data['id'] ?? '') . DIRECTORY_SEPARATOR . $thumb_filename;
        $thumb_flat = PROFILE_STORAGE_PHOTO . $thumb_filename;
        $thumb_legacy = dirname(__DIR__) . '/uploads/foto_profil/' . $thumb_filename;
        if (file_exists($thumb_per_user) || file_exists($thumb_flat) || file_exists($thumb_legacy)) {
            $exp2 = time() + 300;
            $payload2 = ($user_data['id'] ?? '') . ':' . $thumb_filename . ':' . $exp2;
            $sig2 = hash_hmac('sha256', $payload2, PROFILE_IMAGE_SECRET);
            $foto_profil_thumb_url = $protocol . $host . '/gas/gas_web/login/user/foto_profil_image.php?id=' . urlencode($user_data['id']) . '&exp=' . $exp2 . '&sig=' . $sig2 . '&thumb=1';
        } else {
            $foto_profil_thumb_url = null;
        }
    } else {
        // File tidak ditemukan
        $foto_profil_url = null;
        $foto_profil_thumb_url = null;
    }
}

// ============================================================================
// 6. SIAPKAN RESPONSE DATA
// ============================================================================

// Hitung saldo berdasarkan transaksi (transaksi -> jumlah_masuk - jumlah_keluar)
$saldo_calculated = null;
$id_tabungan_val = $user_data['id_tabungan'] ?? ($user_data['nis'] ?? '');
// Jika tabel transaksi ada dan id_tabungan/nis tersedia, hitung saldo dari transaksi menggunakan helper
if (!empty($id_tabungan_val)) {
    require_once dirname(__DIR__) . '/flutter_api/helpers.php';
    $safeSum = safe_sum_transaksi($conn, $id_tabungan_val);
    if ($safeSum !== null) {
        $saldo_calculated = floatval($safeSum['saldo']);
        @file_put_contents(dirname(__DIR__) . '/flutter_api/logs/get_profil_calc.log', json_encode(['ts' => date('c'), 'id' => $user_data['id'] ?? null, 'id_tabungan' => $id_tabungan_val, 'saldo_calc' => $saldo_calculated, 'sql' => 'safe_sum_transaksi']) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
// Fallback: if there is tabungan table with jenis columns, calculate from tabungan
if ($saldo_calculated === null) {
    $check_tabungan = runQueryWithLog($conn, "SHOW TABLES LIKE 'tabungan'");
    if ($check_tabungan && mysqli_num_rows($check_tabungan) > 0) {
        $userIdCol = 'id';
        // try to find id_pengguna on pengguna table to join
        $sql_tabungan = "SELECT COALESCE(SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE 0 END), 0) as total_masuk, COALESCE(SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END), 0) as total_keluar FROM tabungan WHERE ";
        if (isset($user_data['id_pengguna'])) {
            $sql_tabungan .= "id_pengguna='" . mysqli_real_escape_string($conn, $user_data['id_pengguna']) . "'";
        } else if (isset($user_data['id'])) {
            // Try to match if tabungan.id_pengguna exists
            $check_idPengguna = runQueryWithLog($conn, "SHOW COLUMNS FROM tabungan LIKE 'id_pengguna'");
            if ($check_idPengguna && mysqli_num_rows($check_idPengguna) > 0) {
                $sql_tabungan .= "id_pengguna='" . mysqli_real_escape_string($conn, $user_data['id']) . "'";
            } else {
                $sql_tabungan .= "id_tabungan='" . mysqli_real_escape_string($conn, $id_tabungan_val) . "'";
            }
        } else {
            $sql_tabungan .= "id_tabungan='" . mysqli_real_escape_string($conn, $id_tabungan_val) . "'";
        }
        $res_tabungan = runQueryWithLog($conn, $sql_tabungan);
        if ($res_tabungan && mysqli_num_rows($res_tabungan) > 0) {
            $row_tabungan = mysqli_fetch_assoc($res_tabungan);
            $saldo_calculated = floatval($row_tabungan['total_masuk']) - floatval($row_tabungan['total_keluar']);
            @file_put_contents(dirname(__DIR__) . '/flutter_api/logs/get_profil_calc.log', json_encode(['ts' => date('c'), 'id' => $user_data['id'] ?? null, 'id_tabungan' => $id_tabungan_val, 'saldo_calc' => $saldo_calculated, 'sql' => $sql_tabungan]) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

// If still null, fallback to pengguna.saldo
$saldo_db = intval($user_data['saldo'] ?? 0);
if ($saldo_calculated === null) {
    $saldo_calculated = $saldo_db;
}

// Update pengguna.saldo if mismatched to keep DB consistent
if (intval($saldo_calculated) !== intval($saldo_db)) {
    // Prefer 'id' column; fallback to 'id_pengguna' or 'id_pengguna' if needed.
    $userIdValue = $user_data['id'] ?? ($user_data['id_pengguna'] ?? ($user_data['id_pengguna'] ?? null));
    $idColumn = null;
    if ($userIdValue !== null) {
        if (isset($user_data['id'])) $idColumn = 'id';
        elseif (isset($user_data['id_pengguna'])) $idColumn = 'id_pengguna';
        elseif (isset($user_data['id_pengguna'])) $idColumn = 'id_pengguna';
    }
    if ($idColumn !== null) {
        $safe_id = mysqli_real_escape_string($conn, $userIdValue);
        $upd = runQueryWithLog($conn, "UPDATE pengguna SET saldo='" . intval($saldo_calculated) . "' WHERE $idColumn='" . $safe_id . "' LIMIT 1");
        // Refresh user_data saldo value
        $user_data['saldo'] = intval($saldo_calculated);
    }
}

// Ambil data verifikasi (foto KTP / selfie) dari tabel verifikasi_pengguna (jika ada)
$foto_ktp_url = null;
$foto_selfie_url = null;
if (!empty($user_data['id'])) {
    $safeId = mysqli_real_escape_string($conn, $user_data['id']);
    // Determine a safe column to order by (not all schemas have 'id')
    $order_col = 'created_at';
    $cols_res = runQueryWithLog($conn, "SHOW COLUMNS FROM verifikasi_pengguna");
    $cols = [];
    if ($cols_res) {
        while ($c = mysqli_fetch_assoc($cols_res)) $cols[] = $c['Field'];
        if (in_array('id', $cols)) $order_col = 'id';
        elseif (in_array('updated_at', $cols)) $order_col = 'updated_at';
        elseif (in_array('created_at', $cols)) $order_col = 'created_at';
        elseif (count($cols) > 0) $order_col = $cols[0];
    }
    // ensure the column name is safe (simple whitelist by regex)
    if (!preg_match('/^[A-Za-z0-9_]+$/', $order_col)) $order_col = 'created_at';

    // Determine verifikasi table id column and select it as ver_id (schema-agnostic)
    $ver_id_col = null;
    if (!empty($cols)) {
        if (in_array('id', $cols)) $ver_id_col = 'id';
        elseif (in_array('id_verifikasi', $cols)) $ver_id_col = 'id_verifikasi';
        elseif (in_array('verifikasi_id', $cols)) $ver_id_col = 'verifikasi_id';
        else {
            // pick first column that is not id_pengguna
            foreach ($cols as $c) {
                if ($c !== 'id_pengguna') { $ver_id_col = $c; break; }
            }
            if ($ver_id_col === null) $ver_id_col = $cols[0];
        }
    } else {
        $ver_id_col = 'id';
    }
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_profil] Using verifikasi id column: " . $ver_id_col . "\n", FILE_APPEND);

    $ver_id_select = "`" . $ver_id_col . "` AS ver_id";
    $res_ver = runQueryWithLog($conn, "SELECT " . $ver_id_select . ", foto_ktp, foto_selfie FROM verifikasi_pengguna WHERE id_pengguna='" . $safeId . "' ORDER BY " . $order_col . " DESC LIMIT 1");
    if ($res_ver && mysqli_num_rows($res_ver) > 0) {
        $row_ver = mysqli_fetch_assoc($res_ver);
            $ktpPath = $row_ver['foto_ktp'] ?? null;
        $selfiePath = $row_ver['foto_selfie'] ?? null;
        $ver_id = $row_ver['ver_id'] ?? null;
        // Ensure ver_id is numeric (avoid leaking file paths as ver_id)
        if (!is_numeric($ver_id)) {
            $ver_id = null;
        }

        // Do NOT expose direct filesystem paths or public URLs to mobile clients.
        // Instead, provide 'has_ktp' and 'has_selfie' booleans and the verification id for admin views.
        $foto_ktp_url = null;
        $foto_selfie_url = null;
        $has_ktp = false;
        $has_selfie = false;

        // Do not expose filesystem paths in API responses. We will only report has_ktp/has_selfie and verifikasi_id.
        if (session_status() === PHP_SESSION_NONE) session_start();
        $isAdmin = isset($_SESSION['akses']) && $_SESSION['akses'] === 'admin';

        if (!empty($ktpPath)) {
            // If path is absolute and file exists, report true
            if (file_exists($ktpPath) || file_exists(dirname(__DIR__) . '/flutter_api/foto_verifikasi/' . basename($ktpPath))) {
                $has_ktp = true;
            }
        }
        if (!empty($selfiePath)) {
            if (file_exists($selfiePath) || file_exists(dirname(__DIR__) . '/flutter_api/foto_verifikasi/' . basename($selfiePath))) {
                $has_selfie = true;
            }
        }
    } else {
        // No verification record - leave URLs null (no error)
        $has_ktp = false;
        $has_selfie = false;
        $ver_id = null;
    }
}

// Buat array data profil yang akan dikembalikan
$profile_data = [
    'id' => $user_data['id'],
    'no_hp' => $user_data['no_hp'],
    'nama_lengkap' => $user_data['nama_lengkap'],
    'alamat_domisili' => $user_data['alamat_domisili'],
    'tanggal_lahir' => $user_data['tanggal_lahir'],
    'status_akun' => $user_data['status_akun'],
    'created_at' => $user_data['created_at'],
    // Always return calculated saldo to clients so UI shows real-time balance
    'saldo' => intval($saldo_calculated),
    'saldo_calculated' => intval($saldo_calculated),
    'foto_profil' => $foto_profil_url,  // null atau URL lengkap
    // Backwards-compatibility: provide 'foto' key used by mobile client (full signed URL)
    'foto' => $foto_profil_url,
    'foto_profil_thumb' => $foto_profil_thumb_url, // null atau URL lengkap
    'foto_profil_updated_at' => (isset($user_data['foto_profil_updated_at']) ? intval($user_data['foto_profil_updated_at']) : null),
    'foto_ktp' => null, // intentionally not exposing direct URLs to mobile clients
    'foto_selfie' => null,
    'verifikasi_id' => $ver_id ?? null,
    'has_ktp' => (isset($has_ktp) ? (bool)$has_ktp : false),
    'has_selfie' => (isset($has_selfie) ? (bool)$has_selfie : false),
    'status_verifikasi' => $user_data['status_verifikasi'] ?? null
];

// ============================================================================
// 7. RESPONSE SUKSES
// ============================================================================

sendJsonResponse(true, 'Data profil berhasil diambil', array('data' => $profile_data));

} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_profil] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    sendJsonResponse(false, $e->getMessage());
}


