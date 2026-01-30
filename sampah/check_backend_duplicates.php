<?php
// Check apakah buat_mulai_nabung MASIH create 2x transaksi
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== Check: Apakah masih ada 2x transaksi per mulai_nabung? ===\n\n";

// Get recent mulai_nabung
$res = $c->query("SELECT id_mulai_nabung, jumlah, status, created_at FROM mulai_nabung WHERE nomor_hp='081990608817' ORDER BY id_mulai_nabung DESC LIMIT 10");

while($mn = $res->fetch_assoc()) {
  $id = intval($mn['id_mulai_nabung']);
  $jumlah = $mn['jumlah'];
  
  // Check transaksi untuk mulai_nabung ini
  $pattern = "%mulai_nabung {$id}%";
  $stmt = $c->prepare("SELECT id_transaksi, status, keterangan FROM transaksi WHERE keterangan LIKE ? ORDER BY id_transaksi DESC");
  $stmt->bind_param('s', $pattern);
  $stmt->execute();
  $tres = $stmt->get_result();
  
  $count = $tres->num_rows;
  if ($count > 1) {
    echo "⚠ DUPLIKAT: mulai_nabung_id={$id}, jumlah={$jumlah}, transaksi_count={$count}\n";
    while($tr = $tres->fetch_assoc()) {
      echo "    id_transaksi={$tr['id_transaksi']}, status={$tr['status']}, keter={$tr['keterangan']}\n";
    }
  } else if ($count == 1) {
    echo "✓ OK: mulai_nabung_id={$id}, jumlah={$jumlah}, transaksi_count=1\n";
  } else {
    echo "✗ ERROR: mulai_nabung_id={$id}, jumlah={$jumlah}, transaksi_count=0 (NO TRANSAKSI!)\n";
  }
  echo "\n";
  $stmt->close();
}
?>
