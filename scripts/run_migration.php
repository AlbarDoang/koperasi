<?php
$con = new mysqli('localhost', 'root', '', 'tabungan');
if ($con->connect_error) { echo 'Connection failed: ' . $con->connect_error; exit(1); }

$sql = "ALTER TABLE transaksi MODIFY COLUMN jenis_transaksi ENUM('setoran','penarikan','transfer_masuk','transfer_keluar','pinjaman_biasa','pinjaman_kredit') NOT NULL";
if ($con->query($sql)) {
    echo "SUCCESS: Enum updated\n";
} else {
    echo "ERROR: " . $con->error . "\n";
}
$con->close();
