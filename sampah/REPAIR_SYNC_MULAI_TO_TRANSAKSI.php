<?php
require 'gas_web/config/database.php';
$connect = getConnectionOOP();
if (!$connect) { echo "DB connect failed\n"; exit(1); }

// Find mulai_nabung rows with final statuses
$rows = $connect->query("SELECT id_mulai_nabung, id_tabungan, nomor_hp, nama_pengguna, jumlah, status, created_at FROM mulai_nabung WHERE status IN ('ditolak','berhasil') ORDER BY created_at DESC");
if (!$rows) { echo "Query error: " . $connect->error . "\n"; exit(1); }

$updated = 0;
while ($r = $rows->fetch_assoc()) {
    $mid = intval($r['id_mulai_nabung']);
    $status = strtolower($r['status']);
    $target_status = ($status === 'berhasil') ? 'approved' : 'rejected';
    $reason = ($status === 'ditolak') ? 'Ditolak oleh admin' : null;

    // Resolve pengguna id from id_tabungan or nomor_hp
    $user_id = null;
    if (!empty($r['id_tabungan'])) {
        // id_tabungan may be numeric user id in some deployments; try numeric lookup first
        if (ctype_digit((string)$r['id_tabungan'])) {
            $candidate = intval($r['id_tabungan']);
            $q = $connect->query("SELECT id FROM pengguna WHERE id = $candidate LIMIT 1");
            if ($q && $q->num_rows > 0) { $user_id = intval($q->fetch_assoc()['id']); }
        }
        // fallback: try matching pengguna.id_tabungan if column exists
        if ($user_id === null) {
            $tb = $connect->real_escape_string($r['id_tabungan']);
            $q = @$connect->query("SELECT id FROM pengguna WHERE id_tabungan = '$tb' LIMIT 1");
            if ($q && $q->num_rows > 0) { $user_id = intval($q->fetch_assoc()['id']); }
        }
    }
    if ($user_id === null && !empty($r['nomor_hp'])) {
        $hp = $connect->real_escape_string($r['nomor_hp']);
        $q = $connect->query("SELECT id FROM pengguna WHERE no_hp = '$hp' LIMIT 1");
        if ($q && $q->num_rows > 0) { $user_id = intval($q->fetch_assoc()['id']); }
    }
    if ($user_id === null) {
        echo "Skipping mulai_nabung $mid: cannot resolve user\n";
        continue;
    }

    // Update matching transaksi entries (do not require status pending)
    $pattern = "%mulai_nabung $mid%";
    if ($target_status === 'approved') {
        $stmt = $connect->prepare("UPDATE transaksi SET status = 'approved' WHERE id_pengguna = ? AND keterangan LIKE ?");
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $pattern);
            if ($stmt->execute()) { $cnt = $stmt->affected_rows; echo "Updated $cnt transaksi -> approved for mulai_nabung $mid\n"; $updated += $cnt; }
            else { echo "Warning: update failed for mulai_nabung $mid: " . $stmt->error . "\n"; }
            $stmt->close();
        }
    } else {
        // rejected: append reason
        $stmt = $connect->prepare("UPDATE transaksi SET status = 'rejected', keterangan = CONCAT(keterangan, ' | Ditolak: ', ?) WHERE id_pengguna = ? AND keterangan LIKE ?");
        if ($stmt) {
            $stmt->bind_param('sis', $reason, $user_id, $pattern);
            if ($stmt->execute()) { $cnt = $stmt->affected_rows; echo "Updated $cnt transaksi -> rejected for mulai_nabung $mid\n"; $updated += $cnt; }
            else { echo "Warning: update failed (reject) for mulai_nabung $mid: " . $stmt->error . "\n"; }
            $stmt->close();
        }
    }
}

echo "Done. Total transaksi rows updated: $updated\n";
?>
