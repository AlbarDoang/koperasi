<?php
// tests/integration/pinjaman_cicilan_test.php
// Simple integration test: submit a pinjaman and verify cicilan_per_bulan is stored in pinjaman_biasa
// Usage: php tests/integration/pinjaman_cicilan_test.php --base='http://192.168.43.151/gas/gas_web' --db-name='tabungan'

$options = getopt('', ['base::','db-name::']);
$base = rtrim($options['base'] ?? 'http://192.168.43.151/gas/gas_web', '/');
$dbName = $options['db-name'] ?? 'tabungan';

// Basic test values
$userId = 1;
$amount = 2000000; // Rp 2.000.000
$tenor = 2;

$payload = json_encode(['id_pengguna'=>$userId, 'jumlah_pinjaman'=>$amount, 'tenor'=>$tenor, 'tujuan_penggunaan'=>'test cicilan']);

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
    exit(1);
}

$insertId = intval($decoded['id'] ?? 0);
if ($insertId <= 0) {
    echo "FAILED: no insert id returned.\n";
    exit(1);
}

// Connect to DB and verify cicilan_per_bulan
$mysqli = new mysqli('localhost', 'root', '', $dbName);
if ($mysqli->connect_errno) {
    echo "FAILED: DB connection: " . $mysqli->connect_error . "\n";
    exit(1);
}

$q = $mysqli->prepare("SELECT cicilan_per_bulan FROM pinjaman_biasa WHERE id = ? LIMIT 1");
$q->bind_param('i', $insertId);
$q->execute();
$r = $q->get_result();
if (!$r || $r->num_rows === 0) {
    echo "FAILED: inserted row not found in pinjaman_biasa (id={$insertId}).\n";
    exit(1);
}
$row = $r->fetch_assoc();
$cicilan = intval($row['cicilan_per_bulan'] ?? 0);

if ($cicilan <= 0) {
    echo "FAILED: cicilan_per_bulan is empty or zero: " . var_export($row, true) . "\n";
    exit(1);
}

echo "OK: cicilan_per_bulan={$cicilan} stored for id={$insertId}.\n";
exit(0);

