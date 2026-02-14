<?php
/**
 * FILE: send_otp_background.php
 * TUJUAN: Background script untuk mengirim OTP via Fonnte API
 * 
 * Dipanggil dari aktivasi_akun.php sebagai background process (non-blocking)
 * Script ini berjalan terpisah dari HTTP request, sehingga timeout Fonnte
 * tidak akan mempengaruhi respons ke Flutter app
 * 
 * Panggilan dari parent:
 * shell_exec("nohup php send_otp_background.php '$json_data' > /dev/null 2>&1 &");
 * 
 * $json_data format:
 * {
 *   "target": "62812345678",
 *   "otp": "123456",
 *   "no_hp": "62812345678"
 * }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');  // Jangan tampilkan error ke stdout

// Ambil data dari argument CLI
$data_json = $argv[1] ?? null;
if (!$data_json) {
    file_put_contents(__DIR__ . '/log_otp_background.txt', date('Y-m-d H:i:s') . " - ERROR: No data passed\n", FILE_APPEND);
    exit(1);
}

$data = json_decode($data_json, true);
if (!$data) {
    file_put_contents(__DIR__ . '/log_otp_background.txt', date('Y-m-d H:i:s') . " - ERROR: Invalid JSON data\n", FILE_APPEND);
    exit(1);
}

$target = $data['target'] ?? null;
$otp = $data['otp'] ?? null;
$no_hp = $data['no_hp'] ?? null;

if (!$target || !$otp || !$no_hp) {
    file_put_contents(__DIR__ . '/log_otp_background.txt', date('Y-m-d H:i:s') . " - ERROR: Missing required data\n", FILE_APPEND);
    exit(1);
}

// Load config
require_once __DIR__ . '/../config/fontte_constants.php';

$fonnte_token = FONTTE_TOKEN ?? null;
if (!$fonnte_token) {
    file_put_contents(__DIR__ . '/log_otp_background.txt', date('Y-m-d H:i:s') . " - ERROR: Fontte token not configured\n", FILE_APPEND);
    exit(1);
}

$logFile = __DIR__ . '/log_otp_background.txt';

file_put_contents($logFile, date('Y-m-d H:i:s') . " - STARTING background OTP send target=$target otp=$otp\n", FILE_APPEND);

// ==========================================
// Kirim OTP via Fonnte API (synchronous, tapi non-blocking dari HTTP perspective)
// ==========================================
$message = "Kode OTP Anda adalah: $otp (berlaku 1 menit).";

// Sanitasi nomor tujuan
$target_clean = preg_replace('/[^0-9]/', '', (string)$target);
if (substr($target_clean, 0, 1) === '0') {
    $target_clean = '62' . substr($target_clean, 1);
}

if (!preg_match('/^62\d{8,13}$/', $target_clean)) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: Invalid target number: $target -> $target_clean\n", FILE_APPEND);
    exit(1);
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

// cURL request ke Fonnte
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.fonnte.com/send',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postFields,
    CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $fonnte_token
    ),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
));

$response = curl_exec($curl);
$curl_error = curl_error($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

// Log hasil
if ($curl_error) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - FAILED target=$target_clean curl_error=$curl_error\n", FILE_APPEND);
    exit(1);
}

if ($http_code === 200) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - SUCCESS target=$target_clean http_code=$http_code\n", FILE_APPEND);
    exit(0);
} else {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - HTTP_ERROR target=$target_clean http_code=$http_code response=" . substr($response, 0, 500) . "\n", FILE_APPEND);
    exit(1);
}

