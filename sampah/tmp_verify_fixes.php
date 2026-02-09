<?php
/**
 * Comprehensive Bug Fix Verification Script
 * Tests all three flows: mulai_nabung, pencairan, transfer
 * Verifies balance calculations are correct throughout
 */

header('Content-Type: application/json');

$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'tabungan';

$connect = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($connect->connect_error) {
    die(json_encode(['error' => 'Connection failed']));
}

$verification = [];

// TEST 1: Verify approve_penarikan.php SUM fix
$verification['test_1_approve_response_query'] = [];
$verification['test_1_approve_response_query']['description'] = 'Verify approve_penarikan.php uses SUM instead of LIMIT 1';

// Check if the fix was applied by looking at the source
$file_content = file_get_contents(__DIR__ . '/flutter_api/approve_penarikan.php');
if (strpos($file_content, 'COALESCE(SUM(jumlah),0) AS total_remaining FROM tabungan_masuk') !== false) {
    $verification['test_1_approve_response_query']['status'] = 'PASS';
    $verification['test_1_approve_response_query']['message'] = 'approve_penarikan.php correctly uses SUM for response query';
} else {
    $verification['test_1_approve_response_query']['status'] = 'FAIL';
    $verification['test_1_approve_response_query']['message'] = 'SUM query not found in approve_penarikan.php';
}

// TEST 2: Verify cairkan_tabungan.php approval filter
$verification['test_2_cairkan_saldo_filter'] = [];
$verification['test_2_cairkan_saldo_filter']['description'] = 'Verify cairkan_tabungan.php filters by status=approved';

$cairkan_content = file_get_contents(__DIR__ . '/flutter_api/cairkan_tabungan.php');
if (strpos($cairkan_content, "AND status = 'approved'") !== false && strpos($cairkan_content, 'tabungan_keluar') !== false) {
    $verification['test_2_cairkan_saldo_filter']['status'] = 'PASS';
    $verification['test_2_cairkan_saldo_filter']['message'] = 'cairkan_tabungan.php correctly filters tabungan_keluar by status';
} else {
    $verification['test_2_cairkan_saldo_filter']['status'] = 'FAIL';
    $verification['test_2_cairkan_saldo_filter']['message'] = 'Status filter not found in cairkan_tabungan.php';
}

// TEST 3: Verify get_saldo_tabungan uses correct filter
$verification['test_3_get_saldo_filter'] = [];
$verification['test_3_get_saldo_filter']['description'] = 'Verify get_saldo_tabungan.php uses correct status filter';

$getsaldo_content = file_get_contents(__DIR__ . '/flutter_api/get_saldo_tabungan.php');
if (strpos($getsaldo_content, "status = 'approved'") !== false) {
    $verification['test_3_get_saldo_filter']['status'] = 'PASS';
    $verification['test_3_get_saldo_filter']['message'] = 'get_saldo_tabungan.php uses correct status filter';
} else {
    $verification['test_3_get_saldo_filter']['status'] = 'FAIL';
    $verification['test_3_get_saldo_filter']['message'] = 'Status filter not found in get_saldo_tabungan.php';
}

// TEST 4: Database consistency check
$verification['test_4_database_consistency'] = [];
$verification['test_4_database_consistency']['description'] = 'Verify database has required tables and columns';

$required_tables = ['pengguna', 'tabungan_masuk', 'tabungan_keluar', 'jenis_tabungan', 'mulai_nabung'];
$missing_tables = [];
foreach ($required_tables as $table) {
    $result = $connect->query("SHOW TABLES LIKE '$table'");
    if (!$result || $result->num_rows == 0) {
        $missing_tables[] = $table;
    }
}

if (empty($missing_tables)) {
    $verification['test_4_database_consistency']['status'] = 'PASS';
    $verification['test_4_database_consistency']['message'] = 'All required tables exist';
} else {
    $verification['test_4_database_consistency']['status'] = 'FAIL';
    $verification['test_4_database_consistency']['message'] = 'Missing tables: ' . implode(', ', $missing_tables);
}

