<?php
/**
 * FILE: otp_helper.php
 * TUJUAN: Fungsi-fungsi helper untuk sistem OTP
 * 
 * Fungsi-fungsi di file ini:
 * 1. normalizePhoneNumber() - Konversi nomor HP ke format internasional (62xx)
 * 2. generateOTP() - Generate kode OTP 6 digit random
 * 3. isOTPExpired() - Cek apakah OTP sudah expired
 * 4. sendOTPViaFonnte() - Kirim OTP ke WhatsApp via Fonnte API
 */

// Note: Use centralized helpers for phone normalization (normalizePhoneNumber / phone_to_international62)
// The helper functions live in flutter_api/helpers.php and are loaded where needed.
// Ensure helpers are available in this runtime (some entrypoints may not include them).
$helpers_file = __DIR__ . '/flutter_api/helpers.php';
if (!function_exists('normalizePhoneNumber') || !function_exists('phone_to_international62')) {
    if (file_exists($helpers_file)) {
        require_once $helpers_file;
    } else {
        // Record for debugging without breaking functionality elsewhere
        @file_put_contents(__DIR__ . '/otp_helper_debug.log', date('c') . " helpers.php not found at {$helpers_file}\n", FILE_APPEND);
    }
}

// ============================================================================
// FUNGSI 2: Generate OTP 6 digit random
// ============================================================================
/**
 * Generate kode OTP 6 digit random
 * 
 * Format: 000000 - 999999
 * Contoh: 524891, 100234, 987654
 * 
 * @return string Kode OTP 6 digit
 */
function generateOTP() {
    // Menggunakan sprintf untuk memastikan always 6 digit (dengan leading zero jika perlu)
    return sprintf('%06d', random_int(0, 999999));
}

// ============================================================================
// FUNGSI 3: Cek apakah OTP sudah expired
// ============================================================================
/**
 * Cek apakah waktu kadaluarsa sudah lewat
 * 
 * @param string $waktu_kadaluarsa Waktu kadaluarsa (format: Y-m-d H:i:s)
 * @return bool true jika sudah expired, false jika masih valid
 */
function isOTPExpired($waktu_kadaluarsa) {
    if (empty($waktu_kadaluarsa)) {
        return true; // Dianggap expired jika waktu tidak ada
    }

    $expired_time = strtotime($waktu_kadaluarsa);
    $current_time = time();

    return $current_time > $expired_time;
}

// ============================================================================
// FUNGSI 4: Kirim OTP via Fonnte API
// ============================================================================
/**
 * Kirim OTP ke nomor HP melalui Fonnte API (WhatsApp)
 * 
 * Parameter Fonnte API:
 * - target: Nomor HP tujuan (format internasional: 62xx)
 * - message: Pesan OTP yang akan dikirim
 * - Authorization: Token Fonnte API
 * 
 * Response Fonnte:
 * {
 *   "status": "success" atau "failed",
 *   "data": { ... },
 *   "reason": "Alasan error (jika failed)" 
 * }
 * 
 * @param string $no_hp Nomor HP tujuan (format: 62xx)
 * @param string $kode_otp Kode OTP 6 digit
 * @param string $token Token Fonnte API
 * @return array Array dengan key: 'success' (bool), 'message' (string), 'response' (raw response)
 */
