<?php
// Bersihkan output buffer dan cegah error HTML bocor ke client
ob_clean();
error_reporting(E_ALL);
ini_set('display_errors', '0');
/**
 * API: Aktivasi Akun (Request OTP)
 * 
 * Endpoint untuk mengirim OTP ke pengguna yang sudah terdaftar.
 * Flow:
 * 1. Terima input no_hp
 * 2. Cek apakah no_hp terdaftar dan status_akun = PENDING
 * 3. Generate OTP 6 digit random
 * 4. Simpan ke tabel otp_codes (status = 'belum')
 * 5. Return JSON dengan success=true dan optional otp_debug
 * 
 * PENTING: Tidak ada UPDATE status_akun di file ini.
 * Status_akun hanya berubah saat user verifikasi OTP di verifikasi_otp.php
 */


header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Include centralized Fonnte configuration (fallback if not loaded via api_bootstrap)
if (!defined('FONNTE_TOKEN')) {
    $fonnte_config_path = __DIR__ . '/../config/fonnte_constants.php';
    if (file_exists($fonnte_config_path)) {
        require_once $fonnte_config_path;
    }
}

// Simple file-based log for DB and integration errors (append-only)
$logFile = __DIR__ . '/log_db.txt';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Use centralized helpers: normalizePhoneNumber(), phone_to_international62(), and sanitizePhone() in helpers.php
// Removed local duplicate implementation to avoid conflicts and ensure single source of truth for phone normalization.

// ============================================================================
// FUNGSI: Kirim OTP ke WhatsApp via Fonnte API
// ============================================================================
/**
 * Kirim OTP ke nomor HP melalui Fonnte API (WhatsApp)
 * 
 * @param string $target Nomor HP tujuan (format: 62xxxx)
 * @param string $otp Kode OTP 6 digit
 * @return array Array dengan key: 'success' (bool), 'message' (string), 'response' (raw)
 */
function sendOTPViaCURL($target, $otp) {
    // Persiapan pesan OTP (singkat, formal)
    // Format yang lebih sederhana untuk menghindari spam filter operator
    $message = "Kode OTP: $otp. Berlaku 1 menit. Jangan bagikan kode ini.";

    // Gunakan token Fonnte WA Admin - PASTIKAN DARI otp_helper.php atau config
    // Jika belum, define default fallback (tapi harus from centralized config idealnya)
    if (!defined('FONNTE_TOKEN')) {
        @file_put_contents(__DIR__ . '/log_otp_fonte.txt', date('c') . " - ERROR: FONNTE_TOKEN not defined\n", FILE_APPEND);
        return array('success' => false, 'message' => 'Token pengiriman WhatsApp belum dikonfigurasi pada server', 'response' => null);
    }
    $fonnte_token = FONNTE_TOKEN;

    // Validasi token
    if (empty($fonnte_token)) {
        @file_put_contents(__DIR__ . '/log_otp_fonte.txt', date('c') . " - ERROR: Fonnte token kosong\n", FILE_APPEND);
        return array('success' => false, 'message' => 'Token pengiriman WhatsApp belum dikonfigurasi pada server', 'response' => null);
    }

    // Sanitasi nomor tujuan: hapus karakter non-digit
    $target_clean = preg_replace('/[^0-9]/', '', (string)$target);
    // Pastikan format internasional 62...
    if (substr($target_clean, 0, 1) === '0') {
        $target_clean = '62' . substr($target_clean, 1);
    }
    if (!preg_match('/^62\d{8,13}$/', $target_clean)) {
        @file_put_contents(__DIR__ . '/log_otp_fonte.txt', date('c') . " - ERROR: Nomor tujuan tidak valid: " . $target . " -> " . $target_clean . "\n", FILE_APPEND);
        return array('success' => false, 'message' => 'Nomor tujuan tidak valid untuk WhatsApp (harus dalam format 628xxxxxxxx)', 'response' => null);
    }

    // Siapkan payload url-encoded (agar Fonnte langsung kirim tanpa queue)
    $postFields = http_build_query(array(
        'target' => $target_clean,
        'message' => $message,
        'countryCode' => '62',
        'delay' => '0',
        'typing' => 'false',
        'connectOnly' => 'true',
        'preview' => 'false'
    ));

    // Inisialisasi cURL dengan endpoint yang benar
    if (!defined('FONNTE_API_ENDPOINT')) {
        define('FONNTE_API_ENDPOINT', 'https://api.fonnte.com/send');
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => FONNTE_API_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $fonnte_token
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));

    // Eksekusi cURL
    $response = curl_exec($curl);
    $curl_error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    // Logging: simpan request/response (jangan menyimpan token)
    $logEntry = date('c') . " - FONNTE_SEND target={$target_clean} http_code={$http_code} curl_err=" . ($curl_error ?: '') . "\n";
    @file_put_contents(__DIR__ . '/log_otp_fonte.txt', $logEntry, FILE_APPEND);

    if ($curl_error) {
        return array('success' => false, 'message' => 'cURL Error: ' . $curl_error, 'response' => $response);
    }

    // Validasi: response diharapkan format JSON
    $result = json_decode($response, true);
    if (!$result || !is_array($result)) {
        return array('success' => false, 'message' => 'Fonte API tidak mengembalikan JSON yang valid', 'response' => $response);
    }

    // Cek status/response dari Fonte (terima beberapa variasi)
    $statusVal = $result['status'] ?? null;
    $isSuccessStatus = ($statusVal === true) || ($statusVal === 'success') || ($statusVal === 1) || (is_string($statusVal) && strtolower($statusVal) === 'ok');
    if (!$isSuccessStatus) {
        $reason = $result['reason'] ?? ($result['message'] ?? 'Unknown error');
        @file_put_contents(__DIR__ . '/log_otp_fonte.txt', date('c') . " - FONNTE_RESPONSE_ERROR reason=" . (string)$reason . " raw=" . substr((string)$response,0,2000) . "\n", FILE_APPEND);
        return array('success' => false, 'message' => 'Fonte API Error: ' . $reason, 'response' => $response);
    }

    // Success
    @file_put_contents(__DIR__ . '/log_otp_fonte.txt', date('c') . " - FONNTE_SEND_SUCCESS target={$target_clean} response=" . substr((string)$response,0,2000) . "\n", FILE_APPEND);
    return array('success' => true, 'message' => 'OTP berhasil dikirim ke WhatsApp', 'response' => $response);
}


