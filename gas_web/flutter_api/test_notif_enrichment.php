<?php
/**
 * TEST SCRIPT: Backend Notification Enrichment
 * 
 * Purpose: Verify that get_notifications.php correctly enriches 
 * notification data with id_transaksi from transaksi table
 * 
 * Usage: Open in browser: http://localhost/gas/gas_web/flutter_api/test_notif_enrichment.php?user_id=123
 */

header('Content-Type: application/json; charset=utf-8');

// Get test user_id from query param
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;

// Include connection
require_once __DIR__ . '/connection.php';

if (!$connect) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$results = [];

// Step 1: Check if test data exists
$results['step_1_check_test_data'] = [];

$q1 = "SELECT id_pengguna FROM pengguna WHERE id = ? LIMIT 1";
$s1 = $connect->prepare($q1);
$s1->bind_param('i', $user_id);
$s1->execute();
$r1 = $s1->get_result();

if ($r1->num_rows > 0) {
    $results['step_1_check_test_data']['user_exists'] = true;
} else {
    $results['step_1_check_test_data']['user_exists'] = false;
    $results['step_1_check_test_data']['note'] = "User not found. Create test user first.";
}
$s1->close();

// Step 2: Check notifications for this user
$results['step_2_notifications'] = [];

$q2 = "SELECT id, type, title, data, read_status FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 5";
$s2 = $connect->prepare($q2);
$s2->bind_param('i', $user_id);
$s2->execute();
$r2 = $s2->get_result();

$notif_count = $r2->num_rows;
$results['step_2_notifications']['count'] = $notif_count;
$results['step_2_notifications']['list'] = [];

while ($row = $r2->fetch_assoc()) {
    $data_parsed = json_decode($row['data'], true);
    
    $item = [
        'id' => (int)$row['id'],
        'type' => $row['type'],
        'title' => $row['title'],
        'data_raw' => $row['data'],
        'data_parsed' => $data_parsed,
        'has_mulai_id' => isset($data_parsed['mulai_id']) ? true : false,
        'has_id_transaksi_before_enrichment' => isset($data_parsed['id_transaksi']) ? true : false,
    ];
    
    // Try enrichment logic
    if ((isset($data_parsed['mulai_id']) || isset($data_parsed['id_mulai_nabung'])) && empty($data_parsed['id_transaksi'])) {
        $mulai_id = $data_parsed['mulai_id'] ?? $data_parsed['id_mulai_nabung'];
        
        $qe = "SELECT id_transaksi FROM transaksi WHERE id_mulai_nabung = ? LIMIT 1";
        $se = $connect->prepare($qe);
        if ($se) {
            $se->bind_param('i', $mulai_id);
            $se->execute();
            $re = $se->get_result();
            
            if ($re->num_rows > 0) {
                $row_e = $re->fetch_assoc();
                $item['enriched_id_transaksi'] = (int)$row_e['id_transaksi'];
                $item['enrichment_status'] = 'SUCCESS - id_transaksi found';
            } else {
                $item['enrichment_status'] = 'NO_MATCH - no transaction with this mulai_id';
            }
            $se->close();
        } else {
            $item['enrichment_status'] = 'QUERY_ERROR - ' . $connect->error;
        }
    } else {
        $item['enrichment_status'] = 'SKIPPED - no mulai_id or already has id_transaksi';
    }
    
    $results['step_2_notifications']['list'][] = $item;
}
$s2->close();

// Step 3: Check corresponding transactions
$results['step_3_transactions'] = [];

$q3 = "SELECT id_transaksi, id_mulai_nabung, jenis_transaksi, jumlah, status FROM transaksi WHERE id_anggota = ? ORDER BY tanggal DESC LIMIT 5";
$s3 = $connect->prepare($q3);
$s3->bind_param('i', $user_id);
$s3->execute();
$r3 = $s3->get_result();

$results['step_3_transactions']['count'] = $r3->num_rows;
$results['step_3_transactions']['list'] = [];

while ($row = $r3->fetch_assoc()) {
    $results['step_3_transactions']['list'][] = [
        'id_transaksi' => (int)$row['id_transaksi'],
        'id_mulai_nabung' => $row['id_mulai_nabung'] ? (int)$row['id_mulai_nabung'] : null,
        'jenis_transaksi' => $row['jenis_transaksi'],
        'jumlah' => (int)$row['jumlah'],
        'status' => $row['status'],
    ];
}
$s3->close();

// Step 4: Call actual API and show result
$results['step_4_api_call'] = [];

ob_start();
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/gas/gas_web/flutter_api/get_notifications.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['id_pengguna' => $user_id]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
ob_end_clean();

$results['step_4_api_call']['http_code'] = $http_code;

if ($http_code === 200) {
    $api_response = json_decode($response, true);
    $results['step_4_api_call']['success'] = $api_response['success'] ?? false;
    $results['step_4_api_call']['data_count'] = count($api_response['data'] ?? []);
    
    // Check first notification for enrichment
    if (!empty($api_response['data'])) {
        $first_notif = $api_response['data'][0];
        $results['step_4_api_call']['first_notification'] = [
            'id' => $first_notif['id'],
            'title' => $first_notif['title'],
            'data' => $first_notif['data'],
            'has_id_transaksi' => isset($first_notif['data']['id_transaksi']) ? true : false,
        ];
    }
} else {
    $results['step_4_api_call']['error'] = "HTTP {$http_code}";
    $results['step_4_api_call']['response'] = substr($response, 0, 200);
}

// Summary
$results['summary'] = [
    'test_user_id' => $user_id,
    'notifications_found' => $notif_count,
    'transactions_found' => $results['step_3_transactions']['count'],
    'api_working' => ($results['step_4_api_call']['http_code'] === 200),
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
