<?php
// Quick check of pinjaman_kredit table structure
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

$tables = ['pinjaman_kredit', 'pinjaman_kredit_log'];
$result = [];

foreach ($tables as $tbl) {
    $r = mysqli_query($con, "SHOW TABLES LIKE '$tbl'");
    $exists = $r && mysqli_num_rows($r) > 0;
    $result[$tbl] = [
        'exists' => $exists,
        'columns' => []
    ];
    
    if ($exists) {
        $cr = mysqli_query($con, "SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$tbl'");
        while ($col = mysqli_fetch_assoc($cr)) {
            $result[$tbl]['columns'][] = [
                'name' => $col['COLUMN_NAME'],
                'type' => $col['COLUMN_TYPE']
            ];
        }
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>
