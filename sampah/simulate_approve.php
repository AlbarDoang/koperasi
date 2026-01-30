<?php
// Simulate admin approval for the latest pending mulai_nabung
$c = new mysqli('localhost', 'root', '', 'tabungan');
if ($c->connect_errno) { die("DB connect error: " . $c->connect_error . "\n"); }

echo "Finding a pending mulai_nabung...\n";
$pending_q = $c->query("SELECT * FROM mulai_nabung WHERE status NOT IN ('berhasil','ditolak') ORDER BY created_at DESC LIMIT 1");
if (!$pending_q || $pending_q->num_rows == 0) { echo "No pending mulai_nabung found.\n"; exit; }
$row = $pending_q->fetch_assoc();
$id = intval($row['id_mulai_nabung']);
$nomor_hp = $row['nomor_hp'] ?? null;
$jumlah = floatval($row['jumlah']);
echo "Found mulai_nabung id={$id}, nomor_hp={$nomor_hp}, jumlah={$jumlah}, status={$row['status']}\n";

// Resolve pengguna
$user = null;
if ($nomor_hp) {
  $u = $c->query("SELECT id, no_hp, saldo FROM pengguna WHERE no_hp = '" . $c->real_escape_string($nomor_hp) . "' LIMIT 1");
  if ($u && $u->num_rows>0) $user = $u->fetch_assoc();
}
if (!$user) {
  echo "Could not find pengguna for nomor_hp={$nomor_hp}.\n";
  exit;
}
$user_id = intval($user['id']);
$current_saldo = floatval($user['saldo']);
$new_saldo = $current_saldo + $jumlah;

echo "Before: pengguna id={$user_id}, saldo={$current_saldo}\n";

// Show matching transaksi rows
$pattern = '%mulai_nabung ' . $id . '%';
$stmt = $c->prepare("SELECT id_transaksi, status, keterangan, saldo_sebelum, saldo_sesudah FROM transaksi WHERE id_anggota = ? AND jenis_transaksi = 'setoran' AND keterangan LIKE ? ORDER BY tanggal DESC");
$stmt->bind_param('is', $user_id, $pattern);
$stmt->execute();
$res = $stmt->get_result();
echo "Transaksi related to this mulai_nabung:\n";
while($r = $res->fetch_assoc()) {
  echo "  id_transaksi={$r['id_transaksi']}, status={$r['status']}, saldo_sesudah={$r['saldo_sesudah']}, keterangan={$r['keterangan']}\n";
}
$stmt->close();

// Perform simulated approval in transaction
$c->begin_transaction();
try {
  // Update pengguna saldo
  $upd = $c->prepare("UPDATE pengguna SET saldo = ? WHERE id = ?");
  $upd->bind_param('di', $new_saldo, $user_id);
  $upd->execute();
  $upd->close();

  // Update latest pending transaksi for this mulai_nabung
  $sel = $c->prepare("SELECT id_transaksi FROM transaksi WHERE id_anggota = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? ORDER BY tanggal DESC LIMIT 1");
  $sel->bind_param('is', $user_id, $pattern);
  $sel->execute();
  $sres = $sel->get_result();
  $sel->close();
  $updated_id = null;
  if ($sres && $sres->num_rows>0) {
    $er = $sres->fetch_assoc();
    $updated_id = intval($er['id_transaksi']);
    $up2 = $c->prepare("UPDATE transaksi SET status = 'approved', saldo_sesudah = ? WHERE id_transaksi = ?");
    $up2->bind_param('di', $new_saldo, $updated_id);
    $up2->execute();
    $up2->close();
    // cleanup other pending duplicates
    $del = $c->prepare("DELETE FROM transaksi WHERE id_anggota = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? AND id_transaksi != ?");
    $del->bind_param('isi', $user_id, $pattern, $updated_id);
    $del->execute();
    $del->close();
  } else {
    // Insert new transaksi as fallback
    $ins = $c->prepare("INSERT INTO transaksi (id_anggota, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, 'setoran', ?, ?, ?, ?, NOW(), 'approved')");
    $ins->bind_param('iddds', $user_id, $jumlah, $current_saldo, $new_saldo, $pattern);
    $ins->execute();
    $updated_id = $c->insert_id;
    $ins->close();
    // cleanup other pending duplicates
    $del = $c->prepare("DELETE FROM transaksi WHERE id_anggota = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? AND id_transaksi != ?");
    $del->bind_param('isi', $user_id, $pattern, $updated_id);
    $del->execute();
    $del->close();
  }

  // Update mulai_nabung status
  $upd2 = $c->prepare("UPDATE mulai_nabung SET status = 'berhasil' WHERE id_mulai_nabung = ?");
  $upd2->bind_param('i', $id);
  $upd2->execute();
  $upd2->close();

  $c->commit();
  echo "Approval applied. transaksi id updated/created={$updated_id}, pengguna saldo now={$new_saldo}\n";
} catch(Exception $e) {
  $c->rollback();
  echo "Error during approve: " . $e->getMessage() . "\n";
  exit;
}

// Print after state
$u2 = $c->query("SELECT id, no_hp, saldo FROM pengguna WHERE id = {$user_id} LIMIT 1");
$uu = $u2->fetch_assoc();
echo "After: pengguna id={$uu['id']}, saldo={$uu['saldo']}\n";

$stmt = $c->prepare("SELECT id_transaksi, status, keterangan, saldo_sebelum, saldo_sesudah FROM transaksi WHERE id_anggota = ? AND jenis_transaksi = 'setoran' AND keterangan LIKE ? ORDER BY tanggal DESC");
$stmt->bind_param('is', $user_id, $pattern);
$stmt->execute();
$res2 = $stmt->get_result();
echo "Transaksi related to this mulai_nabung (after):\n";
while($r = $res2->fetch_assoc()) {
  echo "  id_transaksi={$r['id_transaksi']}, status={$r['status']}, saldo_sesudah={$r['saldo_sesudah']}, keterangan={$r['keterangan']}\n";
}
$stmt->close();

$mn = $c->query("SELECT id_mulai_nabung, jumlah, status FROM mulai_nabung WHERE id_mulai_nabung = {$id} LIMIT 1");
if ($mn) {
  $mnr = $mn->fetch_assoc();
  echo "mulai_nabung id={$mnr['id_mulai_nabung']}, jumlah={$mnr['jumlah']}, status={$mnr['status']}\n";
}

echo "Done.\n";
?>