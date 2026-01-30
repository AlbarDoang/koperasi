<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$r = $connect->query('SELECT id, id_tabungan, nama FROM pengguna WHERE id=95 LIMIT 1');
if ($r && $r->num_rows>0) { print_r($r->fetch_assoc()); } else { echo "no user\n"; }
