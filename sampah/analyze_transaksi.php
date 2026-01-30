<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== Sample transaksi keterangan ===\n";
$res = $c->query("SELECT id_transaksi, keterangan, status FROM transaksi WHERE jenis_transaksi='setoran' ORDER BY id_transaksi DESC LIMIT 20");
while($r = $res->fetch_assoc()) {
  echo "id={$r['id_transaksi']}, status={$r['status']}, keterangan={$r['keterangan']}\n";
}

echo "\n=== Count ALL transaksi with 'mulai_nabung' in keterangan ===\n";
$c2 = $c->query("SELECT COUNT(*) as cnt FROM transaksi WHERE keterangan LIKE '%mulai_nabung%'");
$r2 = $c2->fetch_assoc();
echo "Total: " . $r2['cnt'] . "\n";

echo "\n=== Check for duplicates: keterangan like Topup AND status pending ===\n";
$c3 = $c->query("SELECT keterangan, COUNT(*) as cnt FROM transaksi WHERE keterangan LIKE '%Topup tunai%' GROUP BY keterangan HAVING cnt > 1");
if ($c3->num_rows > 0) {
  while($r3 = $c3->fetch_assoc()) {
    echo "Keterangan: {$r3['keterangan']}, Count: {$r3['cnt']}\n";
  }
} else {
  echo "No duplicate keterangan found\n";
}

echo "\n=== Check pending by keterangan pattern ===\n";
$c4 = $c->query("SELECT id_transaksi, keterangan, status FROM transaksi WHERE keterangan LIKE '%menunggu%' AND status='pending' ORDER BY id_transaksi DESC LIMIT 15");
echo "Pending count: " . $c4->num_rows . "\n";
while($r4 = $c4->fetch_assoc()) {
  echo "  id={$r4['id_transaksi']}, status={$r4['status']}, keter={$r4['keterangan']}\n";
}
?>
