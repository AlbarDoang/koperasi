<?php
// ========================================
// KONFIGURASI OTP EMAIL DAN WHATSAPP
// ========================================

// -------- KONFIGURASI EMAIL GMAIL --------
// Pastikan aktifkan "App Password" di Gmail:
// 1. Buka myaccount.google.com
// 2. Security -> App passwords
// 3. Generate app password untuk "Mail"
// 4. Copy password ke sini (bukan password Gmail biasa)

define('EMAIL_SENDER', 'alifalifrizal15@gmail.com');          // Email pengirim OTP
define('EMAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');          // Ganti dengan App Password dari Gmail
define('EMAIL_DISPLAY_NAME', 'Tabungan System');          // Nama pengirim

// -------- KONFIGURASI WHATSAPP --------
// Nomor pengirim: 62895419328070
// Status: Dummy/Mock Mode (log saja, belum ada API berbayar)
// Jika sudah ada API (Twilio, WhatsApp Business API), update di sini

define('WHATSAPP_ENABLE', true);                          // Set true untuk enable WhatsApp OTP
define('WHATSAPP_SENDER', '62895419328070');              // Nomor WhatsApp pengirim (format: 62...)
define('WHATSAPP_API_MODE', 'dummy');                     // Mode: 'dummy' (test) atau 'api' (production)
define('WHATSAPP_API_URL', '');                           // URL API (jika pakai Twilio/WhatsApp Business API)
define('WHATSAPP_API_KEY', '');                           // API Key (jika pakai service berbayar)
define('WHATSAPP_ACCOUNT_SID', '');                       // Untuk Twilio (jika pakai)

// -------- OTP SETTINGS --------
define('OTP_LENGTH', 6);                                  // Panjang kode OTP
define('OTP_VALIDITY', 600);                              // Validitas OTP dalam detik (600 = 10 menit)
define('OTP_MAX_ATTEMPTS', 3);                            // Max percobaan verifikasi OTP

// -------- DEBUG MODE --------
define('DEBUG_MODE', true);                               // Set false di production
define('LOG_DIR', __DIR__ . '/../../logs/');              // Folder untuk log OTP

// Buat folder logs jika belum ada
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0777, true);
}

/**
 * Function untuk log OTP
 */
function log_otp($email, $phone, $otp, $kode) {
    if (DEBUG_MODE) {
        $log_file = LOG_DIR . 'otp_' . date('Y-m-d') . '.log';
        $log_msg = date('Y-m-d H:i:s') . " | Email: $email | Phone: $phone | OTP: $otp | Kode: $kode\n";
        file_put_contents($log_file, $log_msg, FILE_APPEND);
    }
}

?>
