<?php
require_once __DIR__ . '/../gas_web/config/db.php';
$res = mysqli_query($con, "SELECT id, foto_barang, id_pengguna, nama_barang, created_at FROM pinjaman_kredit ORDER BY id DESC LIMIT 5");
$out = [];
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) $out[] = $r;
}
header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
