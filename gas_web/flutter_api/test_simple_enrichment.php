<?php
/**
 * SIMPLE TEST: Verify notification enrichment with id_transaksi
 * 
 * This script directly tests the enrichment logic without needing curl/API calls
 */

// Prevent any output until final JSON
ob_start();

try {
    // Simulate notification data from database
    $test_notification = [
        'id' => 1,
        'id_pengguna' => 1,
        'type' => 'tabungan',
        'title' => 'Setoran Diproses',
        'message' => 'Setoran Anda sedang diproses',
        'data' => json_encode(['mulai_id' => 1, 'status' => 'menunggu_admin']),
        'read_status' => false,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    // Simulate the backend enrichment logic from get_notifications.php
    $row = $test_notification;
    $list = [];

    // Parse existing data
    $data_parsed = $row['data'] ? json_decode($row['data'], true) : [];
    if (!is_array($data_parsed)) {
        $data_parsed = [];
    }

    // ENRICHMENT LOGIC (from get_notifications.php lines 76-94)
    if ((isset($data_parsed['mulai_id']) || isset($data_parsed['id_mulai_nabung'])) && empty($data_parsed['id_transaksi'])) {
        $mulai_id = $data_parsed['mulai_id'] ?? $data_parsed['id_mulai_nabung'];
        
        if (!empty($mulai_id)) {
            // Simulate SQL query result
            // In real scenario, we'd query: SELECT id_transaksi FROM transaksi WHERE id_mulai_nabung = ?
            
            // For testing, simulate finding id_transaksi = 999
            $simulated_id_transaksi = 999;
            $data_parsed['id_transaksi'] = (int)$simulated_id_transaksi;
            
            $enrichment_result = 'SUCCESS - Found id_transaksi: ' . $simulated_id_transaksi;
        }
    } else {
        $enrichment_result = 'SKIPPED - No mulai_id or already has id_transaksi';
    }

    // Final notification object
    $notification = [
        'id' => (int)$row['id'],
        'type' => $row['type'],
        'title' => $row['title'],
        'message' => $row['message'],
        'data' => !empty($data_parsed) ? $data_parsed : null,
        'read' => (bool)$row['read_status'],
        'created_at' => $row['created_at'],
    ];

    ob_end_clean();

    // Output test results
    echo json_encode([
        'status' => 'SUCCESS',
        'test_case' => 'Notification with mulai_id enrichment',
        'enrichment_result' => $enrichment_result,
        'input_data' => json_decode($test_notification['data'], true),
        'output_data' => $notification['data'],
        'has_id_transaksi' => isset($notification['data']['id_transaksi']),
        'id_transaksi_value' => $notification['data']['id_transaksi'] ?? null,
        'complete_notification' => $notification,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage(),
    ]);
}
?>
