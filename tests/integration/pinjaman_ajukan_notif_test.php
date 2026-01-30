<?php
// tests/integration/pinjaman_ajukan_notif_test.php
// Test: submitting via /api/pinjaman/ajukan.php creates a notification for the user
// Usage: php tests/integration/pinjaman_ajukan_notif_test.php --base='http://192.168.1.8/gas/gas_web' --db-name='tabungan'

$options = getopt('', ['base::','db-name::']);
$base = rtrim($options['base'] ?? 'http://192.168.1.8/gas/gas_web', '/');
$dbName = $options['db-name'] ?? 'tabungan';

$mysqli = new mysqli('localhost', 'root', '', $dbName);
if ($mysqli->connect_errno) { echo "FAILED: DB connect {$mysqli->connect_error}\n"; exit(1); }

// Create a temporary user using common columns (nama_lengkap, no_hp) so the schema matches everywhere
$nama = 'test_user_' . rand(1000,9999);
$nohp = '081234' . rand(10000,99999);
$alamat = 'Jl Test';
$tgl = '1990-01-01';
$status = 'approved';
$ins = $mysqli->prepare("INSERT INTO pengguna (nama_lengkap, no_hp, alamat_domisili, tanggal_lahir, status_akun, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$ins->bind_param('sssss', $nama, $nohp, $alamat, $tgl, $status);
if (!$ins->execute()) { echo "FAILED: insert pengguna: " . $ins->error . "\n"; exit(1); }
$userId = intval($mysqli->insert_id);
$ins->close();

// Submit pinjaman via API (use submit.php which accepts id_pengguna parameter)
$payload = json_encode(['id_pengguna' => $userId, 'jumlah_pinjaman'=>1000000, 'tenor'=>12, 'tujuan_penggunaan'=>'test notif']);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base . '/api/pinjaman/submit.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "API response code: $code\n";
echo "Response body: $res\n";
$decoded = json_decode($res, true);
if (!$decoded || !isset($decoded['status']) || $decoded['status'] !== true) {
    echo "FAILED: API did not return success.\n";
    cleanup($mysqli, $userId);
    exit(1);
}

// Verify notification exists
$stmt = $mysqli->prepare("SELECT id, title, message FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res2 = $stmt->get_result();
if (!$res2 || $res2->num_rows === 0) {
    echo "FAILED: No notification found for user {$userId}.\n";
    cleanup($mysqli, $userId);
    exit(1);
}
$row = $res2->fetch_assoc();
$stmt->close();

echo "OK: Found notification id={$row['id']} title={$row['title']} message={$row['message']}\n";

cleanup($mysqli, $userId);
exit(0);

function cleanup($mysqli, $userId) {
    $mysqli->query("DELETE FROM notifikasi WHERE id_pengguna = " . intval($userId));
    $mysqli->query("DELETE FROM pinjaman WHERE id_pengguna = " . intval($userId));
    $mysqli->query("DELETE FROM pengguna WHERE id = " . intval($userId));
}
