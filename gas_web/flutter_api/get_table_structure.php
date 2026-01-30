<?php
header('Content-Type: application/json');

require_once __DIR__ . '/connection.php';

// Get table structure
$result = $connect->query('SHOW CREATE TABLE tabungan_masuk');
if (!$result) {
    die(json_encode(['error' => $connect->error]));
}

$row = $result->fetch_assoc();
echo json_encode([
    'table_creation_sql' => $row['Create Table']
], JSON_PRETTY_PRINT);
?>
