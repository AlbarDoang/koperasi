<?php
/**
 * COMPREHENSIVE TEST - Mulai Nabung Complete Flow
 * Tests: Submit â†’ Approve/Reject â†’ Verify Riwayat
 */

// Config
$api_base = "http://192.168.1.38/gas/gas_web/flutter_api/";
$test_user_hp = "081990608817";
$test_user_id = 3;

// Test results
$results = [];
$errors = [];

function log_test($title, $success, $message = "", $data = null) {
    global $results, $errors;
    $results[] = [
        'title' => $title,
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    if (!$success) {
        $errors[] = "$title: $message";
    }
    echo ($success ? "âœ…" : "âŒ") . " $title" . ($message ? " - $message" : "") . "\n";
}

echo "=== STARTING COMPREHENSIVE FLOW TEST ===\n\n";

// TEST 1: Submit Mulai Nabung (creates pending transaction)
echo "--- TEST 1: Submit Mulai Nabung ---\n";
$submit_payload = [
    'id_tabungan' => 1,
    'nomor_hp' => $test_user_hp,
    'nama_pengguna' => 'jtbttn',
    'jumlah' => 100000,
    'jenis_tabungan' => 'Tabungan Reguler'
];

$ch = curl_init($api_base . 'buat_mulai_nabung.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($submit_payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$submit_data = json_decode($response, true);
log_test("Submit Mulai Nabung", 
    $http_code == 200 && $submit_data['success'] == true,
    "HTTP $http_code",
    $submit_data
);

if (!$submit_data['success']) {
    echo "ERROR: " . $submit_data['message'] . "\n";
    die();
}

$id_mulai_nabung = $submit_data['id_mulai_nabung'] ?? null;
echo "â†’ ID Mulai Nabung: $id_mulai_nabung\n\n";

// TEST 2: Check Riwayat Tabungan (should show pending entry)
echo "--- TEST 2: Check Riwayat Tabungan (Pending) ---\n";
$ch = curl_init($api_base . 'get_riwayat_tabungan.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET'
]);
curl_setopt($ch, CURLOPT_URL, $api_base . 'get_riwayat_tabungan.php?nomor_hp=' . urlencode($test_user_hp) . '&jenis_tabungan=Tabungan%20Reguler');
$response = curl_exec($ch);
curl_close($ch);

$riwayat_data = json_decode($response, true);
$has_pending = false;
if ($riwayat_data['success'] && is_array($riwayat_data['data'])) {
    foreach ($riwayat_data['data'] as $item) {
        if ($item['id'] == $id_mulai_nabung && in_array($item['status'], ['pending', 'menunggu_penyerahan'])) {
            $has_pending = true;
            break;
        }
    }
}
log_test("Riwayat shows pending entry", $has_pending, 
    "Found in riwayat: " . ($has_pending ? "YES" : "NO")
);
echo "\n";

// TEST 3: Check Transaction Records (should have pending transaction)
echo "--- TEST 3: Check Transaction Records (Pending) ---\n";
$ch = curl_init($api_base . 'get_riwayat_transaksi.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET'
]);
curl_setopt($ch, CURLOPT_URL, $api_base . 'get_riwayat_transaksi.php?id_pengguna=' . $test_user_id);
$response = curl_exec($ch);
curl_close($ch);

$transaksi_data = json_decode($response, true);
$has_pending_trans = false;
if ($transaksi_data['success'] && is_array($transaksi_data['data'])) {
    foreach ($transaksi_data['data'] as $trans) {
        if ($trans['status'] == 'pending' && strpos($trans['keterangan'] ?? '', 'mulai_nabung') !== false) {
            $has_pending_trans = true;
            break;
        }
    }
}
log_test("Transaction shows pending entry", $has_pending_trans, 
    "Found: " . ($has_pending_trans ? "YES" : "NO")
);
echo "\n";

// TEST 4: Admin Approve
echo "--- TEST 4: Admin Approve Mulai Nabung ---\n";
$approve_payload = [
    'id_mulai_nabung' => $id_mulai_nabung,
    'action' => 'setuju'
];

