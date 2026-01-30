<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
require 'gas_web/config/database.php';
$connect = getConnectionOOP();
if (!$connect) { echo "DB connect failed\n"; exit(1); }
$res = $connect->query("DESCRIBE pengguna");
if (!$res) { echo "Error: " . $connect->error . "\n"; exit(1); }
while ($r = $res->fetch_assoc()) { print_r($r); echo "\n"; }
?>