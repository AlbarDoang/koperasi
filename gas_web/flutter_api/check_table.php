<?php
header('Content-Type: application/json; charset=utf-8');

include 'connection.php';

// Cek struktur tabel pengguna
$result = $connect->query("DESCRIBE pengguna");

if (!$result) {
    echo json_encode(['error' => $connect->error]);
    exit();
}

$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row;
}

echo json_encode([
    'success' => true,
    'table' => 'pengguna',
    'columns' => $columns,
    'total_columns' => count($columns)
]);
