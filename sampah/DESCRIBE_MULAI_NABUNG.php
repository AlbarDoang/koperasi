<?php
require 'gas_web/config/database.php';
$connect = getConnectionOOP();
$r = $connect->query('DESCRIBE mulai_nabung');
if (!$r) { echo 'Error: '.$connect->error."\n"; exit(1); }
while ($row = $r->fetch_assoc()) { echo $row['Field']."\n"; }
?>