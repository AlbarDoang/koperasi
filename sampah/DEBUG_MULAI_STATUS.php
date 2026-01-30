<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== MULAI_NABUNG STATUS CHECK ===\n";
$res = $c->query("SELECT id_mulai_nabung, nomor_hp, jenis_tabungan, jumlah, status, created_at FROM mulai_nabung WHERE nomor_hp = '081990608817' ORDER BY created_at DESC LIMIT 10");
echo "Total: " . $res->num_rows . "\n";
while($row = $res->fetch_assoc()) {
  echo "ID: {$row['id_mulai_nabung']}, Jenis: {$row['jenis_tabungan']}, Jumlah: {$row['jumlah']}, STATUS: {$row['status']}, Created: {$row['created_at']}\n";
}
?>
