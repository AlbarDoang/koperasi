<?php
/**
 * API: Get Detail Transaksi
 * Method: GET/POST
 * 
 * Parameters:
 * - id_transaksi (required): ID dari tabel transaksi
 * 
 * Logic:
 * 1. Query data dari tabel transaksi berdasarkan id_transaksi
 * 2. Ambil jenis_transaksi dan id_pengguna
 * 3. Conditional:
 *    - Jika jenis_transaksi == 'penarikan': query tabungan_keluar JOIN jenis_tabungan
 *    - Jika jenis_transaksi == 'setoran': query tabungan_masuk JOIN jenis_tabungan
 * 4. Return: jumlah, status, keterangan, created_at, nama_jenis AS jenis_tabungan
 * 
 * Timezone: Asia/Jakarta (UTC+7)
 */

// Set timezone ke Indonesia (UTC+7)
date_default_timezone_set('Asia/Jakarta');

// Prevent PHP warnings/HTML from breaking JSON responses
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ob_start();

// Include database connection
include 'connection.php';

// Set MySQL timezone ke +07:00
if ($connect) {
    mysqli_query($connect, "SET time_zone = '+07:00'");
}

// Get id_transaksi from GET or POST
$id_transaksi = isset($_POST['id_transaksi']) ? trim($_POST['id_transaksi']) : '';
if (empty($id_transaksi)) {
    $id_transaksi = isset($_GET['id_transaksi']) ? trim($_GET['id_transaksi']) : '';
}

// Validate id_transaksi
$id_transaksi = intval($id_transaksi);
if ($id_transaksi <= 0) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id_transaksi wajib diisi dan harus numerik'
    ]);
    exit();
}

if (empty($connect)) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    // STEP 1: Query data dari tabel transaksi berdasarkan id_transaksi
    $sql_trans = "SELECT id_transaksi, id_pengguna, jenis_transaksi, jumlah, tanggal, status, keterangan 
                  FROM transaksi 
                  WHERE id_transaksi = ? 
                  LIMIT 1";
    
    $stmt_trans = $connect->prepare($sql_trans);
    if (!$stmt_trans) {
        throw new Exception('Prepare statement failed: ' . $connect->error);
    }
    
    $stmt_trans->bind_param('i', $id_transaksi);
    if (!$stmt_trans->execute()) {
        throw new Exception('Query failed: ' . $stmt_trans->error);
    }
    
    $result_trans = $stmt_trans->get_result();
    if (!$result_trans || $result_trans->num_rows == 0) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaksi tidak ditemukan'
        ]);
        exit();
    }
    
    $trans_row = $result_trans->fetch_assoc();
    $jenis_transaksi = strtolower($trans_row['jenis_transaksi']);
    $id_pengguna = intval($trans_row['id_pengguna']);
    $stmt_trans->close();
    
    // STEP 2: Based on jenis_transaksi, query dari tabungan_masuk atau tabungan_keluar
    $detail_data = null;
    
    if ($jenis_transaksi == 'penarikan') {
        // Query from tabungan_keluar JOIN jenis_tabungan
        $sql_detail = "SELECT 
                            tk.id,
                            tk.id_pengguna,
                            tk.id_jenis_tabungan,
                            tk.jumlah,
                            tk.status,
                            tk.keterangan,
                            tk.created_at,
                            jt.nama_jenis
                       FROM tabungan_keluar tk
                       LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                       WHERE tk.id_pengguna = ?
                       ORDER BY tk.created_at DESC
                       LIMIT 1";
        
        $stmt_detail = $connect->prepare($sql_detail);
        if (!$stmt_detail) {
            throw new Exception('Prepare detail query failed: ' . $connect->error);
        }
        
        $stmt_detail->bind_param('i', $id_pengguna);
        if (!$stmt_detail->execute()) {
            throw new Exception('Query detail failed: ' . $stmt_detail->error);
        }
        
        $result_detail = $stmt_detail->get_result();
        if ($result_detail && $result_detail->num_rows > 0) {
            $detail_data = $result_detail->fetch_assoc();
        }
        $stmt_detail->close();
        
    } elseif ($jenis_transaksi == 'setoran') {
        // Query from tabungan_masuk JOIN jenis_tabungan
        $sql_detail = "SELECT 
                            tm.id,
                            tm.id_pengguna,
                            tm.id_jenis_tabungan,
                            tm.jumlah,
                            tm.status,
                            tm.keterangan,
                            tm.created_at,
                            jt.nama_jenis
                       FROM tabungan_masuk tm
                       LEFT JOIN jenis_tabungan jt ON jt.id = tm.id_jenis_tabungan
                       WHERE tm.id_pengguna = ?
                       ORDER BY tm.created_at DESC
                       LIMIT 1";
        
        $stmt_detail = $connect->prepare($sql_detail);
        if (!$stmt_detail) {
            throw new Exception('Prepare detail query failed: ' . $connect->error);
        }
        
        $stmt_detail->bind_param('i', $id_pengguna);
        if (!$stmt_detail->execute()) {
            throw new Exception('Query detail failed: ' . $stmt_detail->error);
        }
        
        $result_detail = $stmt_detail->get_result();
        if ($result_detail && $result_detail->num_rows > 0) {
            $detail_data = $result_detail->fetch_assoc();
        }
        $stmt_detail->close();
    }
    
    // STEP 3: Format dan return response
    $response_data = [
        'id_transaksi' => (int) $trans_row['id_transaksi'],
        'id_pengguna' => (int) $trans_row['id_pengguna'],
        'jenis_transaksi' => $trans_row['jenis_transaksi'],
        'tanggal' => $trans_row['tanggal'],
        'status' => $trans_row['status'],
        'keterangan' => $trans_row['keterangan'] ?? '',
    ];
    
    // Add detail data jika ditemukan
    if ($detail_data !== null) {
        $response_data['jumlah'] = (int) $detail_data['jumlah'];
        $response_data['detail_status'] = $detail_data['status'];
        $response_data['detail_keterangan'] = $detail_data['keterangan'];
        $response_data['detail_created_at'] = $detail_data['created_at'];
        $response_data['id_jenis_tabungan'] = (int) $detail_data['id_jenis_tabungan'];
        $response_data['jenis_tabungan'] = $detail_data['nama_jenis'] ?? 'Tabungan Reguler';
    } else {
        // Fallback: use data from transaksi table
        $response_data['jumlah'] = (int) $trans_row['jumlah'];
        $response_data['detail_status'] = $trans_row['status'];
        $response_data['detail_keterangan'] = $trans_row['keterangan'];
        $response_data['detail_created_at'] = $trans_row['tanggal'];
        $response_data['jenis_tabungan'] = 'Tabungan Reguler';  // Default if not found
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Detail transaksi berhasil diambil',
        'data' => $response_data
    ]);
    exit();
    
} catch (Exception $e) {
    @file_put_contents(
        __DIR__ . '/api_debug.log',
        date('c') . " [get_detail_transaksi] Error: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
    exit();
}
?>
