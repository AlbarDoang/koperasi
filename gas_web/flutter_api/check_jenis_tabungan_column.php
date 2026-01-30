<?php
/**
 * Check struktur kolom jenis_tabungan di tabel mulai_nabung
 */
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php';

$response = [];

// Check struktur kolom
$check = $connect->query("DESCRIBE mulai_nabung");
$columns = [];
while ($row = $check->fetch_assoc()) {
    $columns[] = $row;
    if ($row['Field'] === 'jenis_tabungan') {
        $response['jenis_tabungan_column'] = $row;
    }
}

// Check sample data
$sample = $connect->query("SELECT id_mulai_nabung, id_tabungan, jenis_tabungan, status FROM mulai_nabung ORDER BY id_mulai_nabung DESC LIMIT 3");
$response['sample_data'] = [];
while ($row = $sample->fetch_assoc()) {
    $response['sample_data'][] = $row;
}

// Check data type dari jenis_tabungan
$response['all_columns'] = $columns;
$response['status'] = 'success';

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