function sendOTPViaFonnte($no_hp, $kode_otp, $token) {
    // Validasi input dasar
    if (empty($no_hp) || empty($kode_otp) || empty($token)) {
        return array(
            'success' => false,
            'message' => 'Parameter tidak lengkap (no_hp, kode_otp, atau token kosong)',
            'response' => null
        );
    }

    // Normalize into international format using centralized helper
    $no_hp_int = phone_to_international62($no_hp);
    if ($no_hp_int === false) {
        return array(
            'success' => false,
            'message' => 'Format nomor HP tidak valid. Harus format 08xxx atau 62xxx',
            'response' => null
        );
    }
    $no_hp = $no_hp_int;

    // Validasi pola final (62...) untuk mencegah pengiriman ke format lain
    if (!preg_match('/^62\d{8,13}$/', $no_hp)) {
        return array(
            'success' => false,
            'message' => 'Nomor setelah normalisasi tidak memenuhi format 62...',
            'response' => null
        );
    }

    // Persiapan pesan OTP
    $message = "Kode OTP Anda adalah: $kode_otp (berlaku 1 menit). Jangan berikan kode ini ke siapapun.";

    // Payload form-data (url-encoded, agar Fonnte langsung kirim tanpa queue)
    $postFields = http_build_query(array(
        'target' => $no_hp,
        'message' => $message,
        'countryCode' => '62',
        'delay' => '0',
        'typing' => 'false',
        'connectOnly' => 'true',
        'preview' => 'false'
    ));

    // Inisialisasi cURL (Fonnte API: url-encoded + Authorization header)
    // Validasi OTP numeric
    if (!ctype_digit((string)$kode_otp)) {
        return array(
            'success' => false,
            'message' => 'OTP harus berupa angka',
            'response' => null
        );
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $token
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));

    // Eksekusi cURL
    $response = curl_exec($curl);
    $curl_error = curl_error($curl);
    $curl_info = curl_getinfo($curl);
    curl_close($curl);

    // Logging minimal (jangan tulis token mentah ke log)
    $log_short = date('c') . " | sendOTPViaFonnte target={$no_hp} http_code=" . ($curl_info['http_code'] ?? 'NULL') . "\n";
    @file_put_contents(__DIR__ . '/log_otp_fonte.txt', $log_short, FILE_APPEND);

    // Tangani error cURL
    if ($curl_error) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " cURL error sending OTP to {$no_hp}: {$curl_error}\n", FILE_APPEND);
        return array(
            'success' => false,
            'message' => 'cURL Error: ' . $curl_error,
            'response' => $response,
            'http_code' => $curl_info['http_code'] ?? null
        );
    }

    // Tangani HTTP error (bukan 200)
    $http_code = $curl_info['http_code'] ?? 0;
    if ($http_code !== 200) {
        $error_msg = 'HTTP Error ' . $http_code . '. ';
        if ($http_code === 401) {
            $error_msg .= 'Token Fonnte tidak valid atau sudah expired.';
        } else if ($http_code === 400) {
            $error_msg .= 'Format nomor HP atau pesan tidak valid.';
        } else if ($http_code >= 500) {
            $error_msg .= 'Server Fonnte sedang bermasalah. Coba lagi nanti.';
        } else {
            $error_msg .= 'Gagal mengirim OTP.';
        }

        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " HTTP {$http_code} sending OTP to {$no_hp}. Response: {$response}\n", FILE_APPEND);
        return array(
            'success' => false,
            'message' => $error_msg,
            'response' => $response,
            'http_code' => $http_code
        );
    }

    // Parse JSON response dari Fonnte
    $fonnte_response = json_decode($response, true);
    if ($fonnte_response === null) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " Invalid JSON response sending OTP to {$no_hp}: {$response}\n", FILE_APPEND);
        return array(
            'success' => false,
            'message' => 'Response Fonnte tidak valid (bukan JSON)',
            'response' => $response,
            'http_code' => $http_code
        );
    }

    // Cek status field
    if (!isset($fonnte_response['status'])) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " Missing status in Fonnte response for {$no_hp}: {$response}\n", FILE_APPEND);
        return array(
            'success' => false,
            'message' => 'Response Fonnte tidak memiliki field status',
            'response' => $response,
            'http_code' => $http_code
        );
    }

    $status = $fonnte_response['status'];
    if ($status === 'success' || $status === true || (is_string($status) && strtolower($status) === 'success')) {
        return array(
            'success' => true,
            'message' => 'OTP berhasil dikirim via WhatsApp',
            'response' => $response,
            'http_code' => $http_code
        );
    }

    $error_reason = isset($fonnte_response['reason']) ? $fonnte_response['reason'] : 'Unknown error';
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " Fonnte API returned failed for {$no_hp}: {$error_reason}\n", FILE_APPEND);
    return array(
        'success' => false,
        'message' => 'Fonnte API Error: ' . $error_reason,
        'response' => $response,
        'http_code' => $http_code
    );
}

