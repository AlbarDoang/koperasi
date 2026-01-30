<?php
/**
 * MIGRATION: Tambahkan kolom `type` ke tabel katasandi_reset_otps
 * 
 * Tujuan: Membedakan tipe OTP (reset_password / aktivasi_akun)
 *         tanpa perlu duplikasi tabel
 * 
 * Perubahan:
 * - ALTER TABLE katasandi_reset_otps ADD COLUMN type VARCHAR(50) DEFAULT 'reset_password'
 * 
 * Aman: Pengecekan IF NOT EXISTS untuk mencegah error jika kolom sudah ada
 */

require_once __DIR__ . '/../config/database.php';

// ============================================================================
// FUNGSI MIGRATION
// ============================================================================

function migrate_add_otp_type_column($connect) {
    $table = 'katasandi_reset_otps';
    $column = 'type';
    $default_value = 'reset_password';
    
    // Check jika tabel ada
    $check_table = $connect->query("
        SELECT COUNT(*) as cnt 
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '{$table}'
    ");
    
    if (!$check_table) {
        return ['success' => false, 'message' => 'Query error: ' . $connect->error];
    }
    
    $row = $check_table->fetch_assoc();
    if ((int)$row['cnt'] === 0) {
        return ['success' => false, 'message' => "Tabel {$table} tidak ditemukan"];
    }
    
    // Check jika kolom sudah ada
    $check_column = $connect->query("
        SELECT COUNT(*) as cnt 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '{$table}' 
        AND COLUMN_NAME = '{$column}'
    ");
    
    if (!$check_column) {
        return ['success' => false, 'message' => 'Query error: ' . $connect->error];
    }
    
    $col_row = $check_column->fetch_assoc();
    if ((int)$col_row['cnt'] > 0) {
        return ['success' => true, 'message' => "Kolom {$column} sudah ada, skip"];
    }
    
    // Tambahkan kolom
    $sql_alter = "
        ALTER TABLE {$table} 
        ADD COLUMN {$column} VARCHAR(50) DEFAULT '{$default_value}' AFTER status
    ";
    
    if (!$connect->query($sql_alter)) {
        return ['success' => false, 'message' => 'ALTER TABLE error: ' . $connect->error];
    }
    
    return ['success' => true, 'message' => "Kolom {$column} berhasil ditambahkan"];
}

// ============================================================================
// JALANKAN MIGRATION
// ============================================================================

if (php_sapi_name() === 'cli' || isset($_GET['__migrate'])) {
    $result = migrate_add_otp_type_column($connect);
    
    if (php_sapi_name() === 'cli') {
        echo json_encode($result) . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    exit();
}

?>
