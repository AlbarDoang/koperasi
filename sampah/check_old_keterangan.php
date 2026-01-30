<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== Check: Apakah ada transaksi tanpa ID di keterangan? ===\n";

$res = $c->query("
  SELECT id_transaksi, jumlah, status, keterangan
  FROM transaksi
  WHERE jenis_transaksi='setoran'
  AND id_anggota=3
  ORDER BY id_transaksi DESC
  LIMIT 20
");

$no_id = 0;
while($r = $res->fetch_assoc()) {
  $has_id = strpos($r['keterangan'], 'mulai_nabung') !== false;
  $status_mark = $has_id ? '✓' : '✗ NO ID';
  echo "{$status_mark} id_transaksi={$r['id_transaksi']}, jumlah={$r['jumlah']}, status={$r['status']}\n";
  echo "  keterangan: {$r['keterangan']}\n\n";
  if (!$has_id) $no_id++;
}

echo "Total without ID: {$no_id}\n";
if ($no_id > 0) {
  echo "⚠ PROBLEM: Ada transaksi tanpa ID, ini menyebabkan duplikat di dedup logic!\n";
}
?>
