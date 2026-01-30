<?php
header('Content-Type: application/json; charset=utf-8');
// Simple test response for DataTables
$draw = isset($_GET['draw']) ? intval($_GET['draw']) : 1;
echo json_encode([
    'draw' => $draw,
    'recordsTotal' => 1,
    'recordsFiltered' => 1,
    'data' => [
        ["1", "TEST", "123", "Nama Test", "01-01-2025", "Rp 0", "Actions"]
    ]
]);
