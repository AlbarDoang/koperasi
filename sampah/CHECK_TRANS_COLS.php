<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== Transaksi table columns ===\n";
$res = $c->query('DESCRIBE transaksi');
while($row = $res->fetch_assoc()) {
  echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
