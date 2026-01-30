<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== Test: Extract id_mulai_nabung dari keterangan ===\n\n";

// Test SQL extraction
$res = $c->query("
  SELECT 
    id_transaksi,
    keterangan,
    CAST(
      SUBSTRING_INDEX(
        SUBSTRING_INDEX(keterangan, 'mulai_nabung ', -1),
      ')', 1) AS UNSIGNED
    ) AS extracted_id
  FROM transaksi
  WHERE jenis_transaksi='setoran'
  AND id_anggota=3
  ORDER BY id_transaksi DESC
  LIMIT 15
");

while($r = $res->fetch_assoc()) {
  $extracted = $r['extracted_id'] ?? 'NULL';
  echo "id_transaksi={$r['id_transaksi']}, extracted_id_mulai_nabung={$extracted}\n";
  echo "  keter: {$r['keterangan']}\n\n";
}
?>
