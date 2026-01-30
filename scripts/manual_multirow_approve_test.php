<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
function post($url, $data) {
    $opts = ['http' => ['method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($data), 'timeout' => 10]];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    return $res === false ? null : json_decode($res, true);
}

// Use existing user id 97 for manual test
$uid = 97;
echo "Using existing user id=$uid\n";

// Ensure there is a jenis
$jenisName = 'ManualJenisMR';
$jr = $connect->prepare("SELECT id FROM jenis_tabungan WHERE nama_jenis = ? LIMIT 1");
if ($jr) { $jr->bind_param('s', $jenisName); $jr->execute(); $rj = $jr->get_result(); if ($rj && $rj->num_rows>0) { $jenisId = intval($rj->fetch_assoc()['id']); } $jr->close(); }
if (empty($jenisId)) {
    $connect->query("INSERT INTO jenis_tabungan (nama_jenis) VALUES ('" . $connect->real_escape_string($jenisName) . "')");
    $jenisId = intval($connect->insert_id);
}
echo "Using jenis id=$jenisId\n";

// Insert two incoming rows
$connect->query("INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan) VALUES ($uid, $jenisId, 3000, 'manual test A')");
$connect->query("INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan) VALUES ($uid, $jenisId, 2000, 'manual test B')");

echo "Inserted two tabungan_masuk rows (3000 + 2000)\n";

$base = 'http://localhost/gas/gas_web/flutter_api';
$res = post($base . '/cairkan_tabungan.php', ['id_pengguna' => $uid, 'id_jenis_tabungan' => $jenisId, 'nominal' => 4500]);
print_r($res);

// find pending
$stmt = $connect->prepare("SELECT id FROM tabungan_keluar WHERE id_pengguna = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
$stmt->bind_param('i', $uid); $stmt->execute(); $r = $stmt->get_result(); $row = $r->fetch_assoc(); $pid = $row['id'] ?? null; $stmt->close();
if (!$pid) { echo "No pending found\n"; exit(1); }

echo "Pending id=$pid\n";
$res2 = post($base . '/approve_penarikan.php', ['no_keluar' => $pid, 'action' => 'approve', 'approved_by' => 1]);
print_r($res2);

// Check final per-jenis and wallet
$_REQUEST['id_tabungan'] = $uid; $_GET['id_tabungan'] = $uid; include __DIR__ . '/../gas_web/flutter_api/get_rincian_tabungan.php';
echo "\n";

$_REQUEST['id_tabungan'] = $uid; $_GET['id_tabungan'] = $uid; include __DIR__ . '/../gas_web/flutter_api/get_saldo_tabungan.php';

?>