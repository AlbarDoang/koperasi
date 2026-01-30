<?php
// Simple manual check: create a user, insert a pinjaman notification, and call get_notifications.php
$options = getopt('', ['base::', 'db-name::']);
$base = rtrim($options['base'] ?? 'http://192.168.1.8/gas/gas_web', '/');
$dbName = $options['db-name'] ?? 'tabungan';

$mysqli = new mysqli('localhost', 'root', '', $dbName);
if ($mysqli->connect_errno) { echo "DB connect failed: {$mysqli->connect_error}\n"; exit(1); }

$nama = 'manual_test_user_' . time();
$nohp = '081234' . rand(1000,9999);
$alamat = 'Jl Manual Test';
$tgl = '1990-01-01';
$status = 'approved';
$ins = $mysqli->prepare("INSERT INTO pengguna (nama_lengkap, no_hp, alamat_domisili, tanggal_lahir, status_akun, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$ins->bind_param('sssss', $nama, $nohp, $alamat, $tgl, $status);
if (!$ins->execute()) { echo "FAILED insert user: " . $ins->error . "\n"; exit(1); }
$uid = intval($mysqli->insert_id);
$ins->close();

$msg = 'Pengajuan pinjaman sebesar Rp 1.000.000 sedang diproses oleh admin.';
$title = 'Pengajuan Pinjaman Diajukan';
$type = 'pinjaman';
$stmt = $mysqli->prepare("INSERT INTO notifikasi (id_pengguna, type, title, message, data, read_status, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
$json = json_encode(['test' => 'manual']);
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

// cleanup
$mysqli->query("DELETE FROM notifikasi WHERE id = " . intval($nid));
$mysqli->query("DELETE FROM pengguna WHERE id = " . intval($uid));

exit(0);
