<?php
// Direct DB debug bypassing API connection wrapper to show errors
error_reporting(E_ALL);
ini_set('display_errors', '1');
require 'gas_web/config/database.php';
$connect = getConnectionOOP();
if (!$connect) { echo "DB connect failed\n"; exit(1); }

// Find user by phone or name
$candidates = ['081990608817','jtbttn','jtbtn'];
$user = null;
foreach ($candidates as $q) {
    $safe = $connect->real_escape_string($q);
        $sql = "SELECT * FROM pengguna WHERE no_hp = '$safe' OR nama_lengkap = '$safe' LIMIT 1";
    $res = $connect->query($sql);
    if ($res && $res->num_rows > 0) { $user = $res->fetch_assoc(); break; }
}

echo "USER: \n";
var_export($user);

if (!$user) { echo "No user found.\n"; exit(0); }
$uid = intval($user['id']);
$id_tabungan = $user['id_tabungan'] ?? null;

echo "\nUSER ID: $uid, id_tabungan: " . var_export($id_tabungan, true) . "\n";

// list transaksi for user id
echo "\nTRANSAKSI WHERE id_anggota = $uid:\n";
$res = $connect->query("SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi WHERE id_anggota = $uid ORDER BY tanggal DESC LIMIT 50");
if ($res) { while ($r = $res->fetch_assoc()) { print_r($r); echo "\n"; } } else { echo "Error transaksi: " . $connect->error . "\n"; }

// list transaksi where keterangan contains mulai_nabung or tabungan_masuk
foreach (['mulai_nabung','tabungan_masuk'] as $pat) {
    echo "\nTRANSAKSI LIKE %$pat%:\n";
    $p = $connect->real_escape_string('%'.$pat.'%');
    $q = "SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi WHERE keterangan LIKE '$p' ORDER BY tanggal DESC LIMIT 50";
    $r = $connect->query($q);
    if ($r) { while ($row = $r->fetch_assoc()) { print_r($row); echo "\n"; } } else { echo "Error pattern $pat: " . $connect->error . "\n"; }
}

// list mulai_nabung rows matching user's phone or id_tabungan
echo "\nMULAI_NABUNG matches:\n";
$conds = [];
if ($id_tabungan) $conds[] = "id_tabungan = '" . $connect->real_escape_string($id_tabungan) . "'";
if (!empty($user['no_hp'])) $conds[] = "nomor_hp = '" . $connect->real_escape_string($user['no_hp']) . "'";
// pengguna uses 'no_hp' column
if (!empty($user['nama_lengkap'])) $conds[] = "nama_pengguna = '" . $connect->real_escape_string($user['nama_lengkap']) . "'";
if (!empty($conds)) {
    $sql = "SELECT * FROM mulai_nabung WHERE (" . implode(' OR ', $conds) . ") ORDER BY tanggal DESC LIMIT 50";
    $r = $connect->query($sql);
    if ($r) { while ($row = $r->fetch_assoc()) { print_r($row); echo "\n"; } } else { echo "Error mulai_nabung: " . $connect->error . "\n"; }
} else { echo "No conditions for mulai_nabung\n"; }

// list tabungan_masuk
echo "\nTABUNGAN_MASUK matches:\n";
$conds2 = [];
$conds2[] = "id_pengguna = $uid";
if (!empty($user['no_hp'])) $conds2[] = "nomor_hp = '" . $connect->real_escape_string($user['no_hp']) . "'";
$sql2 = "SELECT * FROM tabungan_masuk WHERE (" . implode(' OR ', $conds2) . ") ORDER BY created_at DESC LIMIT 50";
$r2 = $connect->query($sql2);
if ($r2) { while ($row = $r2->fetch_assoc()) { print_r($row); echo "\n"; } } else { echo "Error tabungan_masuk: " . $connect->error . "\n"; }

echo "\nDone\n";
?>