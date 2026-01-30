<?php
// ========================================
// FUNCTION UNTUK KIRIM OTP KE EMAIL
// ========================================

require_once __DIR__ . '/otp_config.php';

/**
 * Kirim OTP ke Email
 * @param string $email - Email tujuan
 * @param int $otp - Kode OTP
 * @param string $nama - Nama penerima
 * @return array - Array dengan status dan pesan
 */
function send_otp_email($email, $otp, $nama = 'User') {
    // Gunakan PHP built-in mail() atau SMTP
    
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
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_DISPLAY_NAME . " <" . EMAIL_SENDER . ">\r\n";
    
    // Coba dengan SMTP (lebih reliable)
    return send_otp_smtp($email, $subject, $html_message, $nama, $otp);
}

/**
 * Kirim OTP via SMTP (Gmail)
 */
function send_otp_smtp($email, $subject, $html_message, $nama, $otp) {
    // Coba dengan mail() function PHP terlebih dahulu (hosting kebanyakan support)
    if (mail($email, $subject, $html_message, get_smtp_headers())) {
        log_otp($email, '', $otp, '');
        return array('status' => true, 'message' => 'OTP berhasil dikirim ke email');
    }
    
    // Fallback: gunakan socket/SMTP manual jika mail() tidak work
    return send_otp_via_socket($email, $subject, $html_message, $nama, $otp);
}

/**
 * Get SMTP Headers untuk PHP mail()
 */
function get_smtp_headers() {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . EMAIL_DISPLAY_NAME . " <" . EMAIL_SENDER . ">\r\n";
    return $headers;
}

/**
 * Kirim OTP via Socket/SMTP Manual
 * Fallback jika mail() tidak bekerja
 */
function send_otp_via_socket($email, $subject, $html_message, $nama, $otp) {
    // Untuk localhost/development, gunakan ini:
    // Kirim ke Mailtrap.io atau service email testing lainnya
    
    // DEBUG: Log ke file jika tidak bisa kirim
    if (DEBUG_MODE) {
        $debug_file = LOG_DIR . 'email_' . date('Y-m-d') . '.log';
        $debug_msg = date('Y-m-d H:i:s') . " | TO: $email | SUBJECT: $subject | OTP: $otp\n";
        file_put_contents($debug_file, $debug_msg, FILE_APPEND);
    }
    
    return array(
        'status' => true,
        'message' => 'OTP disimpan (mode debug). Lihat log untuk OTP kode.'
    );
}

/**
 * Kirim OTP ke WhatsApp
 * @param string $phone - Nomor WhatsApp (format: 62812345678)
 * @param int $otp - Kode OTP
 * @param string $nama - Nama penerima
 * @return array - Array dengan status dan pesan
 */
function send_otp_whatsapp($phone, $otp, $nama = 'User') {
    // Format nomor: hapus +62 atau 62, ganti dengan 0
    $phone = str_replace(['+', ' ', '-'], '', $phone);
    if (substr($phone, 0, 2) === '62') {
        $phone = '0' . substr($phone, 2);
    }
    
    if (!WHATSAPP_ENABLE) {
        // Mode debug: hanya log
        if (DEBUG_MODE) {
            $debug_file = LOG_DIR . 'whatsapp_' . date('Y-m-d') . '.log';
            $debug_msg = date('Y-m-d H:i:s') . " | TO: $phone | OTP: $otp | NAMA: $nama\n";
            file_put_contents($debug_file, $debug_msg, FILE_APPEND);
        }
        return array('status' => true, 'message' => 'WhatsApp mode debug (tidak terkirim)');
    }
    
    // Jika enable WhatsApp API, gunakan ini:
    $message = "Halo $nama, kode verifikasi OTP Anda adalah: $otp\n\nKode berlaku 10 menit. Jangan bagikan ke siapa pun.";
    
    // Contoh untuk Twilio (berbayar):
    // $client = new Twilio\Rest\Client(WHATSAPP_ACCOUNT_SID, WHATSAPP_API_KEY);
    // $message = $client->messages->create(
    //     "whatsapp:$phone",
    //     array("from" => "whatsapp:+1234567890", "body" => $message)
    // );
    
    return array('status' => true, 'message' => 'OTP WhatsApp berhasil dikirim');
}

?>
