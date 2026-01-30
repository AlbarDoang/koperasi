<?php
// Dry-run: find tabungan_masuk rows created by admin confirmation where
// id_jenis_tabungan doesn't match the mapped jenis from mulai_nabung.
include __DIR__ . '/../gas_web/flutter_api/connection.php';

$sql = "SELECT tm.id AS tm_id, tm.id_pengguna, tm.id_jenis_tabungan, tm.jumlah, tm.keterangan, tm.created_at, m.id_mulai_nabung, m.jenis_tabungan
FROM tabungan_masuk tm
JOIN mulai_nabung m ON tm.keterangan LIKE CONCAT('%mulai:', m.id_mulai_nabung, '%')
WHERE m.status = 'berhasil' AND tm.keterangan LIKE 'Topup Admin (id_mulai:%'";
$res = $connect->query($sql);
$out = [];
while ($r = $res->fetch_assoc()) {
    $mid = intval($r['id_mulai_nabung']);
    $jn = $r['jenis_tabungan'];
    $validated = 1;
    if ($jn !== null && $jn !== '') {
        if (ctype_digit((string)$jn)) {
            $id_jenis = intval($jn);
            $chk = $connect->query("SELECT id FROM jenis_tabungan WHERE id = " . intval($id_jenis) . " LIMIT 1");
            if ($chk && $chk->num_rows > 0) $validated = $id_jenis;
        } else {
            $name = $connect->real_escape_string($jn);
            $norm = preg_replace('/\\btabungan\\b/i', '', $name);
            $norm = trim($norm);
            $n1 = $connect->real_escape_string($norm);
            $jr = $connect->query("SELECT id FROM jenis_tabungan WHERE (nama_jenis = '$name' OR nama_jenis = '$n1' OR nama_jenis LIKE '%$n1%') LIMIT 1");
            if ($jr && $jr->num_rows > 0) {
                $rrow = $jr->fetch_assoc();
                $validated = intval($rrow['id']);
            }
        }
    }
    if ($validated !== intval($r['id_jenis_tabungan'])) {
        $out[] = ['tm_id'=>$r['tm_id'],'id_pengguna'=>intval($r['id_pengguna']),'current_jenis'=>intval($r['id_jenis_tabungan']),'expected_jenis'=>$validated,'jumlah'=>intval($r['jumlah']),'id_mulai'=>$mid,'jenis_mulai'=>$r['jenis_tabungan'],'keterangan'=>$r['keterangan']];
    }
}
if (empty($out)) {
    echo "No mismatches found.\n";
} else {
    echo json_encode($out, JSON_PRETTY_PRINT) . "\n";
}
?>