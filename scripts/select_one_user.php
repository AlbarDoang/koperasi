<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$res = $con->query('SELECT id, nama_lengkap FROM pengguna LIMIT 1');
if ($r = $res->fetch_assoc()) { echo $r['id'] . ': ' . ($r['nama_lengkap'] ?? '-') . "\n"; } else { echo "No user found\n"; }
?>