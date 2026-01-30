<?php
/**
 * FILE: fonnte_constants.php
 * TUJUAN: Centralisasi konfigurasi Fonnte WhatsApp OTP
 * 
 * LOKASI: gas_web/config/fonnte_constants.php
 * 
 * Penggunaan:
 *   require_once __DIR__ . '/../config/fonnte_constants.php';
 *   $response = sendOTPViaFonnte($no_hp_international, $otp, FONNTE_TOKEN);
 */

// ============================================================================
// KONFIGURASI FONNTE (WhatsApp OTP Gateway)
// ============================================================================

// 1. TOKEN FONNTE API (Admin WhatsApp)
// - Token ini digunakan untuk mengirim OTP dan pesan WhatsApp
// - Jangan share token ini ke publik
// - ⚠️  UPDATE TOKEN HANYA DI FILE INI SAJA
// - Jangan hardcode token di file lain (forgot_password.php, api_kirim_otp.php, dll)
// - Gunakan FONNTE_TOKEN constant di semua file yang butuh token
define('FONNTE_TOKEN', 'fovXJkrMJdnVswBG3U2Z');

// 2. ENDPOINT API FONNTE
// - URL endpoint resmi Fonnte untuk mengirim pesan
// - Gunakan HTTPS untuk keamanan
define('FONNTE_API_ENDPOINT', 'https://api.fonnte.com/send');

// 3. NOMOR WhatsApp ADMIN (untuk mengirim notifikasi ke admin)
// - Format: International (62xx, bukan 08xx)
// - Contoh: '6287822451601'
define('FONNTE_ADMIN_WA', '6287822451601');

// 4. TIMEOUT CURL (detik)
// - Waktu maksimal untuk menunggu response dari API Fonnte
define('FONNTE_CURL_TIMEOUT', 30);
define('FONNTE_CURL_CONNECT_TIMEOUT', 10);

// 5. TIMEOUT OTP (menit)
// - Berapa lama OTP valid sejak digenerate
define('FONNTE_OTP_VALID_MINUTES', 2);

// ============================================================================
// VALIDASI KONFIGURASI
// ============================================================================

// Pastikan token tidak kosong
if (empty(FONNTE_TOKEN) || strlen(FONNTE_TOKEN) < 10) {
    trigger_error('ERROR: FONNTE_TOKEN tidak dikonfigurasi dengan benar di ' . __FILE__, E_USER_WARNING);
}

// Pastikan endpoint valid
if (!filter_var(FONNTE_API_ENDPOINT, FILTER_VALIDATE_URL)) {
    trigger_error('ERROR: FONNTE_API_ENDPOINT tidak valid di ' . __FILE__, E_USER_WARNING);
}

// Pastikan nomor admin valid
if (!preg_match('/^62\d{8,13}$/', FONNTE_ADMIN_WA)) {
    trigger_error('ERROR: FONNTE_ADMIN_WA harus format internasional 62xxx di ' . __FILE__, E_USER_WARNING);
}

?>
