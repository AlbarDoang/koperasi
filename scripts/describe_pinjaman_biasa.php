<?php
require_once __DIR__ . '/../gas_web/config/database.php';
$res = $con->query("SHOW COLUMNS FROM pinjaman_biasa");
while ($r = $res->fetch_assoc()) { echo $r['Field'] . "\n"; }
?>