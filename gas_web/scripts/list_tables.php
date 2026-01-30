<?php
require_once __DIR__ . "/../config/database.php";
$k = getConnectionOOP();
$pat = '%';
$res = $k->query("SHOW TABLES");
$tables = [];
while($r = $res->fetch_row()) $tables[] = $r[0];
// print any relevant tables
$interesting = array_filter($tables, function($t){ return preg_match('/t_keluar|tabungan|transaksi|pengguna|t_keluar/i', $t); });
echo "All tables (first 100):\n";
foreach(array_slice($tables,0,200) as $t) echo "- $t\n";

echo "\nInteresting tables:\n";
foreach($interesting as $t) echo "- $t\n";
