<?php
// Simple CLI test: simulate user cairkan_tabungan then admin approve_penarikan
$base = 'http://192.168.43.151/gas/gas_web/flutter_api';

// Configure test parameters
$id_pengguna = $argv[1] ?? '95';
$id_jenis = $argv[2] ?? '1';
$nominal = $argv[3] ?? '10000';
$admin_id = $argv[4] ?? '1';

function post($url, $data) {
    $opts = ['http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($data),
        'timeout' => 10
    ]];
    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);
    return $res === false ? null : json_decode($res, true);
}

echo "STEP 1: Cairkan (user)\n";
$res1 = post($base . '/cairkan_tabungan.php', ['id_pengguna' => $id_pengguna, 'id_jenis_tabungan' => $id_jenis, 'nominal' => $nominal]);
print_r($res1);

if (!($res1 && isset($res1['status']) && $res1['status'] === true)) {
    echo "User request failed; aborting test.\n";
    exit(1);
}

// Give the system a moment (DB commit) then fetch admin list to find the no_penarikan
sleep(1);

echo "STEP 2: Fetch admin pending list (first page) to locate the created entry\n";
$adminFetchUrl = 'http://localhost/gas/gas_web/login/function/fetch_keluar_admin.php';
$fetchRes = post($adminFetchUrl, ['draw' => 1, 'start' => 0, 'length' => 10, 'status' => 'pending']);
// Find entry for our user
$found = null;
if ($fetchRes && isset($fetchRes['data'])) {
    foreach ($fetchRes['data'] as $row) {
        // row[2] is id_tabungan column
        if (stripos($row[2], (string)$id_pengguna) !== false) { $found = $row; break; }
    }
}

print_r($found);
if (!$found) { echo "Pending entry not found in admin list.\n"; exit(2); }

// Extract no_penarikan from column row[1]
$no = strip_tags($found[1]);
$no = trim($no);
echo "Found pending no: $no\n";

echo "STEP 3: Admin approves\n";
$approveRes = post($base . '/approve_penarikan.php', ['no_keluar' => $no, 'action' => 'approve', 'approved_by' => $admin_id]);
print_r($approveRes);

if (!($approveRes && isset($approveRes['success']) && $approveRes['success'] === true)) {
    echo "Approve failed\n"; exit(3);
}

echo "Test complete.\n";
?>
