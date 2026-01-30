<?php
include 'gas_web/flutter_api/connection.php';

// List all tables
echo "=== All Tables ===\n";
$result = $connect->query('SHOW TABLES');
$tables = [];
while ($row = $result->fetch_row()) {
  $tables[] = $row[0];
  echo $row[0] . "\n";
}

// Look for user/pengguna related tables
echo "\n=== Looking for user tables ===\n";
foreach ($tables as $table) {
  if (stripos($table, 'user') !== false || stripos($table, 'pengguna') !== false) {
    echo "Found: " . $table . "\n";
  }
}

// Look for transaction tables  
echo "\n=== Looking for transaction tables ===\n";
foreach ($tables as $table) {
  if (stripos($table, 'transaksi') !== false || stripos($table, 'trans') !== false) {
    echo "Found: " . $table . "\n";
  }
}
?>
