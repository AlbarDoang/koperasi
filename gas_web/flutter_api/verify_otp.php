<?php
// API: verify_otp.php — validate OTP and mark used
header('Content-Type: application/json');
@ini_set('display_errors', '0');
include 'connection.php';
include 'helpers.php';

$no_wa = isset($_POST['no_wa']) ? $_POST['no_wa'] : '';
$kode_otp = isset($_POST['kode_otp']) ? $_POST['kode_otp'] : '';

if (empty($no_wa) || empty($kode_otp)) {
    sendJsonResponse(false, 'Nomor WhatsApp dan kode OTP wajib diisi.');
}

try {
    $stmt = $conn->prepare('SELECT kode_otp, expired_at, status FROM otp_codes WHERE no_wa = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('s', $no_wa);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $now = date('Y-m-d H:i:s');
        if ($row['kode_otp'] === $kode_otp) {
            if ($row['expired_at'] >= $now && $row['status'] == 'belum') {
                // Mark OTP as used
                $update = $conn->prepare('UPDATE otp_codes SET status = ? WHERE no_wa = ? AND kode_otp = ?');
                $used = 'sudah';
                $update->bind_param('sss', $used, $no_wa, $kode_otp);
                $update->execute();
                $response['success'] = true;
                $response['message'] = 'OTP berhasil diverifikasi.';
            } else if ($row['expired_at'] < $now) {
                $response['success'] = false;
                $response['message'] = 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.';
            } else {
                $response['success'] = false;
                $response['message'] = 'Kode OTP sudah pernah digunakan. Silakan minta kode baru.';
            }
        } else {
            $response['success'] = false;
            $response['message'] = 'Kode OTP yang Anda masukkan tidak valid.';
        }
    } else {
        $response['success'] = false;
        $response['message'] = 'OTP tidak ditemukan.';
    }
} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [verify_otp] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    sendJsonResponse(false, 'Terjadi kesalahan: ' . $e->getMessage());
}

// If we reach here, the success/message were set above
sendJsonResponse(isset($response['success']) ? (bool)$response['success'] : false, $response['message'] ?? '');
