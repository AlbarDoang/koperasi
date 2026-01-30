<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');
$r = $c->query('SELECT id, nama_jenis FROM jenis_tabungan LIMIT 10');
echo "=== jenis_tabungan entries ===\n";
while($row = $r->fetch_assoc()) {
  echo 'ID: ' . $row['id'] . ', Nama: ' . $row['nama_jenis'] . "\n";
}
?>
