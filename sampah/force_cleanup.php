<?php
$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "Deleting all old pending (orphaned) transaksi...\n";

// Delete all pending dengan generic keterangan (tidak ada mulai_nabung ID)
$del = $c->query("DELETE FROM transaksi WHERE status='pending' AND keterangan='Mulai nabung tunai (menunggu persetujuan)'");

echo "Deleted: " . $c->affected_rows . " rows\n";

echo "\nCurrent pending:\n";
$res = $c->query("SELECT COUNT(*) as cnt FROM transaksi WHERE status='pending'");
$r = $res->fetch_assoc();
echo "Total pending: " . $r['cnt'] . "\n";
?>
