<?php
$con = new mysqli('localhost', 'root', '', 'tabungan');
$con->query("SET time_zone = '+07:00'");
$res = $con->query("SELECT id_transaksi, jenis_transaksi, jumlah, status FROM transaksi WHERE jenis_transaksi IN ('pinjaman_biasa', 'pinjaman_kredit') ORDER BY id_transaksi ASC");
echo "Pinjaman records in transaksi table:\n";
while ($row = $res->fetch_assoc()) {
    echo "  id_transaksi={$row['id_transaksi']} jenis={$row['jenis_transaksi']} jumlah={$row['jumlah']} status={$row['status']}\n";
}
$con->close();
