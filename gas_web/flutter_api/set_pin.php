<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once('connection.php');
require_once('helpers.php');

$logFile = __DIR__ . '/log_db.txt';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user_id = getPostData('user_id');
        $pin = getPostData('pin');
        $pin_confirm = getPostData('pin_confirm');

        if (empty($user_id) || empty($pin) || empty($pin_confirm)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID, PIN, dan konfirmasi PIN wajib diisi'
            ]);
            exit();
        }

        $user_id = trim($user_id);
        $pin = trim($pin);
        $pin_confirm = trim($pin_confirm);

        if (!preg_match('/^\d{6}$/', $pin)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'PIN harus 6 digit angka'
            ]);
            exit();
        }

        if ($pin !== $pin_confirm) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'PIN tidak cocok. Silakan coba lagi.'
            ]);
            exit();
        }

        $sql_check = 'SELECT id, nama_lengkap, status_akun FROM pengguna WHERE id = ? LIMIT 1';
        $stmt_check = $connect->prepare($sql_check);
        if (!$stmt_check) {
            $err = 'Prepare stmt_check gagal: ' . $connect->error;
            @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $err . PHP_EOL, FILE_APPEND);
            throw new Exception($err);
        }
        $stmt_check->bind_param('i', $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows === 0) {
            $stmt_check->close();
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User tidak ditemukan'
            ]);
            exit();
        }

        $user = $result_check->fetch_assoc();
        $stmt_check->close();

        $status_akun = strtoupper(trim($user['status_akun']));
        if ($status_akun !== 'APPROVED') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Akun Anda belum disetujui. Silakan menunggu persetujuan admin.'
            ]);
            exit();
        }

        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);

        $sql_update = 'UPDATE pengguna SET pin = ? WHERE id = ?';
        $stmt_update = $connect->prepare($sql_update);
        if (!$stmt_update) {
            $err = 'Prepare stmt_update gagal: ' . $connect->error;
            @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $err . PHP_EOL, FILE_APPEND);
            throw new Exception($err);
        }
        $stmt_update->bind_param('si', $pin_hash, $user_id);
        if (!$stmt_update->execute()) {
            $err = 'Execute stmt_update gagal: ' . $stmt_update->error;
            @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $err . PHP_EOL, FILE_APPEND);
            $stmt_update->close();
            throw new Exception($err);
        }
        $stmt_update->close();

        @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - PIN_SET_SUCCESS: user_id=' . $user_id . PHP_EOL, FILE_APPEND);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'PIN transaksi berhasil diatur. Anda akan diarahkan ke Dashboard.'
        ]);
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed. Use POST'
        ]);
    }
} catch (Exception $e) {
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - Exception: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
