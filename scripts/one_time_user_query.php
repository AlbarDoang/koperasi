<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
// Build a safe select list based on existing columns
$cols = ['id','saldo','nama','no_hp','username','id_tabungan'];
$available = [];
foreach ($cols as $c) {
    $r = $connect->query("SHOW COLUMNS FROM pengguna LIKE '" . $connect->real_escape_string($c) . "'");
    if ($r && $r->num_rows > 0) $available[] = $c;
}
if (empty($available)) { echo json_encode(['success'=>false,'message'=>'No usable columns found']); exit(); }
$sql = 'SELECT ' . implode(', ', $available) . ' FROM pengguna LIMIT 20';
$q = $connect->query($sql);
$out = [];
if ($q) {
    while ($r = $q->fetch_assoc()) $out[] = $r;
}
header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT);
