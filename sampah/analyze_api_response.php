<?php
// Fetch API response dan analyze dedup
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://192.168.1.8/gas/gas_web/flutter_api/get_riwayat_transaksi.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['id_pengguna'=>3]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
if ($res === false) { echo 'curl error: '.curl_error($ch)."\n"; exit; }

$json = json_decode($res, true);
if (!$json['success']) { echo "API error\n"; exit; }

echo "=== Recent transactions for user_id=3 ===\n\n";
foreach($json['data'] as $item) {
  $id = $item['id'] ?? 'NULL';
  $id_trans = $item['id_transaksi'] ?? 'NULL';
  $id_mulai = $item['id_mulai_nabung'] ?? 'NULL';
  $jumlah = $item['jumlah'] ?? 0;
  $status = $item['status'] ?? 'NULL';
  $time = substr($item['created_at'] ?? '', 0, 16);
  
  // What dedup key would be?
  $dedup_key_prefer_id = "id:{$id_trans}";
  
  echo "id_transaksi={$id_trans}, id_mulai_nabung={$id_mulai}, jumlah={$jumlah}, status={$status}, time={$time}\n";
  echo "  dedup_key (preferred)={$dedup_key_prefer_id}\n";
  echo "\n";
}
?>