/**
 * Kirim pesan bebas ke WhatsApp via Fonnte API
 * @param string $no_hp nomor tujuan (62...)
 * @param string $message pesan lengkap
 * @param string $token token Fonnte
 * @return array same contract as sendOTPViaFonnte
 */
function sendWhatsAppMessage($no_hp, $message, $token) {
    if (empty($no_hp) || empty($message) || empty($token)) {
        return ['success' => false, 'message' => 'Parameter tidak lengkap', 'response' => null];
    }

    // Normalize into international format using centralized helper
    $no_hp_int = phone_to_international62($no_hp);
    if ($no_hp_int === false) {
        @file_put_contents(__DIR__ . '/otp_helper_debug.log', date('c') . " phone_to_international62 failed for phone={$no_hp}\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Format nomor HP tidak valid', 'response' => null];
    }
    $no_hp = $no_hp_int;

    // Validate final normalized phone
    if (!preg_match('/^62\d{8,13}$/', $no_hp)) {
        return ['success' => false, 'message' => 'Nomor setelah normalisasi tidak memenuhi format 62...', 'response' => null];
    }

    $postFields = http_build_query(array('target' => $no_hp, 'message' => $message, 'countryCode' => '62', 'delay' => '0', 'typing' => 'false', 'connectOnly' => 'true', 'preview' => 'false'));

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => array(
            'Authorization: ' . $token
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));

    $response = curl_exec($curl);
    $curl_error = curl_error($curl);
    $curl_info = curl_getinfo($curl);
    curl_close($curl);

    // Minimal logging
    @file_put_contents(__DIR__ . '/log_otp_fonte.txt', date('c') . " | sendWhatsAppMessage target={$no_hp} http_code=" . ($curl_info['http_code'] ?? 'NULL') . "\n", FILE_APPEND);

    if ($curl_error) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " cURL error sending message to {$no_hp}: {$curl_error}\n", FILE_APPEND);
        return ['success' => false, 'message' => 'cURL Error: ' . $curl_error, 'response' => $response, 'http_code' => $curl_info['http_code'] ?? null];
    }

    if ($curl_info['http_code'] !== 200) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " HTTP {$curl_info['http_code']} sending message to {$no_hp}. Response: {$response}\n", FILE_APPEND);
        return ['success' => false, 'message' => 'HTTP Error ' . $curl_info['http_code'], 'response' => $response, 'http_code' => $curl_info['http_code']];
    }

    $parsed = json_decode($response, true);
    if ($parsed === null) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " Invalid JSON response sending message to {$no_hp}: {$response}\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Response Fonnte tidak valid (bukan JSON)', 'response' => $response, 'http_code' => 200];
    }

    if (!isset($parsed['status'])) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " Missing status in Fonnte response for {$no_hp}: {$response}\n", FILE_APPEND);
        return ['success' => false, 'message' => 'Response Fonnte tidak memiliki field status', 'response' => $response, 'http_code' => 200];
    }

    if ($parsed['status'] === 'success' || $parsed['status'] === true || (is_string($parsed['status']) && strtolower($parsed['status']) === 'success')) {
        return ['success' => true, 'message' => 'Pesan berhasil dikirim via WhatsApp', 'response' => $response, 'http_code' => 200];
    }

    $reason = isset($parsed['reason']) ? $parsed['reason'] : 'Unknown error';
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " Fonnte API returned failed for {$no_hp}: {$reason}\n", FILE_APPEND);
    return ['success' => false, 'message' => 'Fonnte API Error: ' . $reason, 'response' => $response, 'http_code' => 200];
}

// ============================================================================
// END OF OTP HELPER FUNCTIONS
// ============================================================================
?>
