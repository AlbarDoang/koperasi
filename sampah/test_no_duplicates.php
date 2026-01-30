<?php
// Test complete flow: Create 2x mulai_nabung with different amounts
// Simulate them being approved
// Verify no duplicates in transaksi

$c = new mysqli('localhost', 'root', '', 'tabungan');

echo "=== TEST: Create 2 mulai_nabung + approve each ===\n\n";

// Get user
$user_res = $c->query("SELECT id FROM pengguna WHERE no_hp='081990608817' LIMIT 1");
$user = $user_res->fetch_assoc();
$user_id = intval($user['id']);

// TEST 1: Create mulai_nabung 1
echo "1. Creating mulai_nabung #1...\n";
$ins1 = $c->prepare("INSERT INTO mulai_nabung (nomor_hp, jumlah, status, id_tabungan, nama_pengguna, tanggal, created_at) VALUES (?, ?, 'menunggu_admin', 0, '', NOW(), NOW())");
$phone = '081990608817';
$amt1 = 100000;
$ins1->bind_param('si', $phone, $amt1);
$ins1->execute();
$id1 = $c->insert_id;
echo "Created mulai_nabung id={$id1}\n";

// Insert initial transaksi for mulai_nabung 1
$t1_ins = $c->prepare("INSERT INTO transaksi (id_anggota, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, 'setoran', ?, ?, ?, ?, NOW(), 'pending')");
$keter1 = "Mulai nabung tunai (mulai_nabung {$id1})";
$saldo_before = 170000;
$t1_ins->bind_param('isdds', $user_id, $amt1, $saldo_before, $saldo_before, $keter1);
$t1_ins->execute();
echo "Created transaksi for mulai_nabung {$id1}\n";

// TEST 2: Create mulai_nabung 2
echo "\n2. Creating mulai_nabung #2...\n";
$ins2 = $c->prepare("INSERT INTO mulai_nabung (nomor_hp, jumlah, status, id_tabungan, nama_pengguna, tanggal, created_at) VALUES (?, ?, 'menunggu_admin', 0, '', NOW(), NOW())");
$amt2 = 50000;
$ins2->bind_param('si', $phone, $amt2);
$ins2->execute();
$id2 = $c->insert_id;
echo "Created mulai_nabung id={$id2}\n";

// Insert initial transaksi for mulai_nabung 2
$t2_ins = $c->prepare("INSERT INTO transaksi (id_anggota, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, 'setoran', ?, ?, ?, ?, NOW(), 'pending')");
$keter2 = "Mulai nabung tunai (mulai_nabung {$id2})";
$t2_ins->bind_param('isdds', $user_id, $amt2, $saldo_before, $saldo_before, $keter2);
$t2_ins->execute();
echo "Created transaksi for mulai_nabung {$id2}\n";

// TEST 3: Approve mulai_nabung 1
echo "\n3. Approving mulai_nabung #{$id1}...\n";
$saldo_after_1 = $saldo_before + $amt1;
$search_pattern = "%mulai_nabung {$id1}%";
$t1_upd = $c->prepare("UPDATE transaksi SET status='approved', keterangan=?, saldo_sesudah=? WHERE id_anggota=? AND status='pending' AND keterangan LIKE ? LIMIT 1");
$keter1_approved = "Topup tunai (mulai_nabung {$id1})";
$t1_upd->bind_param('ssis', $keter1_approved, $saldo_after_1, $user_id, $search_pattern);
$t1_upd->execute();
echo "Updated transaksi for mulai_nabung {$id1}\n";

// Cleanup duplicates for mulai_nabung 1
$t1_del = $c->prepare("DELETE FROM transaksi WHERE id_anggota=? AND jenis_transaksi='setoran' AND status='pending' AND keterangan LIKE ?");
$t1_del->bind_param('is', $user_id, $search_pattern);
$t1_del->execute();
$deleted = $c->affected_rows;
if ($deleted > 0) echo "Deleted {$deleted} duplicate pending transaksi\n";

// TEST 4: Approve mulai_nabung 2
echo "\n4. Approving mulai_nabung #{$id2}...\n";
$saldo_after_2 = $saldo_after_1 + $amt2;
$search_pattern2 = "%mulai_nabung {$id2}%";
$t2_upd = $c->prepare("UPDATE transaksi SET status='approved', keterangan=?, saldo_sesudah=? WHERE id_anggota=? AND status='pending' AND keterangan LIKE ? LIMIT 1");
$keter2_approved = "Topup tunai (mulai_nabung {$id2})";
$t2_upd->bind_param('ssis', $keter2_approved, $saldo_after_2, $user_id, $search_pattern2);
$t2_upd->execute();
echo "Updated transaksi for mulai_nabung {$id2}\n";

// Cleanup duplicates for mulai_nabung 2
$t2_del = $c->prepare("DELETE FROM transaksi WHERE id_anggota=? AND jenis_transaksi='setoran' AND status='pending' AND keterangan LIKE ?");
$t2_del->bind_param('is', $user_id, $search_pattern2);
$t2_del->execute();
$deleted = $c->affected_rows;
if ($deleted > 0) echo "Deleted {$deleted} duplicate pending transaksi\n";

// TEST 5: Verify result
echo "\n5. VERIFICATION\n";
echo "================\n";

$verify = $c->query("
  SELECT id_transaksi, status, keterangan 
  FROM transaksi 
  WHERE id_anggota={$user_id} AND jenis_transaksi='setoran' AND (keterangan LIKE '%{$id1}%' OR keterangan LIKE '%{$id2}%')
  ORDER BY id_transaksi DESC
");

echo "Transaksi untuk mulai_nabung #{$id1} dan #{$id2}:\n";
$count = 0;
while($r = $verify->fetch_assoc()) {
  echo "  id_transaksi={$r['id_transaksi']}, status={$r['status']}, keter={$r['keterangan']}\n";
  $count++;
}
echo "Total: {$count} (Expected: 2 - one per mulai_nabung)\n";

if ($count == 2) {
  echo "\n✓ SUCCESS: No duplicates!\n";
} else {
  echo "\n✗ FAILED: Expected 2, got {$count}\n";
}
?>
