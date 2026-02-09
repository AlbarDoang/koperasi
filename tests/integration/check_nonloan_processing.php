<?php
// Test: ensure processing/waiting/verification notifications for non-loan types are excluded
$options = getopt('', ['base::', 'db-name::']);
$base = rtrim($options['base'] ?? 'http://192.168.43.151/gas/gas_web', '/');
$dbName = $options['db-name'] ?? 'tabungan';

$mysqli = new mysqli('localhost', 'root', '', $dbName);
if ($mysqli->connect_errno) { echo "DB connect failed: {$mysqli->connect_error}\n"; exit(1); }

$nama = 'test_user_' . time();
$nohp = '081234' . rand(1000,9999);
$alamat = 'Jl Test';
$tgl = '1990-01-01';
$status = 'approved';
$ins = $mysqli->prepare("INSERT INTO pengguna (nama_lengkap, no_hp, alamat_domisili, tanggal_lahir, status_akun, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$ins->bind_param('sssss', $nama, $nohp, $alamat, $tgl, $status);
if (!$ins->execute()) { echo "FAILED insert user: " . $ins->error . "\n"; exit(1); }
$uid = intval($mysqli->insert_id);
$ins->close();

// Insert a tabungan notification with processing-like text
$msg = 'Pengajuan setoran tabungan Anda berhasil dikirim dan sedang menunggu verifikasi dari admin.';
$title = 'Pengajuan Setoran Tabungan';
$type = 'tabungan';
$stmt = $mysqli->prepare("INSERT INTO notifikasi (id_pengguna, type, title, message, data, read_status, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
$json = json_encode(['test' => 'processing_nonloan']);
$stmt->bind_param('issss', $uid, $type, $title, $msg, $json);
if (!$stmt->execute()) { echo "FAILED insert notif: " . $stmt->error . "\n"; exit(1); }
$nid = intval($mysqli->insert_id);
$stmt->close();

echo "Inserted notif id={$nid} for user {$uid}\n";

// Call get_notifications.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base . '/flutter_api/get_notifications.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['id_pengguna' => $uid]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
echo "Body: $res\n";

// Verify response: should NOT contain the inserted notification
$body = json_decode($res, true);
$found = false;
if ($body && isset($body['data']) && is_array($body['data'])) {
    foreach ($body['data'] as $item) {
        if (($item['title'] ?? '') === $title && ($item['message'] ?? '') === $msg) {
            $found = true; break;
        }
    }
}

if ($found) {
    echo "TEST FAILED: processing notification for non-loan type was returned by API\n";
    $ret = 1;
} else {
    echo "TEST PASSED: notification correctly excluded\n";
    $ret = 0;
}

// cleanup
$mysqli->query("DELETE FROM notifikasi WHERE id = " . intval($nid));
$mysqli->query("DELETE FROM pengguna WHERE id = " . intval($uid));

exit($ret);

