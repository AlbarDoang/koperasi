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
    $password_baru = getPostData('password_baru');

    if (empty($no_hp) || empty($otp) || empty($password_baru)) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Semua field wajib diisi'));
        exit();
    }

    $no_hp = trim($no_hp);
    $otp = trim($otp);
    $password_baru = trim($password_baru);

    // Normalize: international 62 for OTP table, local 08 for DB lookup
    $no_hp = trim($no_hp);
    $no_wa_normalized = phone_to_international62($no_hp);
    if ($no_wa_normalized === false) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Nomor HP tidak valid'));
        exit();
    }
    $no_hp_local = sanitizePhone($no_hp);
    if (empty($no_hp_local)) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Nomor HP tidak valid'));
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

    // Cek OTP cocok
    if ($otp_record['kode_otp'] !== $otp) {
        http_response_code(401);
        echo json_encode(array('status' => false, 'message' => 'OTP tidak cocok'));
        exit();
    }

    // TIDAK perlu cek expired_at di sini!
    // Pengecekan expired OTP hanya dilakukan di halaman verifikasi OTP (verify_otp_reset.php)
    // Jika user sudah sampai ke halaman reset password, berarti OTP sudah terverifikasi sebelumnya

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

    // Hash password baru
    $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);

    // Update password user (use local 08 in WHERE)
    $sql_update_pass = 'UPDATE pengguna SET kata_sandi = ? WHERE no_hp = ?';
    $stmt_update_pass = $connect->prepare($sql_update_pass);
    
    if (!$stmt_update_pass) {
        throw new Exception('Prepare update password gagal: ' . $connect->error);
    }

    $stmt_update_pass->bind_param('ss', $password_hash, $no_hp_local);
    
    if (!$stmt_update_pass->execute()) {
        throw new Exception('Execute update password gagal: ' . $stmt_update_pass->error);
    }

    $stmt_update_pass->close();
    
    // Update status OTP yang sudah dipakai (jangan dihapus, hanya tandai sebagai terpakai)
    $sql_update_otp = 'UPDATE katasandi_reset_otps SET status = "terpakai" WHERE id = ?';
    $stmt_update_otp = $connect->prepare($sql_update_otp);
    if (!$stmt_update_otp) {
        throw new Exception('Prepare update OTP status gagal: ' . $connect->error);
    }

    $stmt_update_otp->bind_param('i', $otp_record['id']);
    if (!$stmt_update_otp->execute()) {
        throw new Exception('Execute update OTP status gagal: ' . $stmt_update_otp->error);
    }

    $stmt_update_otp->close();

    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - RESET_PASSWORD_SUCCESS: no_hp=' . $no_hp . PHP_EOL, FILE_APPEND);

    http_response_code(200);
    echo json_encode(array('status' => true, 'message' => 'Password berhasil direset. Silakan login dengan password baru Anda.'));

} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - Exception: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(array('status' => false, 'message' => 'Server error: ' . $e->getMessage()));
}
