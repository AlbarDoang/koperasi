<?php
$c = new mysqli('localhost','root','','tabungan');
$res = $c->query("SELECT id_transaksi, id_anggota, status, keterangan, tanggal FROM transaksi WHERE status = 'pending' ORDER BY tanggal DESC LIMIT 30");
if (!$res || $res->num_rows==0) { echo "No pending transaksi found.\n"; exit; }
while($r=$res->fetch_assoc()) {
  echo "id_transaksi={$r['id_transaksi']}, id_anggota={$r['id_anggota']}, status={$r['status']}, tanggal={$r['tanggal']}\n  keterangan={$r['keterangan']}\n\n";
}
?>