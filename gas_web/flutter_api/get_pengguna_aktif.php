<?php
header('Content-Type: application/json; charset=utf-8');

// Gunakan koneksi dari config/database.php
require_once __DIR__ . '/../config/database.php';

try {
    // Dapatkan koneksi
    $con = getConnection();
    
    if (!$con) {
        throw new Exception('Gagal terhubung ke database. Error: ' . mysqli_connect_error());
    }
    
    // Verifikasi database yang digunakan
    $db_name = $con->query("SELECT DATABASE() as db_name");
    if (!$db_name) {
        throw new Exception('Gagal mengecek database: ' . $con->error);
    }
    
    $db_row = $db_name->fetch_assoc();
    error_log('get_pengguna_aktif.php - Database: ' . $db_row['db_name']);
    
    // Query untuk get pengguna dengan status_akun 'approved' saja
    $query = "SELECT id, nama_lengkap, no_hp FROM pengguna WHERE status_akun = 'approved' ORDER BY nama_lengkap ASC LIMIT 500";
    
    error_log('get_pengguna_aktif.php - Query: ' . $query);
    
    $result = $con->query($query);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . $con->error . ' | Query: ' . $query);
    }
    
    $pengguna_list = [];
    while ($row = $result->fetch_assoc()) {
        $pengguna_list[] = [
            'id' => intval($row['id']),
            'nama' => $row['nama_lengkap'] ?? '',
            'nomor_hp' => $row['no_hp'] ?? ''
        ];
    }
    
    error_log('get_pengguna_aktif.php - Berhasil mengambil ' . count($pengguna_list) . ' pengguna');
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => $pengguna_list,
        'count' => count($pengguna_list)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('get_pengguna_aktif.php Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
