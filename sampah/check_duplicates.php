<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

// Check latest mulai_nabung
echo "=== Latest MULAI_NABUNG entries ===\n";
$res = $c->query("SELECT id_mulai_nabung, nomor_hp, jumlah, status, created_at FROM mulai_nabung ORDER BY id_mulai_nabung DESC LIMIT 10");
while($r = $res->fetch_assoc()) {
  echo "ID: {$r['id_mulai_nabung']}, HP: {$r['nomor_hp']}, Jumlah: {$r['jumlah']}, Status: {$r['status']}, Time: {$r['created_at']}\n";
}

echo "=== TRANSAKSI per MULAI_NABUNG ===\n";
$res2 = $c->query("SELECT id_mulai_nabung, nomor_hp FROM mulai_nabung ORDER BY id_mulai_nabung DESC LIMIT 5");
while($r2 = $res2->fetch_assoc()) {
  $id = intval($r2['id_mulai_nabung']);
  $pattern = '%mulai_nabung ' . $id . '%';
  
  $stmt = $c->prepare("SELECT id_transaksi, status, tanggal FROM transaksi WHERE keterangan LIKE ? ORDER BY id_transaksi DESC");
  $stmt->bind_param('s', $pattern);
  $stmt->execute();
  $tres = $stmt->get_result();
  $count = $tres->num_rows;
  
  echo "\nmulai_nabung_id={$id}, hp={$r2['nomor_hp']}: {$count} transaksi\n";
  while($tr = $tres->fetch_assoc()) {
    echo "  - id_transaksi={$tr['id_transaksi']}, status={$tr['status']}, time={$tr['tanggal']}\n";
  }
  $stmt->close();
}
?>
