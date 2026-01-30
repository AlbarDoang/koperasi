<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== Checking latest transactions ===\n";
$res = $c->query('SELECT id_transaksi, id_anggota, status, keterangan, tanggal FROM transaksi WHERE id_anggota = 3 AND jenis_transaksi = "setoran" ORDER BY tanggal DESC LIMIT 10');
while($row = $res->fetch_assoc()) {
  echo 'ID: ' . $row['id_transaksi'] . ', Status: ' . $row['status'] . ', Desc: ' . substr($row['keterangan'], 0, 50) . '..., Time: ' . $row['tanggal'] . "\n";
}

echo "\n=== Checking mulai_nabung status ===\n";
$res2 = $c->query('SELECT id_mulai_nabung, nomor_hp, jumlah, status, created_at FROM mulai_nabung WHERE nomor_hp = "081990608817" ORDER BY created_at DESC LIMIT 5');
while($row = $res2->fetch_assoc()) {
  echo 'ID: ' . $row['id_mulai_nabung'] . ', Jumlah: ' . $row['jumlah'] . ', Status: ' . $row['status'] . ', Time: ' . $row['created_at'] . "\n";
}
?>
