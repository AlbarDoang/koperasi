<?php
require __DIR__ . '/../gas_web/flutter_api/connection.php';
// Test: withdraw nominal and check pengguna.saldo before/after
$uid = 95; $jenis = 7; $nominal = 1; // small amount to test
// get saldo before
$beforeR = $connect->prepare('SELECT saldo FROM pengguna WHERE id = ? LIMIT 1');
$beforeR->bind_param('i', $uid); $beforeR->execute(); $bres = $beforeR->get_result(); $b = ($bres && $bres->num_rows>0) ? floatval($bres->fetch_assoc()['saldo']) : null; $beforeR->close();
echo "saldo_before=" . var_export($b, true) . "\n";

// call endpoint
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['id_pengguna'] = (string)$uid;
$_POST['id_jenis_tabungan'] = (string)$jenis;
$_POST['nominal'] = (string)$nominal;
ob_start();
include __DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php';
$out = ob_get_clean();
echo "endpoint_output=" . $out . "\n";

// get saldo after
$afterR = $connect->prepare('SELECT saldo FROM pengguna WHERE id = ? LIMIT 1');
$afterR->bind_param('i', $uid); $afterR->execute(); $ares = $afterR->get_result(); $a = ($ares && $ares->num_rows>0) ? floatval($ares->fetch_assoc()['saldo']) : null; $afterR->close();
echo "saldo_after=" . var_export($a, true) . "\n";
?>