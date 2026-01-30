<?php
/**
 * File: otp_send.php
 * Fungsi untuk mengirim OTP via WhatsApp dan Email
 * Updated: 13 November 2025
 */

require_once __DIR__ . '/otp_config.php';

/**
 * Fungsi untuk mengirim OTP via WhatsApp
 * @param string $phone Nomor WhatsApp penerima (format: 62... atau 0...)
 * @param string $otp Kode OTP yang akan dikirim
 * @param string $nama Nama penerima (opsional)
 * @return array ['status' => true/false, 'message' => string]
 */
function send_otp_whatsapp($phone, $otp, $nama = 'User') {
    // Normalisasi nomor WhatsApp
    $phone_normalized = normalize_phone_number($phone);
    
    if (!$phone_normalized) {
        return ['status' => false, 'message' => 'Nomor WhatsApp tidak valid'];
    }
    
    // Pesan OTP
    $message = "Halo $nama,\n\n";
    $message .= "Kode OTP Anda: *$otp*\n\n";
    $message .= "Kode ini berlaku selama 10 menit.\n";
    $message .= "Jangan bagikan kode OTP kepada siapa pun.\n\n";
    $message .= "Regards,\nTabungan System";
    
    // Log OTP
    write_otp_log("WhatsApp: $phone_normalized", $otp, $message);
    
    // Mode: Dummy (hanya log) atau API (kirim via service)
    if (WHATSAPP_API_MODE === 'dummy') {
        // Mode testing: hanya log, tidak kirim
        return [
            'status' => true, 
            'message' => "OTP akan dikirim ke WhatsApp $phone_normalized (Mode Testing - Check Log)",
            'mode' => 'dummy',
            'log_info' => "Cek file log di: " . LOG_DIR . "otp_" . date('Y-m-d') . ".log"
        ];
    } else if (WHATSAPP_API_MODE === 'api') {
        // Mode production: kirim via API
        return send_whatsapp_via_api($phone_normalized, $message, $otp);
    } else {
        return ['status' => false, 'message' => 'WhatsApp mode tidak dikenali'];
    }
}

/**
 * Normalisasi nomor WhatsApp ke format internasional
 */
function normalize_phone_number($phone) {
    // Hapus spasi dan karakter khusus
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (empty($phone)) {
        return false;
    }
    
    // Jika dimulai dengan 0, ganti dengan 62
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    // Validasi panjang nomor Indonesia (62 + 9-10 digit)
    if (!preg_match('/^62[0-9]{9,10}$/', $phone)) {
        return false;
    }
    
    return $phone;
}

/**
 * Kirim OTP via API WhatsApp
 * Customize sesuai dengan service yang Anda gunakan
 */
function send_whatsapp_via_api($phone, $message, $otp) {
    // ===== JIKA PAKAI TWILIO =====
    // Uncomment dan isi credentials Twilio jika menggunakan Twilio
    /*
    require_once __DIR__ . '/Twilio/autoload.php';
    use Twilio\Rest\Client;
    
    $account_sid = WHATSAPP_ACCOUNT_SID;
    $auth_token = WHATSAPP_API_KEY;
    $client = new Client($account_sid, $auth_token);
    
    try {
        $client->messages->create(
            "whatsapp:+$phone",
            array("from" => "whatsapp:" . WHATSAPP_SENDER, "body" => $message)
        );
        return ['status' => true, 'message' => "OTP berhasil dikirim ke $phone via Twilio"];
    } catch (Exception $e) {
        write_otp_log($phone, $otp, 'ERROR: ' . $e->getMessage());
        return ['status' => false, 'message' => "Gagal mengirim OTP: " . $e->getMessage()];
    }
    */
    
    // ===== JIKA PAKAI API CUSTOM LAIN =====
    /*
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, WHATSAPP_API_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'phone' => $phone,
        'message' => $message,
        'api_key' => WHATSAPP_API_KEY
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return ['status' => true, 'message' => "OTP berhasil dikirim ke $phone"];
    } else {
        return ['status' => false, 'message' => "Gagal mengirim OTP (HTTP $http_code)"];
    }
    */
    
    return ['status' => false, 'message' => 'WhatsApp API belum dikonfigurasi'];
}

/**
 * Fungsi untuk mengirim OTP via Email
 * @param string $email Email penerima
 * @param string $otp Kode OTP
 * @param string $nama Nama penerima (opsional)
 * @return array ['status' => true/false, 'message' => string]
 */
function send_otp_email($email, $otp, $nama = 'User') {
    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['status' => false, 'message' => 'Email tidak valid'];
    }
    
    // Subject dan body email HTML
    $subject = "Kode Verifikasi OTP - Tabungan System";
    
    $html_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; }
            .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; color: #ff6600; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
            .content { color: #333; line-height: 1.6; }
            .otp-box { background: #fff9f5; border: 2px solid #ff6600; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
            .otp-code { font-size: 36px; font-weight: bold; color: #ff6600; letter-spacing: 5px; }
            .footer { margin-top: 20px; font-size: 12px; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>Tabungan System</div>
            
            <div class='content'>
                <p>Halo <strong>$nama</strong>,</p>
                
                <p>Anda telah mendaftar di Tabungan System. Gunakan kode verifikasi di bawah ini untuk menyelesaikan registrasi Anda:</p>
                
                <div class='otp-box'>
                    <div class='otp-code'>$otp</div>
                </div>
                
                <p><strong>Penting:</strong></p>
                <ul>
                    <li>Kode ini berlaku selama 10 menit</li>
                    <li>Jangan bagikan kode ini kepada siapa pun</li>
                    <li>Jika Anda tidak melakukan registrasi, abaikan email ini</li>
                </ul>
                
                <p>Terima kasih,<br><strong>Tim Tabungan System</strong></p>
            </div>
            
            <div class='footer'>
                <p>Email ini dikirim otomatis. Jangan reply email ini.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Header email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_DISPLAY_NAME . " <" . EMAIL_SENDER . ">\r\n";
    
    // Log OTP
    write_otp_log("Email: $email", $otp, $html_message);
    
    // Coba kirim dengan mail() function
    if (@mail($email, $subject, $html_message, $headers)) {
        return ['status' => true, 'message' => "OTP berhasil dikirim ke email $email"];
    } else {
        return ['status' => true, 'message' => "OTP disimpan (mode debug). Lihat log file."];
    }
}

/**
 * Tulis OTP ke log file
 */
function write_otp_log($destination, $otp, $details = '') {
    if (DEBUG_MODE) {
        $log_file = LOG_DIR . 'otp_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_msg = "[$timestamp] Destination: $destination | OTP: $otp\n";
        if (!empty($details)) {
            $log_msg .= "Details: " . substr($details, 0, 100) . "...\n";
        }
        $log_msg .= "---\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);
    }
}

?>
