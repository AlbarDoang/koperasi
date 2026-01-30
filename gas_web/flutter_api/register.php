<?php 
/**
 * API: Register Anggota Baru
 * Untuk registrasi anggota koperasi dari mobile app
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'connection.php';
include 'helpers.php';

// Register shutdown handler to capture shutdown errors and ensure JSON output
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register] SHUTDOWN: " . var_export($err, true) . "\n", FILE_APPEND);
        if (function_exists('sendJsonResponse') && empty($GLOBALS['FLUTTER_API_JSON_OUTPUT'])) {
            $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
            // Send a safe generic JSON error to the client
            sendJsonResponse(false, 'Internal server error');
        }
    }
});

// Log untuk memastikan endpoint ini terpanggil
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register] START from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND);
error_log("REGISTER API CALLED from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Pastikan koneksi database tersedia
if (!isset($connect) || !$connect) {
    sendJsonResponse(false, 'Database connection not available');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data: dukung beberapa variasi nama field yang mungkin dikirim Flutter
    $no_hp = getPostData('no_hp', getPostData('nohp', getPostData('username', '')));
    $kata_sandi_raw = getPostData('kata_sandi', getPostData('password', getPostData('pass', '')));
    $nama_lengkap = getPostData('nama_lengkap', getPostData('nama', getPostData('full_name', '')));
    $alamat_domisili = getPostData('alamat_domisili', getPostData('alamat', ''));
    $tanggal_lahir = getPostData('tanggal_lahir', getPostData('tgl_lahir', ''));
    $setuju_syarat = getPostData('setuju_syarat', '');

    // Normalize phone early so all checks use the same canonical form
    $no_hp = sanitizePhone(trim($no_hp));
    $digits = preg_replace('/[^0-9]/', '', $no_hp);
    
    // Validasi input (label mengikuti pola lama tanpa mengubah struktur respons)
    $required_fields = [
        'no_hp' => 'Nomor HP',
        'kata_sandi' => 'Password',
        'nama_lengkap' => 'Nama Lengkap',
        'alamat_domisili' => 'Alamat',
        'tanggal_lahir' => 'Tanggal Lahir'
    ];
    
    foreach ($required_fields as $field => $label) {
        // Periksa sesuai field alternatif
        $value = null;
        switch ($field) {
            case 'no_hp':
                $value = $no_hp;
                break;
            case 'kata_sandi':
                $value = $kata_sandi_raw;
                break;
            case 'nama_lengkap':
                $value = $nama_lengkap;
                break;
            case 'alamat_domisili':
                $value = $alamat_domisili;
                break;
            case 'tanggal_lahir':
                $value = $tanggal_lahir;
                break;
        }
        if (empty($value)) {
            sendJsonResponse(false, "$label wajib diisi");
        }
    }
    
    // Escape strings for legacy usage (we will use prepared statements below)
    if (is_object($connect)) {
        $no_hp_esc = $connect->real_escape_string($no_hp);
        $nama_esc = $connect->real_escape_string($nama_lengkap);
        $alamat_esc = $connect->real_escape_string($alamat_domisili);
        $tanggal_lahir_esc = $connect->real_escape_string($tanggal_lahir);
    } else {
        $no_hp_esc = mysqli_real_escape_string($connect, $no_hp);
        $nama_esc = mysqli_real_escape_string($connect, $nama_lengkap);
        $alamat_esc = mysqli_real_escape_string($connect, $alamat_domisili);
        $tanggal_lahir_esc = mysqli_real_escape_string($connect, $tanggal_lahir);
    }

    // Hash password securely
    $password_hash = password_hash($kata_sandi_raw, PASSWORD_DEFAULT);
    
    // Validasi format no HP (minimal 9 digit)
    if (strlen($digits) < 9) {
        sendJsonResponse(false, 'Nomor HP minimal 9 digit');
    }
    
    // Validasi password minimal 6 karakter
    if (strlen($kata_sandi_raw) < 6) {
        sendJsonResponse(false, 'Password minimal 6 karakter');
    }
    
    // Cek apakah no HP sudah terdaftar di tabel 'pengguna'
    $stmt_check = $connect->prepare("SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1");
    if ($stmt_check === false) {
        $err = $connect->error ?? mysqli_error($connect);
        error_log("REGISTER: prepare check failed: " . $err);
        sendJsonResponse(false, "Query prepare failed: " . $err);
    }
    $stmt_check->bind_param('s', $no_hp);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        // Duplicate detected - log to help debugging
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register] Duplicate phone detected: {$no_hp} from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n", FILE_APPEND);
        $stmt_check->close();
        sendJsonResponse(false, 'Nomor HP sudah terdaftar. Silakan login atau gunakan nomor lain.');
    }
    $stmt_check->close();
    
    // Start transaction
    $connect->begin_transaction();

    try {
        // Prepared statement insert into pengguna with specified columns
        $insertSql = "INSERT INTO pengguna (no_hp, kata_sandi, nama_lengkap, alamat_domisili, tanggal_lahir) VALUES (?, ?, ?, ?, ?)";
        $stmt = $connect->prepare($insertSql);
        if ($stmt === false) {
            $err = $connect->error ?? mysqli_error($connect);
            error_log("REGISTER: prepare insert failed: " . $err);
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register] prepare insert failed: " . $err . "\n", FILE_APPEND);
            $connect->rollback();
            sendJsonResponse(false, "Query prepare failed: " . $err);
        }

        $stmt->bind_param('sssss', $no_hp, $password_hash, $nama_lengkap, $alamat_domisili, $tanggal_lahir);

        $exec = $stmt->execute();
        if ($exec === false) {
            $err = $stmt->error ?: ($connect->error ?? mysqli_error($connect));
            error_log("REGISTER ERROR: execute insert failed: " . $err);
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register] execute failed: " . $err . "\n", FILE_APPEND);
            $stmt_errno = $stmt->errno ?? 0;
            $stmt->close();
            $connect->rollback();
            if ($stmt_errno == 1062) {
                // Duplicate key (race) — respond with user-friendly message
                sendJsonResponse(false, 'Nomor HP sudah terdaftar (konflik)');
            }
            sendJsonResponse(false, $err);
        }

        // Check affected rows
        $affected = $stmt->affected_rows;
        if ($affected <= 0) {
            $err = "affected_rows= " . $affected;
            error_log("REGISTER ERROR: insert affected_rows= " . $affected);
            $stmt->close();
            $connect->rollback();
            sendJsonResponse(false, $err);
        }

        $inserted_id = $connect->insert_id;
        $stmt->close();

        // Log insertion before commit
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register] inserted_id: " . $inserted_id . "\n", FILE_APPEND);

        // Commit transaction
        $connect->commit();
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [register] commit ok id: " . $inserted_id . "\n", FILE_APPEND);

        sendJsonResponse(true, 'Register berhasil', array('id' => $inserted_id, 'id_pengguna' => $inserted_id));

    } catch (Exception $e) {
        $connect->rollback();
        error_log("REGISTER: exception: " . $e->getMessage());
        sendJsonResponse(false, $e->getMessage());
    }
    
} else {
    sendJsonResponse(false, 'Method not allowed. Use POST');
}
