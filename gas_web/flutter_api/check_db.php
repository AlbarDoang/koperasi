<?php
header('Content-Type: application/json; charset=utf-8');
include 'connection.php';

// Show which database we're connected to
$result = $connect->query("SELECT DATABASE() as current_db");
$db_info = $result->fetch_assoc();

// List all tables in current database
$tables_result = $connect->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_assoc()) {
    $tables[] = array_values($row)[0];
}

echo json_encode([
    'success' => true,
    'current_database' => $db_info['current_db'],
    'tables' => $tables,
    'total_tables' => count($tables)
], JSON_PRETTY_PRINT);
