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
    $pin_baru = getPostData('pin_baru');
    $pin_confirm = getPostData('pin_confirm');

    // Normalize: local 08 for DB lookup, international 62 for OTP table
    $no_hp_local = sanitizePhone($no_hp);
    if (empty($no_hp_local)) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Nomor HP tidak valid'));
        exit();
    }
    $no_wa_normalized = phone_to_international62($no_hp);
    if ($no_wa_normalized === false) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Nomor HP tidak valid'));
        exit();
    }

    if (empty($no_hp) || empty($otp) || empty($pin_baru) || empty($pin_confirm)) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Semua field wajib diisi'));
        exit();
    }

    $no_hp = trim($no_hp);
    $otp = trim($otp);
    $pin_baru = trim($pin_baru);
    $pin_confirm = trim($pin_confirm);

    if (!preg_match('/^\d{6}$/', $pin_baru)) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'PIN baru harus 6 digit angka'));
        exit();
    }

    if ($pin_baru !== $pin_confirm) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Konfirmasi PIN tidak cocok'));
        exit();
    }

    // Cek OTP di table katasandi_reset_otps (stored as international 62 format)
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
        echo json_encode(array('status' => false, 'message' => 'OTP tidak ditemukan'));
        exit();
    }

    $otp_record = $result_otp->fetch_assoc();
    $stmt_otp->close();

    $now = date('Y-m-d H:i:s');

    // Validasi 1: OTP cocok
    if ($otp_record['kode_otp'] !== $otp) {
        http_response_code(401);
        echo json_encode(array('status' => false, 'message' => 'Kode OTP yang Anda masukkan tidak valid.'));
        exit();
    }

    // Validasi 2: OTP belum expired
    if ($otp_record['expired_at'] < $now) {
        http_response_code(410);
        echo json_encode(array('status' => false, 'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode baru.'));
        exit();
    }

    // Validasi 3: OTP belum digunakan (status = 'belum')
    if ($otp_record['status'] !== 'belum') {
        http_response_code(409);
        echo json_encode(array('status' => false, 'message' => 'Kode OTP sudah pernah digunakan. Silakan minta kode baru.'));
        exit();
    }

    // Cek user ada di database (lookup by local 08)
    $sql_user = 'SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1';
    $stmt_user = $connect->prepare($sql_user);
    if (!$stmt_user) {
        throw new Exception('Prepare user check gagal: ' . $connect->error);
    }

    $stmt_user->bind_param('s', $no_hp_local);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();

    if ($result_user->num_rows === 0) {
        $stmt_user->close();
        http_response_code(404);
        echo json_encode(array('status' => false, 'message' => 'User tidak ditemukan'));
        exit();
    }

    $user = $result_user->fetch_assoc();
    $stmt_user->close();

    // Hash PIN baru
    $pin_hash = password_hash($pin_baru, PASSWORD_DEFAULT);

    // Update pengguna.pin (use local 08)
    $sql_update_pin = 'UPDATE pengguna SET pin = ? WHERE no_hp = ?';
    $stmt_update_pin = $connect->prepare($sql_update_pin);
    if (!$stmt_update_pin) {
        throw new Exception('Prepare update PIN gagal: ' . $connect->error);
    }

    $stmt_update_pin->bind_param('ss', $pin_hash, $no_hp_local);
    if (!$stmt_update_pin->execute()) {
        throw new Exception('Execute update PIN gagal: ' . $stmt_update_pin->error);
    }
    $stmt_update_pin->close();

    // Update status OTP menjadi terpakai
    $sql_update_otp = 'UPDATE katasandi_reset_otps SET status = ? WHERE id = ?';
    $stmt_update_otp = $connect->prepare($sql_update_otp);
    if (!$stmt_update_otp) {
        throw new Exception('Prepare update OTP status gagal: ' . $connect->error);
    }

    $status_terpakai = 'terpakai';
    $stmt_update_otp->bind_param('si', $status_terpakai, $otp_record['id']);
    if (!$stmt_update_otp->execute()) {
        throw new Exception('Execute update OTP status gagal: ' . $stmt_update_otp->error);
    }
    $stmt_update_otp->close();

    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - RESET_PIN_SUCCESS: no_hp=' . $no_hp . ' user_id=' . $user['id'] . PHP_EOL, FILE_APPEND);

    http_response_code(200);
    echo json_encode(array('success' => true, 'status' => true, 'message' => 'PIN berhasil direset. Silakan login dan gunakan PIN baru Anda.'));

} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - RESET_PIN_ERROR: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(array('status' => false, 'message' => 'Server error: ' . $e->getMessage()));
}

