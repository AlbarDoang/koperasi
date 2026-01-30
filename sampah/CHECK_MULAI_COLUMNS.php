<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');
$r = $c->query('DESCRIBE mulai_nabung');
echo "=== mulai_nabung columns ===\n";
while($row = $r->fetch_assoc()) {
  echo $row['Field'] . " (" . $row['Type'] . ")" . ($row['Key']=='PRI' ? ' [PRIMARY]' : '') . "\n";
}
?>
