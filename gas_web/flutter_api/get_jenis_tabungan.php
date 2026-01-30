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
    error_log('get_jenis_tabungan.php - Database: ' . $db_row['db_name']);
    
    // Query untuk get jenis tabungan dari tabel jenis_tabungan
    $query = "SELECT id, nama_jenis FROM jenis_tabungan ORDER BY nama_jenis ASC LIMIT 500";
    
    error_log('get_jenis_tabungan.php - Query: ' . $query);
    
    $result = $con->query($query);
    
    if (!$result) {
        throw new Exception('Database query failed: ' . $con->error . ' | Query: ' . $query);
    }
    
    $jenis_list = [];
    while ($row = $result->fetch_assoc()) {
        $jenis_list[] = [
            'id' => intval($row['id']),
            'nama_jenis' => $row['nama_jenis'] ?? '',
            'nama' => $row['nama_jenis'] ?? ''
        ];
    }
    
    error_log('get_jenis_tabungan.php - Berhasil mengambil ' . count($jenis_list) . ' jenis tabungan');
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'data' => $jenis_list,
        'count' => count($jenis_list)
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('get_jenis_tabungan.php Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
