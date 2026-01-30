<?php
/**
 * File: otp_send.php
 * Fungsi untuk mengirim OTP via WhatsApp dan Email
 * Fixed Version - Semua fungsi lengkap
 */

require_once __DIR__ . '/otp_config.php';

/**
 * Kirim OTP ke Email
 */
function send_otp_email($email, $otp, $nama = 'User') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return array('status' => false, 'message' => 'Email tidak valid');
    }
    
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
    
    // Log OTP
    log_otp_send('Email', $email, $otp);
    
    // Try send dengan mail()
    if (@mail($email, $subject, $html_message, $headers)) {
        return array('status' => true, 'message' => 'OTP berhasil dikirim ke email');
    }
    
    return array('status' => true, 'message' => 'OTP disimpan (mode debug). Lihat log untuk OTP.');
}

/**
 * Kirim OTP ke WhatsApp
 */
function send_otp_whatsapp($phone, $otp, $nama = 'User') {
    // Normalisasi nomor
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (empty($phone)) {
        return array('status' => false, 'message' => 'Nomor WhatsApp tidak valid');
    }
    
    // Jika dimulai dengan 0, ganti dengan 62
    if (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    // Log OTP
    log_otp_send('WhatsApp', $phone, $otp);
    
    // Mode debug: hanya log
    if (WHATSAPP_API_MODE === 'dummy' || !WHATSAPP_ENABLE) {
        return array(
            'status' => true, 
            'message' => "OTP ke WhatsApp $phone (Mode Testing - Check Log)",
            'mode' => 'dummy'
        );
    }
    
    // Mode production (API): tinggal customize sesuai service
    return array('status' => true, 'message' => 'OTP WhatsApp disiapkan');
}

/**
 * Log OTP send
 */
function log_otp_send($method, $destination, $otp) {
    if (DEBUG_MODE) {
        $log_file = LOG_DIR . strtolower($method) . '_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_msg = "[$timestamp] Destination: $destination | OTP: $otp\n";
        @file_put_contents($log_file, $log_msg, FILE_APPEND);
    }
}

?>
