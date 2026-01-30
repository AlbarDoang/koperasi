<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json; charset=utf-8');

require_once('connection.php');
require_once('helpers.php');

$logFile = __DIR__ . '/log_db.txt';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
        exit();
    }

    $id_pengguna = getPostData('id_pengguna');
    $pin_lama = getPostData('pin_lama');
    $pin_baru = getPostData('pin_baru');
    $konfirmasi = getPostData('konfirmasi');

    if (empty($id_pengguna) || empty($pin_lama) || empty($pin_baru) || empty($konfirmasi)) {
        http_response_code(422);
        echo json_encode(array('success' => false, 'message' => 'Semua field wajib diisi'));
        exit();
    }

    if (!preg_match('/^[0-9]{6}$/', $pin_baru) || !preg_match('/^[0-9]{6}$/', $pin_lama)) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'PIN harus 6 digit angka'));
        exit();
    }

    if ($pin_baru !== $konfirmasi) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Konfirmasi PIN tidak cocok'));
        exit();
    }

    $sql = 'SELECT id, pin FROM pengguna WHERE id = ? LIMIT 1';
    $stmt = $connect->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare query gagal: ' . $connect->error);
    }
    $stmt->bind_param('s', $id_pengguna);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'Pengguna tidak ditemukan'));
        exit();
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    $stored = $row['pin'];

    $is_valid_old = false;
    if (!empty($stored)) {
        if (password_verify($pin_lama, $stored)) {
            $is_valid_old = true;
        } else {
            if (sha1($pin_lama) === $stored || $pin_lama === $stored) {
                $is_valid_old = true;
            }
        }
    }

    if (!$is_valid_old) {
        $stored_preview = substr($stored, 0, 30);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " - CHANGE_PIN_FAILED: id={$id_pengguna} ip={$ip} stored_preview={$stored_preview}\n", FILE_APPEND);
        http_response_code(401);
        echo json_encode(array('success' => false, 'message' => 'PIN lama tidak sesuai'));
        exit();
    }

    $new_hash = password_hash($pin_baru, PASSWORD_DEFAULT);

    $sql_update = 'UPDATE pengguna SET pin = ? WHERE id = ?';
    $stmt_up = $connect->prepare($sql_update);
    if (!$stmt_up) {
        throw new Exception('Prepare update gagal: ' . $connect->error);
    }
    $stmt_up->bind_param('ss', $new_hash, $id_pengguna);
    if (!$stmt_up->execute()) {
        throw new Exception('Execute update gagal: ' . $stmt_up->error);
    }
    $affected = $stmt_up->affected_rows;
    $stmt_up->close();

    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - CHANGE_PIN: id=' . $id_pengguna . ' affected=' . $affected . PHP_EOL, FILE_APPEND);

    echo json_encode(array('success' => true, 'message' => 'PIN berhasil diubah'));

} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - Exception CHANGE_PIN: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Server error: ' . $e->getMessage()));
}

