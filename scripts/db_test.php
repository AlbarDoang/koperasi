<?php
require_once __DIR__ . '/../gas_web/config/database.php';
if (isset($connect) && $connect) {
    echo "connect var exists\n";
    $r = $connect->query('SELECT NOW() as t');
    if ($r) { $row = $r->fetch_assoc(); echo 'now='. $row['t'] . "\n"; } else { echo 'query failed: '.$connect->error; }
} else if (isset($con) && $con) {
    echo "con var exists\n"; $r = $con->query('SELECT NOW() as t'); if ($r) { $row = $r->fetch_assoc(); echo 'now='. $row['t'] . "\n"; } else { echo 'query failed: '.$con->error; }
} else { echo "no DB var available\n"; }
?>