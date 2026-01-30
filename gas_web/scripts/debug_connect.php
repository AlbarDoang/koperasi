<?php
include __DIR__ . '/../flutter_api/connection.php';
// Check if $connect/$con/$koneksi set
$info = [
    'has_connect' => isset($connect),
    'connect_type' => isset($connect) ? gettype($connect) : null,
    'has_koneksi' => isset($koneksi),
    'koneksi_type' => isset($koneksi) ? gettype($koneksi) : null,
    'has_con' => isset($con),
    'con_type' => isset($con) ? gettype($con) : null,
];
header('Content-Type: application/json');
echo json_encode($info, JSON_PRETTY_PRINT);
?>