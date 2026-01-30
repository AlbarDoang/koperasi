<?php
/**
 * AUTO FIX: Ubah kolom jenis_tabungan dari INT ke VARCHAR
 * Jalankan di browser: http://localhost/gas/gas_web/flutter_api/auto_fix_jenis_tabungan.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once 'connection.php';

$response = [
    'status' => 'processing',
    'steps' => [],
    'errors' => []
];

try {
    // STEP 1: Check current column structure
    $response['steps'][] = "STEP 1: Checking current column structure...";
    
    $describe = $connect->query("DESCRIBE `mulai_nabung`");
    if (!$describe) {
        throw new Exception("Gagal check table structure: " . $connect->error);
    }
    
    $current_type = null;
    $columns_info = [];
    while ($row = $describe->fetch_assoc()) {
        $columns_info[] = $row;
        if ($row['Field'] === 'jenis_tabungan') {
            $current_type = $row['Type'];
        }
    }
    
    $response['steps'][] = "Current jenis_tabungan type: " . ($current_type ?: 'COLUMN NOT FOUND');
    
    // STEP 2: Modify column to VARCHAR if needed
    if (!$current_type) {
        $response['steps'][] = "STEP 2: Adding jenis_tabungan column...";
        $sql = "ALTER TABLE `mulai_nabung` 
                ADD COLUMN `jenis_tabungan` VARCHAR(100) DEFAULT 'Tabungan Reguler' AFTER `jumlah`";
    } else if (stripos($current_type, 'INT') !== false) {
        $response['steps'][] = "STEP 2: Modifying column from INT to VARCHAR...";
        $sql = "ALTER TABLE `mulai_nabung` 
                MODIFY COLUMN `jenis_tabungan` VARCHAR(100) DEFAULT 'Tabungan Reguler'";
    } else if (stripos($current_type, 'VARCHAR') === false) {
        $response['steps'][] = "STEP 2: Modifying column to VARCHAR...";
        $sql = "ALTER TABLE `mulai_nabung` 
                MODIFY COLUMN `jenis_tabungan` VARCHAR(100) DEFAULT 'Tabungan Reguler'";
    } else {
        $response['steps'][] = "STEP 2: Column already VARCHAR, skipping modify...";
        $sql = null;
    }
    
    if ($sql) {
        if (!$connect->query($sql)) {
            throw new Exception("Gagal modify column: " . $connect->error);
        }
        $response['steps'][] = "✓ Column modified successfully";
    }
    
    // STEP 3: Update existing data (convert 0 to default)
    $response['steps'][] = "STEP 3: Updating data with 0 or NULL values...";
    
    $update_sql = "UPDATE `mulai_nabung` 
                   SET `jenis_tabungan` = 'Tabungan Reguler' 
                   WHERE `jenis_tabungan` = '0' 
                   OR `jenis_tabungan` = 0 
                   OR `jenis_tabungan` IS NULL 
                   OR `jenis_tabungan` = ''";
    
    if ($connect->query($update_sql)) {
        $affected = $connect->affected_rows;
        $response['steps'][] = "Updated " . $affected . " rows";
    } else {
        throw new Exception("Gagal update data: " . $connect->error);
    }
    
    // STEP 4: Verify column after changes
    $response['steps'][] = "STEP 4: Verifying column structure...";
    
    $verify = $connect->query("DESCRIBE `mulai_nabung`");
    if (!$verify) {
        throw new Exception("Gagal verify: " . $connect->error);
    }
    
    $column_after = null;
    while ($row = $verify->fetch_assoc()) {
        if ($row['Field'] === 'jenis_tabungan') {
            $column_after = $row;
        }
    }
    
    $response['column_after_fix'] = $column_after;
    $response['steps'][] = "✓ Column type is now: " . ($column_after['Type'] ?? 'UNKNOWN');
    
    // STEP 5: Check sample data
    $response['steps'][] = "STEP 5: Checking sample data...";
    
    $sample = $connect->query("SELECT id_mulai_nabung, jenis_tabungan, status FROM `mulai_nabung` ORDER BY id_mulai_nabung DESC LIMIT 5");
    if (!$sample) {
        throw new Exception("Gagal fetch sample: " . $connect->error);
    }
    
    $sample_data = [];
    while ($row = $sample->fetch_assoc()) {
        $sample_data[] = $row;
    }
    
    $response['sample_data'] = $sample_data;
    $response['steps'][] = "✓ Found " . count($sample_data) . " sample records";
    
    // Check if any still have 0
    $zero_count_result = $connect->query("SELECT COUNT(*) as cnt FROM `mulai_nabung` WHERE jenis_tabungan = '0' OR jenis_tabungan = 0");
    $zero_count = $zero_count_result->fetch_assoc()['cnt'];
    
    $response['steps'][] = "Records with value 0: " . $zero_count;
    
    if ($zero_count > 0) {
        $response['steps'][] = "STEP 6: Running additional cleanup...";
        $connect->query("UPDATE `mulai_nabung` SET jenis_tabungan = 'Tabungan Reguler' WHERE jenis_tabungan = 0 OR jenis_tabungan = '0'");
        $response['steps'][] = "✓ Cleanup done";
    }
    
    $response['status'] = 'success';
    $response['message'] = 'Semua perbaikan berhasil diterapkan!';
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
    $response['errors'][] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
