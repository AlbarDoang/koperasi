<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');
$r = $c->query('DESCRIBE transaksi');
echo "=== transaksi columns ===\n";
while($row = $r->fetch_assoc()) {
  if($row['Field'] == 'status') {
    echo $row['Field'] . " TYPE: " . $row['Type'] . " (NULL: " . $row['Null'] . ")\n";
  }
}
?>
