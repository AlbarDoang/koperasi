<?php
// Database analysis script
header('Content-Type: application/json');

$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'tabungan';

$connect = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($connect->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $connect->connect_error]));
}

$analysis = [];

// Get all tables
$tables_result = $connect->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_row()) {
    $tables[] = $row[0];
}

$analysis['tables'] = $tables;

// Analyze each important table
$important_tables = ['pengguna', 'tabungan_masuk', 'tabungan_keluar', 'jenis_tabungan', 'mulai_nabung', 'transaksi'];

foreach ($important_tables as $table) {
    if (in_array($table, $tables)) {
        // Get columns
        $cols_result = $connect->query("DESCRIBE $table");
        $columns = [];
        while ($col = $cols_result->fetch_assoc()) {
            $columns[] = [
                'name' => $col['Field'],
                'type' => $col['Type'],
                'null' => $col['Null'],
                'key' => $col['Key'],
                'default' => $col['Default'],
                'extra' => $col['Extra']
            ];
        }
        
        // Get row count
        $count_result = $connect->query("SELECT COUNT(*) as cnt FROM $table");
        $count_row = $count_result->fetch_assoc();
        
        $analysis[$table] = [
            'exists' => true,
            'row_count' => intval($count_row['cnt']),
            'columns' => $columns
        ];
    } else {
        $analysis[$table] = ['exists' => false];
    }
}

// Show sample data
$analysis['sample_data'] = [];

if (in_array('pengguna', $tables)) {
    $sample = $connect->query("SELECT * FROM pengguna LIMIT 1");
    $analysis['sample_data']['pengguna'] = $sample ? $sample->fetch_assoc() : null;
}

if (in_array('tabungan_masuk', $tables)) {
    $sample = $connect->query("SELECT * FROM tabungan_masuk LIMIT 3");
    $rows = [];
    while ($row = $sample->fetch_assoc()) $rows[] = $row;
    $analysis['sample_data']['tabungan_masuk'] = $rows;
}

if (in_array('tabungan_keluar', $tables)) {
    $sample = $connect->query("SELECT * FROM tabungan_keluar LIMIT 3");
    $rows = [];
    while ($row = $sample->fetch_assoc()) $rows[] = $row;
    $analysis['sample_data']['tabungan_keluar'] = $rows;
}

echo json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$connect->close();
?>
