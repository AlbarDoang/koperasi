<?php
// Fetch get_riwayat_transaksi.php for user id 3
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://192.168.1.38/gas/gas_web/flutter_api/get_riwayat_transaksi.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['id_pengguna'=>3]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
if ($res === false) { echo 'curl error: '.curl_error($ch)."\n"; exit; }
curl_close($ch);
echo $res;
?>
