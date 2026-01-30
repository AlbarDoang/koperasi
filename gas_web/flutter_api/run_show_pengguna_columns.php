<?php
require_once 'connection.php';
ini_set('display_errors',1);
error_reporting(E_ALL);
$res = $connect->query("SHOW COLUMNS FROM pengguna");
$cols = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $cols[] = $r;
    }
}
header('Content-Type: application/json');
echo json_encode(['success'=>true,'columns'=>$cols], JSON_PRETTY_PRINT);
