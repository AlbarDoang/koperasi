<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/../config/fonnte_constants.php';
require_once __DIR__ . '/../message_templates.php';

$logFile = __DIR__ . '/log_db.txt';

// ============================================================================
// FUNGSI: Kirim OTP ke WhatsApp via Fonnte API
// ============================================================================
function sendOTPViaCURL($target, $otp) {
    // Format pesan OTP Reset Password sesuai spek Koperasi GAS
    // Pesan: Koperasi GAS + Kode OTP untuk reset password + 2 menit valid + Jangan bagikan
    $message = "Koperasi GAS\n";
    $message .= "Kode OTP untuk reset password akun Anda adalah:\n";
    $message .= "{$otp}\n\n";
    $message .= "Kode ini bersifat rahasia dan berlaku selama 2 menit.\n";
    $message .= "Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai admin.";

    if (!defined('FONNTE_TOKEN')) {
        return array('success' => false, 'message' => 'Token pengiriman WhatsApp belum dikonfigurasi pada server', 'response' => null);
    }
    $fonnte_token = FONNTE_TOKEN;

    if (empty($fonnte_token)) {
        return array('success' => false, 'message' => 'Token pengiriman WhatsApp belum dikonfigurasi pada server', 'response' => null);
    }

    // Sanitasi nomor tujuan: hapus karakter non-digit
    $target_clean = preg_replace('/[^0-9]/', '', (string)$target);
    // Pastikan format internasional 62...
    if (substr($target_clean, 0, 1) === '0') {
        $target_clean = '62' . substr($target_clean, 1);
    }
    if (!preg_match('/^62\d{8,13}$/', $target_clean)) {
        return array('success' => false, 'message' => 'Nomor tujuan tidak valid untuk WhatsApp (harus dalam format 628xxxxxxxx)', 'response' => null);
    }

    // Siapkan payload JSON
    $payload = json_encode([
        'target' => $target_clean,
        'message' => $message
    ]);

    if (!defined('FONNTE_API_ENDPOINT')) {
        define('FONNTE_API_ENDPOINT', 'https://api.fonnte.com/send');
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => FONNTE_API_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $fonnte_token,
            'Content-Type: application/json'
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ));

    $response = curl_exec($curl);
    $curl_error = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($curl_error) {
        return array('success' => false, 'message' => 'cURL Error: ' . $curl_error, 'response' => $response);
    }

    $result = json_decode($response, true);
    if (!$result || !is_array($result)) {
        return array('success' => false, 'message' => 'Fonte API tidak mengembalikan JSON yang valid', 'response' => $response);
    }

    $statusVal = $result['status'] ?? null;
    $isSuccessStatus = ($statusVal === true) || ($statusVal === 'success') || ($statusVal === 1) || (is_string($statusVal) && strtolower($statusVal) === 'ok');
    if (!$isSuccessStatus) {
        $reason = $result['reason'] ?? ($result['message'] ?? 'Unknown error');
        return array('success' => false, 'message' => 'Fonte API Error: ' . $reason, 'response' => $response);
    }

    return array('success' => true, 'message' => 'OTP berhasil dikirim ke WhatsApp', 'response' => $response);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        safeJsonResponse(false, 'Method not allowed', array('status' => false));
    }

    $no_hp = getPostData('no_hp');

    if (empty($no_hp)) {
        safeJsonResponse(false, 'Nomor HP wajib diisi', array('status' => false));
    }

    $no_hp = trim($no_hp);

    // Normalize input: local 08 for DB lookup, international 62 for OTP
    $no_hp_local = sanitizePhone($no_hp);
    if (empty($no_hp_local)) {
        safeJsonResponse(false, 'Nomor HP tidak valid', array('status' => false));
    }
    $no_wa_normalized = phone_to_international62($no_hp);
    if ($no_wa_normalized === false) {
        safeJsonResponse(false, 'Nomor HP tidak valid', array('status' => false));
    }

    // Cek apakah nomor HP terdaftar (DB menyimpan 08...)
    $sql = 'SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1';
    $stmt = $connect->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare statement gagal: ' . $connect->error);
    }

    $stmt->bind_param('s', $no_hp_local);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        safeJsonResponse(false, 'Nomor HP tidak terdaftar', array('status' => false));
    }

    $stmt->close();

    // ========================================================================
    // RATE LIMITING: Cegah OTP request terlalu cepat
    // ========================================================================
    $rate_check = checkRateLimitOTP($no_wa_normalized, 60); // 60 detik minimum
    if (!$rate_check['allowed']) {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - FORGOT_PASSWORD_RATE_LIMITED: ' . $rate_check['message'] . PHP_EOL, FILE_APPEND);
        safeJsonResponse(false, $rate_check['message'], array('status' => false, 'retry_after' => $rate_check['retry_after'] ?? 60));
    }

    // Generate OTP 6 digit (do not persist until send succeeds)
    $otp = sprintf('%06d', random_int(0, 999999));
    $expired_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));

    // ========================================================================
    // OTP Reset Password - Kirim via Fontte dengan pesan template
    // ========================================================================
    // Gunakan template pesan profesional (tanpa nama user untuk kesederhanaan)
    // Pesan format: Kode OTP untuk reset password
    // Message akan ditangani oleh sendOTPViaCURL sebagai simple message
    
    $sendRes = sendOTPViaCURL($no_wa_normalized, $otp);
    if (empty($sendRes) || empty($sendRes['success'])) {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - FORGOT_PASSWORD_SEND_FAIL: ' . substr(json_encode($sendRes),0,1000) . PHP_EOL, FILE_APPEND);
        safeJsonResponse(false, 'Gagal mengirim OTP ke WhatsApp. Silakan coba lagi nanti atau hubungi admin.', array('status' => false));
    }

    // Insert OTP ke database (table: katasandi_reset_otps) AFTER successful send
    $sql_insert = 'INSERT INTO katasandi_reset_otps (no_hp, kode_otp, expired_at, status, created_at) VALUES (?, ?, ?, ?, NOW())';
    $stmt_insert = $connect->prepare($sql_insert);
    if (!$stmt_insert) {
        throw new Exception('Prepare insert gagal: ' . $connect->error);
    }

    $status_belum = 'belum';
    $stmt_insert->bind_param('ssss', $no_wa_normalized, $otp, $expired_at, $status_belum);

    if (!$stmt_insert->execute()) {
        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - FORGOT_PASSWORD_INSERT_ERROR: ' . $stmt_insert->error . PHP_EOL, FILE_APPEND);
        $stmt_insert->close();
        safeJsonResponse(false, 'Gagal menyimpan kode OTP. Silakan coba lagi nanti.', array('status' => false));
    }

    $stmt_insert->close();

    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - FORGOT_PASSWORD_OTP_SENT: no_hp=' . $no_wa_normalized . ' otp=' . $otp . PHP_EOL, FILE_APPEND);

    safeJsonResponse(true, 'Kode OTP telah dikirim ke WhatsApp Anda', array('status' => true));

} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - FORGOT_PASSWORD_ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    safeJsonResponse(false, 'Gagal mengirim OTP: ' . $e->getMessage(), array('status' => false));
}
