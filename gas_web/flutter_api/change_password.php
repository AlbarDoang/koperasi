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
        echo json_encode(array('status' => false, 'message' => 'Method not allowed'));
        exit();
    }

    $id_pengguna = getPostData('id_pengguna');
    $kata_sandi_lama = getPostData('kata_sandi_lama');
    $kata_sandi_baru = getPostData('kata_sandi_baru');
    $konfirmasi = getPostData('konfirmasi');

    // Validasi input
    if (empty($id_pengguna) || empty($kata_sandi_lama) || empty($kata_sandi_baru) || empty($konfirmasi)) {
        http_response_code(422);
        echo json_encode(array('status' => false, 'message' => 'Semua field wajib diisi'));
        exit();
    }

    if ($kata_sandi_baru !== $konfirmasi) {
        http_response_code(400);
        echo json_encode(array('status' => false, 'message' => 'Konfirmasi kata sandi tidak cocok'));
        exit();
    }

    // Ambil kata_sandi tersimpan dari DB
    $sql = 'SELECT id, kata_sandi FROM pengguna WHERE id = ? LIMIT 1';
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
        echo json_encode(array('status' => false, 'message' => 'Pengguna tidak ditemukan'));
        exit();
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    $stored = $row['kata_sandi'];

    $is_valid_old = false;

    // Jika kolom kosong atau null -> tidak valid
    if (!empty($stored)) {
        // Jika password disimpan dengan password_hash (bcrypt/argon)
        if (password_verify($kata_sandi_lama, $stored)) {
            $is_valid_old = true;
        } else {
            // Fallback: cek sha1 (kadang ada implementasi lama) atau kecocokan langsung
            if (sha1($kata_sandi_lama) === $stored || $kata_sandi_lama === $stored) {
                $is_valid_old = true;
            }
        }
    }

    if (!$is_valid_old) {
        // Log diagnostic info for failed verification (do not log plaintext password)
        $stored_preview = substr($stored, 0, 30);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
        @file_put_contents($logFile, date('Y-m-d H:i:s') . " - CHANGE_PASSWORD_FAILED: id={$id_pengguna} ip={$ip} stored_preview={$stored_preview} request_method={$_SERVER['REQUEST_METHOD']}\n", FILE_APPEND);

        http_response_code(401);
        echo json_encode(array('status' => false, 'message' => 'Kata sandi lama tidak sesuai'));
        exit();
    }

    // Hash kata sandi baru
    $new_hash = password_hash($kata_sandi_baru, PASSWORD_DEFAULT);

    $sql_update = 'UPDATE pengguna SET kata_sandi = ? WHERE id = ?';
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

    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - CHANGE_PASSWORD: id=' . $id_pengguna . ' affected=' . $affected . PHP_EOL, FILE_APPEND);

    echo json_encode(array('status' => true, 'message' => 'Kata sandi berhasil diubah'));

} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - Exception CHANGE_PASSWORD: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode(array('status' => false, 'message' => 'Server error: ' . $e->getMessage()));
}