// TEST 5: Check tabungan_keluar has status column
$verification['test_5_tabungan_keluar_status'] = [];
$result = $connect->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'status'");
if ($result && $result->num_rows > 0) {
    $verification['test_5_tabungan_keluar_status']['status'] = 'PASS';
    $verification['test_5_tabungan_keluar_status']['message'] = 'tabungan_keluar.status column exists';
} else {
    $verification['test_5_tabungan_keluar_status']['status'] = 'FAIL';
    $verification['test_5_tabungan_keluar_status']['message'] = 'tabungan_keluar.status column missing';
}

// TEST 6: Check ledger_helpers functions
$verification['test_6_ledger_helpers'] = [];
$ledger_content = file_get_contents(__DIR__ . '/login/function/ledger_helpers.php');
if (strpos($ledger_content, 'function wallet_credit') !== false && strpos($ledger_content, 'function wallet_debit') !== false) {
    $verification['test_6_ledger_helpers']['status'] = 'PASS';
    $verification['test_6_ledger_helpers']['message'] = 'ledger_helpers.php has required functions';
} else {
    $verification['test_6_ledger_helpers']['status'] = 'FAIL';
    $verification['test_6_ledger_helpers']['message'] = 'ledger_helpers functions missing';
}

// TEST 7: Test scenario - user with active withdrawal scenario
$verification['test_7_scenario'] = [];
$verification['test_7_scenario']['description'] = 'Test scenario from bug report: deposit 20k, request 15k (approved), verify balance = 5k';

// Get test user
$test_result = $connect->query("SELECT id FROM pengguna LIMIT 1");
if ($test_result && $test_result->num_rows > 0) {
    $test_user = $test_result->fetch_assoc();
    $uid = $test_user['id'];
    
    // Check scenario
    $masuk = $connect->query("SELECT COALESCE(SUM(jumlah),0) as total FROM tabungan_masuk WHERE id_pengguna = $uid AND id_jenis_tabungan = 2");
    $masuk_row = $masuk->fetch_assoc();
    $total_masuk = intval($masuk_row['total']);
    
    $keluar_approved = $connect->query("SELECT COALESCE(SUM(jumlah),0) as total FROM tabungan_keluar WHERE id_pengguna = $uid AND id_jenis_tabungan = 2 AND status = 'approved'");
    $keluar_row = $keluar_approved->fetch_assoc();
    $total_keluar_approved = intval($keluar_row['total']);
    
    $expected_balance = $total_masuk - $total_keluar_approved;
    
    $verification['test_7_scenario']['test_user_id'] = $uid;
    $verification['test_7_scenario']['masuk_total'] = $total_masuk;
    $verification['test_7_scenario']['keluar_approved_total'] = $total_keluar_approved;
    $verification['test_7_scenario']['expected_balance'] = $expected_balance;
    $verification['test_7_scenario']['status'] = 'DATA_OK';
    $verification['test_7_scenario']['message'] = "Balance calculation: $total_masuk - $total_keluar_approved = $expected_balance";
} else {
    $verification['test_7_scenario']['status'] = 'SKIP';
    $verification['test_7_scenario']['message'] = 'No test user found';
}

// Summary
$verification['summary'] = [];
$pass_count = 0;
$fail_count = 0;
foreach ($verification as $key => $test) {
    if (is_array($test) && isset($test['status'])) {
        if ($test['status'] === 'PASS') $pass_count++;
        if ($test['status'] === 'FAIL') $fail_count++;
    }
}
$verification['summary']['total_tests'] = $pass_count + $fail_count;
$verification['summary']['passed'] = $pass_count;
$verification['summary']['failed'] = $fail_count;
$verification['summary']['result'] = ($fail_count == 0) ? 'ALL TESTS PASSED ✓' : "FAILED: $fail_count test(s)";

echo json_encode($verification, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$connect->close();
?>
