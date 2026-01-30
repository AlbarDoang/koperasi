<?php
// tests/integration/pinjaman_biasa_detail_test.php
// Create a pengguna + pinjaman_biasa and request the admin detail modal (ajax=1) to verify member profile + saldo + reject reason display
// Usage: php tests/integration/pinjaman_biasa_detail_test.php --base='http://192.168.1.8/gas/gas_web' --db-name='tabungan'

$options = getopt('', ['base::','db-name::']);
$base = rtrim($options['base'] ?? 'http://192.168.1.8/gas/gas_web', '/');
$dbName = $options['db-name'] ?? 'tabungan';

$mysqli = new mysqli('localhost', 'root', '', $dbName);
if ($mysqli->connect_errno) {
    echo "FAILED: DB connection: " . $mysqli->connect_error . "\n";
    exit(1);
}

// Create a test pengguna
$nama = 'Test User Detail ' . rand(1000,9999);
$alamat = 'Jl. Integrasi 123';
$nohp = '081234' . rand(10000,99999);
$tgl = '1990-01-01';
$saldo = 123456;
$status = 'approved';

$ins = $mysqli->prepare("INSERT INTO pengguna (nama_lengkap, no_hp, alamat_domisili, tanggal_lahir, saldo, status_akun) VALUES (?, ?, ?, ?, ?, ?)");
$ins->bind_param('sssiss', $nama, $nohp, $alamat, $tgl, $saldo, $status);
if (!$ins->execute()) { echo "FAILED: insert pengguna: " . $mysqli->error . "\n"; exit(1); }
$uid = $mysqli->insert_id;
$ins->close();

// Insert pinjaman_biasa row
$amount = 150000;
$tenor = 3;
$tujuan = 'Tujuan test';
$ins2 = $mysqli->prepare("INSERT INTO pinjaman_biasa (id_pengguna, jumlah_pinjaman, tenor, tujuan_penggunaan, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
$ins2->bind_param('iiis', $uid, $amount, $tenor, $tujuan);
if (!$ins2->execute()) { echo "FAILED: insert pinjaman_biasa: " . $mysqli->error . "\n"; exit(1); }
$pid = $mysqli->insert_id;
$ins2->close();

// Call detail page
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $base . '/login/admin/pinjaman_biasa/detail.php?id=' . $pid . '&ajax=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) { echo "FAILED: detail request returned HTTP " . $code . "\n"; exit(1); }

$need = [$alamat, $tgl, 'Saldo Anggota', 'Tidak diisi'];
$found = true;
foreach ($need as $n) {
    if (strpos($res, (string)$n) === false) { echo "FAILED: expected to find '" . $n . "' in response\n"; $found = false; }
}

// Clean up
$mysqli->query("DELETE FROM pinjaman_biasa WHERE id = " . intval($pid));
$mysqli->query("DELETE FROM pengguna WHERE id = " . intval($uid));

if (!$found) exit(1);

echo "OK: detail page contains expected member fields and formatting\n";
exit(0);
