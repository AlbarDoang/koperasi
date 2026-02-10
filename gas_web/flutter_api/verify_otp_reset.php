<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

require_once('connection.php');
require_once('helpers.php');

$logFile = __DIR__ . '/log_db.txt';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(array('status' => false, 'message' => 'Method not allowed'));
        exit();
    }

    $no_hp = getPostData('no_hp');
    $otp = getPostData('otp');

    if (empty($no_hp) || empty($otp)) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Nomor HP dan OTP wajib diisi'));
        exit();
    }

    $no_hp = trim($no_hp);
    $otp = trim($otp);

    // Prepare international format for OTP lookup
    $no_wa_normalized = phone_to_international62($no_hp);
    if ($no_wa_normalized === false) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Nomor HP tidak valid'));
        exit();
    }

    // Cek OTP di table katasandi_reset_otps (stored as 62...)
    $sql_otp = 'SELECT id, kode_otp, expired_at, status FROM katasandi_reset_otps WHERE no_hp = ? ORDER BY created_at DESC LIMIT 1';
    $stmt_otp = $connect->prepare($sql_otp);
    
    if (!$stmt_otp) {
        throw new Exception('Prepare OTP check gagal: ' . $connect->error);
    }

    $stmt_otp->bind_param('s', $no_wa_normalized);
    $stmt_otp->execute();
    $result_otp = $stmt_otp->get_result();

    if ($result_otp->num_rows === 0) {
        $stmt_otp->close();
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'OTP tidak ditemukan'));
        exit();
    }

    $otp_record = $result_otp->fetch_assoc();
    $stmt_otp->close();

    $now = date('Y-m-d H:i:s');

    // Validasi 1: OTP cocok
    if ($otp_record['kode_otp'] !== $otp) {
        http_response_code(401);
        echo json_encode(array('success' => false, 'status' => false, 'message' => 'Kode OTP yang Anda masukkan tidak valid.'));
        exit();
    }

    // Validasi 2: OTP belum expired (perlu expired_at >= NOW())
    if ($otp_record['expired_at'] < $now) {
        http_response_code(410);
        echo json_encode(array('success' => false, 'status' => false, 'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.'));
        exit();
    }

    // Validasi 3: OTP belum digunakan (status = 'belum')
    if ($otp_record['status'] !== 'belum') {
        http_response_code(409);
        echo json_encode(array('success' => false, 'status' => false, 'message' => 'Kode OTP sudah pernah digunakan. Silakan minta kode baru.'));
        exit();
    }

    // Mark OTP as used (status = 'terpakai')
    $sql_update = 'UPDATE katasandi_reset_otps SET status = ? WHERE id = ?';
    $stmt_update = $connect->prepare($sql_update);
    if (!$stmt_update) {
        throw new Exception('Prepare update OTP gagal: ' . $connect->error);
    }

    $status_terpakai = 'terpakai';
    $stmt_update->bind_param('si', $status_terpakai, $otp_record['id']);
    if (!$stmt_update->execute()) {
        throw new Exception('Execute update OTP gagal: ' . $stmt_update->error);
    }
    $stmt_update->close();

    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - VERIFY_OTP_RESET_SUCCESS: no_hp=' . $no_wa_normalized . ' otp=' . $otp . PHP_EOL, FILE_APPEND);

    http_response_code(200);
    echo json_encode(array('success' => true, 'status' => true, 'message' => 'OTP berhasil diverifikasi.'));

} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - VERIFY_OTP_RESET_ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(array('status' => false, 'message' => 'Gagal verifikasi OTP: ' . $e->getMessage()));
}