// Pastikan tidak ada whitespace dari file connection.php dan helpers.php
require_once('api_bootstrap.php');


try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = getPostData('action') ?? 'send_otp';
        
        // ===== ACTION: send_otp =====
        if ($action === 'send_otp') {
            $raw_no_hp = getPostData('no_hp');
            $no_hp = $raw_no_hp;

            // Validasi: input wajib
            if (empty($no_hp)) {
                http_response_code(400);
                sendJsonResponse(false, 'Nomor HP wajib diisi');
            }

            $no_hp = trim($no_hp);

            // Prepare canonical forms: local (08...) for DB lookup and international (62...) for sending
            $no_hp_local = sanitizePhone($no_hp);
            if (empty($no_hp_local)) {
                http_response_code(400);
                sendJsonResponse(false, 'Format nomor HP tidak valid. Masukkan 08... atau 62...');
            }
            $no_hp_int = phone_to_international62($no_hp);
            if ($no_hp_int === false) {
                http_response_code(400);
                sendJsonResponse(false, 'Nomor HP tidak valid');
            }

            // Cek apakah nomor HP terdaftar di tabel pengguna (DB menyimpan 08...)
            $sql_check = "SELECT id, no_hp, status_akun FROM pengguna WHERE no_hp = ? LIMIT 1";
            $stmt_check = $connect->prepare($sql_check);
            if (!$stmt_check) {
                $err = "Prepare stmt_check gagal: " . $connect->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                throw new Exception($err);
            }
            $stmt_check->bind_param('s', $no_hp_local);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            
            if ($result_check->num_rows === 0) {
                $stmt_check->close();
                http_response_code(404);
                sendJsonResponse(false, 'Nomor HP tidak terdaftar. Silakan daftar terlebih dahulu.');
            }
            $user = $result_check->fetch_assoc();
            $stmt_check->close();

            // Gunakan nomor yang tersimpan di DB (local) dan siapkan nomor internasional untuk OTP
            $no_hp_stored = $user['no_hp'];
            $no_hp = $no_hp_int; // international format untuk pengiriman dan penyimpanan OTP

            $status_akun_norm = strtolower(trim($user['status_akun'] ?? ''));
            if ($status_akun_norm === 'approved') {
                http_response_code(400);
                sendJsonResponse(false, 'Akun Anda sudah diaktivasi. Silakan login.');
            }
            if (in_array($status_akun_norm, ['ditolak','rejected'])) {
                http_response_code(403);
                sendJsonResponse(false, 'Akun Anda ditolak. Hubungi admin untuk informasi lebih lanjut.');
            }

            // Status PENDING - generate dan simpan OTP
            // Generate OTP 6 digit
            $otp = sprintf('%06d', random_int(0, 999999));
            $expired_at = date('Y-m-d H:i:s', strtotime('+1 minute'));

            // Normalisasi nomor HP ke format internasional (62xxxxxxxx)
            $target = normalizePhoneNumber($no_hp);
            if ($target === false) {
                http_response_code(400);
                sendJsonResponse(false, 'Format nomor HP tidak valid untuk pengiriman WhatsApp');
            }

            // ========================================================================
            // KIRIM OTP VIA FONTRE - SYNCHRONOUS (BLOKIR SAMPAI BERHASIL/GAGAL)
            // ========================================================================
            // PENTING: Validasi Fontre response SEBELUM menyimpan ke DB dan merespons sukses
            
            // Gunakan fungsi sendOTPViaCURL() dari file ini
            $fontre_result = sendOTPViaCURL($target, $otp);
            
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - FONTRE_SEND_ATTEMPT: phone=" . $no_hp . " fontre_success=" . ($fontre_result['success'] ? 'true' : 'false') . " message=" . $fontre_result['message'] . PHP_EOL, FILE_APPEND);
            
            // CEK APAKAH FONTRE BERHASIL
            if (!$fontre_result['success']) {
                // Jika Fontre gagal, JANGAN simpan OTP ke DB
                // Jangan respond sukses ke app
                http_response_code(503);
                sendJsonResponse(false, 'Gagal mengirim OTP ke WhatsApp: ' . $fontre_result['message'], array('status' => false));
            }
            
            // HANYA JIKA FONTRE SUKSES: Simpan OTP ke database
            $sql_insert = "INSERT INTO otp_codes (no_wa, kode_otp, expired_at, status, created_at) VALUES (?, ?, ?, 'belum', NOW())";
            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - INSERT_ATTEMPT: after_fontre_success" . PHP_EOL, FILE_APPEND);
            
            $stmt_insert = $connect->prepare($sql_insert);
            if (!$stmt_insert) {
                $err = "Prepare stmt_insert gagal: " . $connect->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                throw new Exception($err);
            }
            $stmt_insert->bind_param('sss', $no_hp, $otp, $expired_at);
            if (!$stmt_insert->execute()) {
                $err = "Execute stmt_insert gagal: " . $stmt_insert->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                $stmt_insert->close();
                throw new Exception($err);
            } else {
                $insert_id = $connect->insert_id ?? null;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - INSERT_SUCCESS (after_fontre_validation) id=" . $insert_id . " no_wa=" . $no_hp . PHP_EOL, FILE_APPEND);
            }
            $stmt_insert->close();

            // HANYA JIKA SEMUA BERHASIL: Respond sukses ke app
            http_response_code(200);
            sendJsonResponse(true, 'Kode OTP telah dikirim melalui WhatsApp', array('status' => true));
        } 
        // ===== ACTION: verify_otp =====
        else if ($action === 'verify_otp') {
            $raw_no_hp = getPostData('no_hp');
            $otp = getPostData('otp');
            $no_hp = $raw_no_hp;

            // Validasi: input wajib
            if (empty($no_hp) || empty($otp)) {
                http_response_code(400);
                sendJsonResponse(false, 'Nomor HP dan OTP wajib diisi');
            }

            $no_hp = trim($no_hp);
            $otp = trim($otp);

            // Validasi: OTP harus numeric
            if (!ctype_digit($otp)) {
                http_response_code(400);
                sendJsonResponse(false, 'OTP harus berupa angka');
            }

            // Prepare canonical forms
            $no_hp_local = sanitizePhone($no_hp);
            if (empty($no_hp_local)) {
                http_response_code(400);
                sendJsonResponse(false, 'Nomor HP tidak valid');
            }
            $no_wa_normalized = phone_to_international62($no_hp);
            if ($no_wa_normalized === false) {
                http_response_code(400);
                sendJsonResponse(false, 'Nomor HP tidak valid');
            }

            // Cek OTP di database (otp_codes menyimpan no_wa sebagai 62...)
            $sql_otp = "SELECT id, kode_otp, expired_at, status FROM otp_codes WHERE no_wa = ? ORDER BY created_at DESC LIMIT 1";
            $stmt_otp = $connect->prepare($sql_otp);
            if (!$stmt_otp) {
                $err = "Prepare stmt_otp gagal: " . $connect->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                throw new Exception($err);
            }
            $stmt_otp->bind_param('s', $no_wa_normalized);
            $stmt_otp->execute();
            $result_otp = $stmt_otp->get_result();

            if ($result_otp->num_rows === 0) {
                $stmt_otp->close();
                http_response_code(404);
                sendJsonResponse(false, 'OTP tidak ditemukan. Silakan minta OTP baru.');
            }

            $otp_record = $result_otp->fetch_assoc();
            $stmt_otp->close();

            $now = date('Y-m-d H:i:s');
            
            // Cek apakah OTP cocok
            if ($otp_record['kode_otp'] !== $otp) {
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - OTP_MISMATCH: input=$otp, db=" . $otp_record['kode_otp'] . PHP_EOL, FILE_APPEND);
                http_response_code(401);
                sendJsonResponse(false, 'Kode OTP salah. Silakan coba lagi.');
            }

            // Cek apakah OTP sudah expired
            if ($otp_record['expired_at'] < $now) {
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - OTP_EXPIRED: expired_at=" . $otp_record['expired_at'] . ", now=" . $now . PHP_EOL, FILE_APPEND);
                http_response_code(410);
                sendJsonResponse(false, 'Kode OTP sudah kadaluarsa. Silakan minta OTP baru.');
            }

            // Cek apakah OTP sudah digunakan
            if ($otp_record['status'] !== 'belum') {
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - OTP_ALREADY_USED: status=" . $otp_record['status'] . PHP_EOL, FILE_APPEND);
                http_response_code(409);
                sendJsonResponse(false, 'Kode OTP sudah pernah digunakan. Silakan minta OTP baru.');
            }

            // OTP valid! Sekarang perbarui status OTP ke 'sudah'
            $sql_update_otp = "UPDATE otp_codes SET status = 'sudah' WHERE id = ?";
            $stmt_update_otp = $connect->prepare($sql_update_otp);
            if (!$stmt_update_otp) {
                $err = "Prepare stmt_update_otp gagal: " . $connect->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                throw new Exception($err);
            }
            $stmt_update_otp->bind_param('i', $otp_record['id']);
            if (!$stmt_update_otp->execute()) {
                $err = "Execute stmt_update_otp gagal: " . $stmt_update_otp->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                $stmt_update_otp->close();
                throw new Exception($err);
            }
            $stmt_update_otp->close();

            // Cari user berdasarkan nomor lokal (08...) di DB
            $sql_user = "SELECT id, no_hp, nama_lengkap, alamat_domisili, tanggal_lahir, status_akun, created_at FROM pengguna WHERE no_hp = ? LIMIT 1";
            $stmt_user = $connect->prepare($sql_user);
            if (!$stmt_user) {
                $err = "Prepare stmt_user gagal: " . $connect->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                throw new Exception($err);
            }
            $stmt_user->bind_param('s', $no_hp_local);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();

            if ($result_user->num_rows === 0) {
                $stmt_user->close();
                http_response_code(404);
                sendJsonResponse(false, 'User tidak ditemukan.');
            }

            $user = $result_user->fetch_assoc();
            $stmt_user->close();

            // Update status akun pengguna ke pending (menunggu verifikasi admin)
            // ENUM: 'draft','submitted','pending','approved','rejected' — harus lowercase!
            $new_status = 'pending';
            // Be defensive: some installations may not have `status_verifikasi` column. Check first and fall back.
            $has_verif_col = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'status_verifikasi'");
            if ($has_verif_col && $has_verif_col->num_rows > 0) {
                $sql_update_user = "UPDATE pengguna SET status_akun = ?, status_verifikasi = 'pending' WHERE id = ?";
            } else {
                $sql_update_user = "UPDATE pengguna SET status_akun = ? WHERE id = ?";
            }
            $stmt_update_user = $connect->prepare($sql_update_user);
            if (!$stmt_update_user) {
                $err = "Prepare stmt_update_user gagal: " . $connect->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                throw new Exception($err);
            }
            $stmt_update_user->bind_param('si', $new_status, $user['id']);
            if (!$stmt_update_user->execute()) {
                $err = "Execute stmt_update_user gagal: " . $stmt_update_user->error;
                @file_put_contents($logFile, date('Y-m-d H:i:s') . " - " . $err . PHP_EOL, FILE_APPEND);
                $stmt_update_user->close();
                throw new Exception($err);
            }
            $stmt_update_user->close();

            @file_put_contents($logFile, date('Y-m-d H:i:s') . " - OTP_ACTIVATION_SUCCESS (PENDING ADMIN): user_id=" . $user['id'] . ", no_wa=" . $no_wa_normalized . PHP_EOL, FILE_APPEND);

            // Return user info to the client indicating pending state
            http_response_code(200);
            // Instruct client to go back to login and display the success notification
            sendJsonResponse(true, 'Pengajuan aktivasi akun diterima, silakan tunggu persetujuan admin.', array('user' => array(
                'id' => $user['id'],
                'no_hp' => $user['no_hp'],
                'nama' => $user['nama_lengkap'],
                'nama_lengkap' => $user['nama_lengkap'],
                'alamat' => $user['alamat_domisili'],
                'tanggal_lahir' => $user['tanggal_lahir'],
                'status_akun' => $new_status,
                'status_verifikasi' => 'pending',
                'created_at' => $user['created_at']
            )));
        } 
        else {
            http_response_code(400);
            sendJsonResponse(false, 'Action tidak dikenali');
        }
    } else {
        http_response_code(405);
        sendJsonResponse(false, 'Method not allowed. Use POST');
    }
} catch (Exception $e) {
    // Log exception to file for debugging
    @file_put_contents($logFile, date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    sendJsonResponse(false, 'Server error: ' . $e->getMessage());
}
?>
