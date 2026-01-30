<?php
/**
 * API: Get Notifications for a user
 * POST: id_pengguna
 */
include 'connection.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

// Accept both POST (normal API) and GET (manual browser/debug) requests
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['POST', 'GET'])) {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Support id_pengguna via POST or GET for ease of debugging in a browser
$id_pengguna_input = null;
if ($method === 'POST') {
    $id_pengguna_input = $_POST['id_pengguna'] ?? null;
} else {
    // GET
    $id_pengguna_input = $_GET['id_pengguna'] ?? null;
}

// Ensure numeric id_pengguna for safe binding
$id_pengguna = intval($id_pengguna_input ?? 0);
if ($id_pengguna <= 0) {
    echo json_encode(['success' => false, 'message' => 'id_pengguna dibutuhkan dan harus numerik (POST/GET param id_pengguna)']);
    exit();
}

try {
    // Simpler: fetch all notifications for the user and apply filtering + normalization in PHP
    $sql = "SELECT id, id_pengguna, type, title, message, data, read_status, created_at FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 200";
    $stmt = $connect->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed: ' . $connect->error);
    $stmt->bind_param('i', $id_pengguna);
    $stmt->execute();
    $res = $stmt->get_result();

    $list = [];
    while ($row = $res->fetch_assoc()) {
        $title_l = mb_strtolower($row['title'] ?? '');
        $msg_l = mb_strtolower($row['message'] ?? '');

        // Exclude noisy ones
        if (strpos($title_l, 'cashback') !== false || strpos($msg_l, 'cashback') !== false) continue;
        


        // Normalize type: ensure non-empty, map legacy 'mulai_nabung' to 'tabungan',
        // and detect tabungan entries when type is missing so mobile will display them.
        $type = trim($row['type'] ?? '');
        if ($type === 'mulai_nabung') $type = 'tabungan';
        
        // Handle withdrawal notification types (map to 'tabungan' for UI consistency)
        if ($type === 'withdrawal_pending' || $type === 'withdrawal_approved' || $type === 'withdrawal_rejected') {
            $type = 'tabungan';
        }
        
        // Ensure notifications are correctly categorized for user actions.
        if ($type === '') {
            // Detect tabungan-related notifications by keywords and default to 'tabungan'
            if (strpos($title_l, 'setoran') !== false || strpos($msg_l, 'setoran') !== false || 
                strpos($title_l, 'pencairan') !== false || strpos($msg_l, 'pencairan') !== false ||
                strpos($title_l, 'mulai nabung') !== false || strpos($msg_l, 'mulai nabung') !== false || 
                strpos($title_l, 'permintaan mulai') !== false) {
                $type = 'tabungan';
            } else {
                // Default to 'transaksi' to ensure UI accepts the notification
                $type = 'transaksi';
            }
        }

        // Parse existing data or create new data object
        $data_parsed = $row['data'] ? json_decode($row['data'], true) : [];
        if (!is_array($data_parsed)) {
            $data_parsed = [];
        }

        // NOTE: Direct navigation is handled by frontend using mulai_id from data
        // No need to query transaksi table - the notification data already has what we need
        // Frontend will use mulai_id or id_mulai_nabung to navigate directly to transaction detail

        $list[] = [
            'id' => (int)$row['id'],
            'type' => $type,
            'title' => $row['title'],
            'message' => $row['message'],
            'data' => !empty($data_parsed) ? $data_parsed : null,
            'read' => (bool)$row['read_status'],
            'created_at' => $row['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $list]);
} catch (Exception $e) {
    error_log('get_notifications.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}

