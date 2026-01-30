<?php
/**
 * FIX: Ubah tipe kolom jenis_tabungan dari INT ke VARCHAR
 * Jalankan script ini untuk memperbaiki kolom
 */
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php';

$response = ['status' => 'success', 'actions' => []];

// Step 1: Check current column type
$check = $connect->query("DESCRIBE mulai_nabung");
$current_type = null;
while ($row = $check->fetch_assoc()) {
    if ($row['Field'] === 'jenis_tabungan') {
        $current_type = $row['Type'];
        $response['current_column'] = $row;
    }
}

// Step 2: If column exists and is INT, convert to VARCHAR
if ($current_type && strpos(strtoupper($current_type), 'INT') !== false) {
    $response['action_needed'] = 'CONVERT INT TO VARCHAR';
    
    // Alter column
    $alter_sql = "ALTER TABLE `mulai_nabung` 
                  MODIFY COLUMN `jenis_tabungan` VARCHAR(100) DEFAULT 'Tabungan Reguler'";
    
    if ($connect->query($alter_sql)) {
        $response['actions'][] = 'Column type changed from INT to VARCHAR(100)';
        $response['status'] = 'success';
    } else {
        $response['status'] = 'error';
        $response['error'] = $connect->error;
    }
    
    // Step 3: Update existing data (convert 0 to default)
    $update_sql = "UPDATE `mulai_nabung` 
                   SET `jenis_tabungan` = 'Tabungan Reguler' 
                   WHERE `jenis_tabungan` = '0' OR `jenis_tabungan` IS NULL";
    
    if ($connect->query($update_sql)) {
        $rows_affected = $connect->affected_rows;
        $response['actions'][] = "Updated $rows_affected rows with default value";
    } else {
        $response['warning'] = 'Could not update existing data: ' . $connect->error;
    }
    
} else if ($current_type) {
    $response['action_needed'] = 'COLUMN ALREADY VARCHAR';
    $response['actions'][] = "Column is already: $current_type";
    $response['problem'] = 'Column type is correct, issue might be elsewhere';
}

// Step 4: Verify column after change
$verify = $connect->query("DESCRIBE mulai_nabung");
while ($row = $verify->fetch_assoc()) {
    if ($row['Field'] === 'jenis_tabungan') {
        $response['column_after_fix'] = $row;
    }
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
