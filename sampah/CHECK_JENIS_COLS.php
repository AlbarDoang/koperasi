<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');
$r = $c->query('DESCRIBE jenis_tabungan');
echo "=== jenis_tabungan columns ===\n";
while($row = $r->fetch_assoc()) {
  echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
?>
