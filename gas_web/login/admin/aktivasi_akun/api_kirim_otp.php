<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST');

require_once __DIR__.'/config/database.php';
require_once __DIR__.'/otp_helper.php';
require_once __DIR__.'/../../../../message_templates.php';

// Include centralized Fonnte configuration
$config_path = __DIR__ . '/../../../../config/fonnte_constants.php';
if (file_exists($config_path) && !defined('FONNTE_TOKEN')) {
    require_once $config_path;
}

// Ambil JSON dari Flutter
$data = json_decode(file_get_contents("php://input"), true);

$no_hp = $data['no_hp'] ?? "";

if ($no_hp == "") {
    echo json_encode([
        "success" => false,
        "message" => "Nomor HP wajib diisi"
    ]);
    exit;
}

// Prepare canonical forms: local 08 for DB lookup, international 62 for OTP
$no_hp_local = sanitizePhone($no_hp);
if (empty($no_hp_local)) {
    echo json_encode([
        "success" => false,
        "message" => "Format nomor HP tidak valid"
    ]);
    exit;
}
$no_hp_int = phone_to_international62($no_hp);
if ($no_hp_int === false) {
    echo json_encode([
        "success" => false,
        "message" => "Format nomor HP tidak valid"
    ]);
    exit;
}

// Cek nomor terdaftar (DB menyimpan 08...)
$sql = "SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1";
$stmt = $con->prepare($sql);
$stmt->bind_param("s", $no_hp_local);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode([
        "success" => false,
        "message" => "Nomor HP tidak terdaftar. Silakan daftar terlebih dahulu."
    ]);
    exit;
}

$row = $result->fetch_assoc();

// ========================================================================
// RATE LIMITING: Cegah OTP request terlalu cepat
// ========================================================================
$rate_check = checkRateLimitOTP($no_hp_int, 60); // 60 detik minimum
if (!$rate_check['allowed']) {
    @file_put_contents(__DIR__ . '/api_kirim_otp_debug.log', date('c') . " RATE_LIMITED no_hp={$no_hp_int} msg=" . $rate_check['message'] . "\n", FILE_APPEND);
    echo json_encode([
        "success" => false,
        "message" => $rate_check['message'],
        "retry_after" => $rate_check['retry_after'] ?? 60
    ]);
    exit;
}

// Generate OTP 6 digit (do not persist until send succeeds)
$kode_otp = generateOTP();
$expired_at = date("Y-m-d H:i:s", strtotime("+2 minutes"));

// Fetch user name untuk personalisasi pesan
$nama_user = 'User';
if (isset($row['nama_lengkap'])) {
    $nama_user = trim($row['nama_lengkap']) ?: 'User';
}

// Use Fonnte admin token from centralized config
$fonnte_token = FONNTE_TOKEN;

// ========================================================================
// OTP Aktivasi Akun - Pesan template profesional
// ========================================================================
$message = getMessageOTPActivation($nama_user, $kode_otp, 2, 'Tabungan');

// Add small delay before Fonnte API request (anti-spam measure)
addDelayBeforeFontneRequest(1);

// Attempt to send via centralized helper (includes validation & logging)
$sendRes = sendOTPViaFonnte($no_hp_int, $kode_otp, $fonnte_token);
if (empty($sendRes) || empty($sendRes['success'])) {
    // Log detailed info for debugging
    @file_put_contents(__DIR__ . '/api_kirim_otp_debug.log', date('c') . " SEND_FAILED no_hp={$no_hp_int} code={$kode_otp} res=" . substr(json_encode($sendRes),0,1000) . "\n", FILE_APPEND);
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengirim OTP ke WhatsApp: " . ($sendRes['message'] ?? 'unknown')
    ]);
    exit;
}

// TODO: optionally check for duplicates or throttling here

// Simpan OTP (use international 62) AFTER successful send
$sql_insert = "INSERT INTO otp_codes (no_wa, kode_otp, expired_at, status) 
               VALUES (?, ?, ?, 'belum')";
$stmt2 = $con->prepare($sql_insert);
$stmt2->bind_param("sss", $no_hp_int, $kode_otp, $expired_at);

if (!$stmt2->execute()) {
    @file_put_contents(__DIR__ . '/api_kirim_otp_debug.log', date('c') . " DB_INSERT_FAILED no_hp={$no_hp_int} code={$kode_otp} err=" . $con->error . "\n", FILE_APPEND);
    echo json_encode([
        "success" => false,
        "message" => "Gagal menyimpan OTP"
    ]);
    exit;
}

// ======================================
// SUCCESS
// ======================================

echo json_encode([
    "success" => true,
    "message" => "Kode OTP telah dikirim ke WhatsApp",
    "no_hp" => $no_hp_int
]);