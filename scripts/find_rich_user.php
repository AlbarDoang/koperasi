<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$q = $connect->query("SELECT id,id_tabungan,saldo,id_jenis_tabungan,jenis_tabungan FROM pengguna WHERE saldo > 0 ORDER BY saldo DESC LIMIT 20");
$out = [];
if ($q) {
    while ($r = $q->fetch_assoc()) $out[] = $r;
}
header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
?>