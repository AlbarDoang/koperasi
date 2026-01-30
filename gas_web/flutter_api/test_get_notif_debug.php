<?php
/**
 * DEBUG: Get Notifications for a user
 */
include 'connection.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

$id_pengguna = 3;

try {
    $sql = "SELECT id, id_pengguna, type, title, message, data, read_status, created_at FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 200";
    $stmt = $connect->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $connect->error);
    }
    $stmt->bind_param('i', $id_pengguna);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if (!$res) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    $list = [];
    while ($row = $res->fetch_assoc()) {
        $title_l = mb_strtolower($row['title'] ?? '');
        $msg_l = mb_strtolower($row['message'] ?? '');

        // Exclude noisy ones
        if (strpos($title_l, 'cashback') !== false || strpos($msg_l, 'cashback') !== false) continue;

        // Normalize type
        $type = trim($row['type'] ?? '');
        if ($type === 'mulai_nabung') $type = 'tabungan';
        
        if ($type === '') {
            if (strpos($title_l, 'setoran') !== false || strpos($msg_l, 'setoran') !== false || 
                strpos($title_l, 'pencairan') !== false || strpos($msg_l, 'pencairan') !== false ||
                strpos($title_l, 'mulai nabung') !== false || strpos($msg_l, 'mulai nabung') !== false || 
                strpos($title_l, 'permintaan mulai') !== false) {
                $type = 'tabungan';
            } else {
                $type = 'transaksi';
            }
        }

        // Parse existing data
        $data_parsed = $row['data'] ? json_decode($row['data'], true) : [];
        if (!is_array($data_parsed)) {
            $data_parsed = [];
        }

        // ENHANCEMENT: Enrich data with id_transaksi
        if ((isset($data_parsed['mulai_id']) || isset($data_parsed['id_mulai_nabung'])) && empty($data_parsed['id_transaksi'])) {
            $mulai_id = $data_parsed['mulai_id'] ?? $data_parsed['id_mulai_nabung'];
            if (!empty($mulai_id)) {
                $tx_sql = "SELECT id_transaksi FROM transaksi WHERE id_mulai_nabung = ? LIMIT 1";
                $tx_stmt = $connect->prepare($tx_sql);
                if ($tx_stmt) {
                    $tx_stmt->bind_param('i', $mulai_id);
                    $tx_stmt->execute();
                    $tx_res = $tx_stmt->get_result();
                    if ($tx_row = $tx_res->fetch_assoc()) {
                        $data_parsed['id_transaksi'] = (int)$tx_row['id_transaksi'];
                    }
                    $tx_stmt->close();
                }
            }
        }

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

    $stmt->close();
    echo json_encode(['success' => true, 'data' => $list]);
} catch (Exception $e) {
    error_log('get_notifications.php debug error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