$ch = curl_init($api_base . 'admin_verifikasi_mulai_nabung.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($approve_payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$approve_data = json_decode($response, true);
log_test("Admin Approve", 
    $http_code == 200 && ($approve_data['success'] == true || $approve_data['status'] == true),
    "HTTP $http_code",
    $approve_data
);

if (!($approve_data['success'] ?? $approve_data['status'] ?? false)) {
    echo "ERROR: " . ($approve_data['message'] ?? "Unknown error") . "\n";
}
echo "\n";

// TEST 5: Check Transaction After Approve (should show approved)
echo "--- TEST 5: Check Transaction After Approve ---\n";
$ch = curl_init($api_base . 'get_riwayat_transaksi.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET'
]);
curl_setopt($ch, CURLOPT_URL, $api_base . 'get_riwayat_transaksi.php?id_pengguna=' . $test_user_id);
$response = curl_exec($ch);
curl_close($ch);

$transaksi_data = json_decode($response, true);
$has_approved_trans = false;
if ($transaksi_data['success'] && is_array($transaksi_data['data'])) {
    foreach ($transaksi_data['data'] as $trans) {
        if ($trans['status'] == 'approved' && strpos($trans['keterangan'] ?? '', 'mulai_nabung') !== false) {
            $has_approved_trans = true;
            break;
        }
    }
}
log_test("Transaction shows approved entry", $has_approved_trans, 
    "Found: " . ($has_approved_trans ? "YES" : "NO")
);
echo "\n";

// TEST 6: Submit second Mulai Nabung (for reject test)
echo "--- TEST 6: Submit Second Mulai Nabung (for reject) ---\n";
$submit_payload2 = [
    'id_tabungan' => 1,
    'nomor_hp' => $test_user_hp,
    'nama_pengguna' => 'jtbttn',
    'jumlah' => 50000,
    'jenis_tabungan' => 'Tabungan Reguler'
];

$ch = curl_init($api_base . 'buat_mulai_nabung.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($submit_payload2),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$submit_data2 = json_decode($response, true);
log_test("Submit Second Mulai Nabung", 
    $http_code == 200 && $submit_data2['success'] == true,
    "HTTP $http_code"
);

$id_mulai_nabung2 = $submit_data2['id_mulai_nabung'] ?? null;
echo "â†’ ID Mulai Nabung: $id_mulai_nabung2\n\n";

// TEST 7: Admin Reject
echo "--- TEST 7: Admin Reject Second Mulai Nabung ---\n";
$reject_payload = [
    'id_mulai_nabung' => $id_mulai_nabung2,
    'action' => 'tolak'
];

$ch = curl_init($api_base . 'admin_verifikasi_mulai_nabung.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($reject_payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$reject_data = json_decode($response, true);
log_test("Admin Reject", 
    $http_code == 200 && ($reject_data['success'] == true || $reject_data['status'] == true),
    "HTTP $http_code"
);

if (!($reject_data['success'] ?? $reject_data['status'] ?? false)) {
    echo "ERROR: " . ($reject_data['message'] ?? "Unknown error") . "\n";
}
echo "\n";

// TEST 8: Check Transaction After Reject (should show ditolak/rejected)
echo "--- TEST 8: Check Transaction After Reject ---\n";
$ch = curl_init($api_base . 'get_riwayat_transaksi.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET'
]);
curl_setopt($ch, CURLOPT_URL, $api_base . 'get_riwayat_transaksi.php?id_pengguna=' . $test_user_id);
$response = curl_exec($ch);
curl_close($ch);

$transaksi_data = json_decode($response, true);
$has_rejected_trans = false;
if ($transaksi_data['success'] && is_array($transaksi_data['data'])) {
    foreach ($transaksi_data['data'] as $trans) {
        if (in_array($trans['status'], ['ditolak', 'rejected']) && strpos($trans['keterangan'] ?? '', 'mulai_nabung') !== false) {
            $has_rejected_trans = true;
            break;
        }
    }
}
log_test("Transaction shows rejected entry", $has_rejected_trans, 
    "Found: " . ($has_rejected_trans ? "YES" : "NO")
);
echo "\n";

// FINAL RESULTS
echo "=== TEST RESULTS ===\n";
$total = count($results);
$passed = array_sum(array_map(fn($r) => $r['success'] ? 1 : 0, $results));
echo "Passed: $passed / $total\n";

if (count($errors) > 0) {
    echo "\nERRORS:\n";
    foreach ($errors as $error) {
        echo "  âŒ $error\n";
    }
} else {
    echo "\nâœ… ALL TESTS PASSED!\n";
}

echo "\n=== RAW RESULTS ===\n";
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

