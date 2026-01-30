<?php
/**
 * Database Migration Helper: Ensure mulai_nabung table has required columns
 * Dijalankan sekali untuk memastikan kolom 'sumber' ada di tabel mulai_nabung
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/api_bootstrap.php';

if (empty($connect)) {
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

try {
    // Check if 'sumber' column exists in mulai_nabung
    $col_check = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'sumber'");
    $has_sumber = ($col_check && $col_check->num_rows > 0);
    
    if (!$has_sumber) {
        // Add sumber column with default value
        $alter_sql = "ALTER TABLE `mulai_nabung` 
                      ADD COLUMN `sumber` VARCHAR(50) DEFAULT 'user' COMMENT 'Sumber setoran: user, admin' 
                      AFTER `status`";
        
        if ($connect->query($alter_sql)) {
            echo json_encode([
                'success' => true,
                'message' => 'Kolom sumber berhasil ditambahkan ke tabel mulai_nabung',
                'action' => 'ALTER TABLE - ADD COLUMN sumber'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menambahkan kolom sumber: ' . $connect->error
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Kolom sumber sudah ada di tabel mulai_nabung',
            'action' => 'NO ACTION NEEDED'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

?>
