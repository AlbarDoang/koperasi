<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';

// Output as valid JSON so connection.php will pass it through
$resp = ['describe' => [], 'notifs' => [], 'transaksi_preview' => []];
$res = $connect->query("DESCRIBE notifikasi");
if ($res) {
    while ($r = $res->fetch_assoc()) $resp['describe'][] = $r;
} else {
    $resp['describe_error'] = $connect->error;
}

$stmt = $connect->prepare("SELECT id, id_pengguna, type, title, message, data, read_status, created_at FROM notifikasi ORDER BY created_at DESC LIMIT 10");
if ($stmt) { $stmt->execute(); $r = $stmt->get_result(); while ($row = $r->fetch_assoc()) $resp['notifs'][] = $row; } else { $resp['notifs_error'] = $connect->error; }

echo json_encode($resp, JSON_PRETTY_PRINT);

echo "\nRecent transaksi for id_tabungan={$uid}\n";
// Build select dynamically based on available columns
$availableCols = [];
$resc = $connect->query("SHOW COLUMNS FROM transaksi");
if ($resc) {
    while ($rc = $resc->fetch_assoc()) { $availableCols[] = $rc['Field']; }
}
$want = ['id','jenis_transaksi','jumlah','keterangan','tanggal','created_at','jumlah_masuk','jumlah_keluar','saldo_sebelum','saldo_sesudah'];
$sel = [];
foreach ($want as $w) { if (in_array($w, $availableCols)) $sel[] = $w; }
if (empty($sel)) { echo "No standard columns in transaksi; skipping\n"; exit(); }
$sql = "SELECT * FROM transaksi ORDER BY " . (in_array('created_at',$sel)?'created_at':'tanggal') . " DESC LIMIT 200";
$stmt2 = $connect->prepare($sql);
$stmt2->execute(); $r2 = $stmt2->get_result();
while ($row = $r2->fetch_assoc()) {
    // try to heuristically match this user
    $matches = false;
    foreach (['id_tabungan','id_anggota','id'] as $c) {
        if (isset($row[$c]) && intval($row[$c]) === $uid) { $matches = true; break; }
    }
    if (!$matches) {
        // check textual fields
        foreach (['keterangan','kegiatan','keterangan','nama'] as $tc) {
            if (isset($row[$tc]) && stripos((string)$row[$tc], (string)$uid) !== false) { $matches = true; break; }
        }
    }
    if ($matches) print_r($row);
}

?>