<?php
// Clean up old pending transactions yang tidak berguna dan no id_mulai_nabung traceable
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== Cleaning up orphaned pending transaksi ===\n";

// Find pending transaksi tanpa ID yang teridentifikasi (generic keterangan)
$old_pending = $c->query("
  SELECT id_transaksi FROM transaksi 
  WHERE status='pending' 
  AND keterangan='Mulai nabung tunai (menunggu persetujuan)'
  AND id_transaksi NOT IN (
    SELECT CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan, 'mulai_nabung ', -1), ')', 1) AS UNSIGNED)
    FROM mulai_nabung
    WHERE id_mulai_nabung IS NOT NULL
  )
  ORDER BY id_transaksi DESC
");

if ($old_pending && $old_pending->num_rows > 0) {
  $count = $old_pending->num_rows;
  echo "Found {$count} orphaned pending transaksi\n";
  
  // Delete them
  $del = $c->query("
    DELETE FROM transaksi 
    WHERE status='pending' 
    AND keterangan='Mulai nabung tunai (menunggu persetujuan)'
    AND id_transaksi NOT IN (
      SELECT CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(keterangan, 'mulai_nabung ', -1), ')', 1) AS UNSIGNED)
      FROM mulai_nabung
      WHERE id_mulai_nabung IS NOT NULL
    )
  ");
  echo "Deleted successfully\n";
} else {
  echo "No orphaned pending transaksi found\n";
}

// Re-check
echo "\n=== Current pending transaksi ===\n";
$res = $c->query("SELECT id_transaksi, keterangan, status FROM transaksi WHERE status='pending' ORDER BY id_transaksi DESC LIMIT 20");
echo "Total pending: " . $res->num_rows . "\n";
while($r = $res->fetch_assoc()) {
  echo "id={$r['id_transaksi']}, keter={$r['keterangan']}\n";
}
?>
