<?php
/**
 * API: Get Riwayat Transaksi (Semua Jenis)
 * Mengambil data dari tabel transaksi
 * Menampilkan semua jenis transaksi: setoran, penarikan, transfer_masuk, transfer_keluar
 * Filter: id_pengguna dan status = 'approved'
 * 
 * Timezone: Asia/Jakarta (UTC+7)
 * 
 * Params (GET/POST): id_pengguna
 */

// Set timezone ke Indonesia (UTC+7)
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Prevent PHP warnings/HTML from breaking JSON responses
ini_set('display_errors', '0');

ob_start();

include 'connection.php';

// Set MySQL timezone ke +07:00
if ($connect) {
    mysqli_query($connect, "SET time_zone = '+07:00'");
}

// Get id_pengguna from POST or GET
$id_pengguna = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : '';
if (empty($id_pengguna)) {
    $id_pengguna = isset($_GET['id_pengguna']) ? trim($_GET['id_pengguna']) : '';
}

// Validate id_pengguna
$id_pengguna = intval($id_pengguna);
if ($id_pengguna <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id_pengguna wajib diisi dan harus numerik'
    ]);
    exit();
}

if (empty($connect)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    // Query all transaksi for this user
    $sql_trans = "SELECT id_transaksi, id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status FROM transaksi WHERE id_pengguna = ? ORDER BY tanggal DESC";
    $stmt_trans = $connect->prepare($sql_trans);
    if (!$stmt_trans) {
        throw new Exception('Prepare failed: ' . $connect->error);
    }
    $stmt_trans->bind_param('i', $id_pengguna);
    if (!$stmt_trans->execute()) {
        throw new Exception('Execute failed: ' . $stmt_trans->error);
    }
    $result_trans = $stmt_trans->get_result();
    $data = [];
    
    while ($row = $result_trans->fetch_assoc()) {
        $jenis_trans = strtolower($row['jenis_transaksi']);
        $id_jenis_tabungan = null;
        $jenis_tabungan = 'Tabungan Reguler';  // default
        
        // LOGIC: Query dari tabungan_keluar atau tabungan_masuk untuk ambil jenis_tabungan yang benar
        if ($jenis_trans == 'penarikan') {
            // Query from tabungan_keluar JOIN jenis_tabungan
            // Try to extract mulai_nabung ID from keterangan first for accurate matching
            $mulai_nabung_id = null;
            if (preg_match('/mulai_nabung\s+(\d+)/i', $row['keterangan'] ?? '', $matches)) {
                $mulai_nabung_id = intval($matches[1]);
            }
            
            if ($mulai_nabung_id > 0) {
                // Join with mulai_nabung if available to get the correct jenis_tabungan for this transaction
                $sql_detail = "SELECT COALESCE(mn.jenis_tabungan, tk.id_jenis_tabungan) as jenis_id, 
                                      COALESCE(mn.jenis_tabungan, jt.nama_jenis) as jenis_name
                              FROM tabungan_keluar tk
                              LEFT JOIN mulai_nabung mn ON mn.id_mulai_nabung = ?
                              LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                              WHERE tk.id_pengguna = ?
                              LIMIT 1";
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('ii', $mulai_nabung_id, $id_pengguna);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            if (!empty($row_detail['jenis_name'])) {
                                $jenis_tabungan = $row_detail['jenis_name'];
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            } else {
                // Fallback: Query by id_pengguna and match by closest timestamp
                $sql_detail = "SELECT tk.id_jenis_tabungan, jt.nama_jenis 
                              FROM tabungan_keluar tk
                              LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                              WHERE tk.id_pengguna = ?
                              ORDER BY ABS(UNIX_TIMESTAMP(tk.created_at) - UNIX_TIMESTAMP(?))
                              LIMIT 1";
                
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('is', $id_pengguna, $row['tanggal']);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            $id_jenis_tabungan = intval($row_detail['id_jenis_tabungan']);
                            if (!empty($row_detail['nama_jenis'])) {
                                $jenis_tabungan = $row_detail['nama_jenis'];
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            }
            
        } elseif ($jenis_trans == 'setoran') {
            // Query from mulai_nabung (direct) or tabungan_masuk (fallback)
            // Try to extract mulai_nabung ID from keterangan first for accurate matching
            $mulai_nabung_id = null;
            if (preg_match('/mulai_nabung\s+(\d+)/i', $row['keterangan'] ?? '', $matches)) {
                $mulai_nabung_id = intval($matches[1]);
            }
            
            if ($mulai_nabung_id > 0) {
                // Use mulai_nabung table directly for accurate jenis_tabungan
                $sql_detail = "SELECT jenis_tabungan FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1";
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('i', $mulai_nabung_id);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            if (!empty($row_detail['jenis_tabungan'])) {
                                $jenis_tabungan = $row_detail['jenis_tabungan'];
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            } else {
                // Fallback: Query from tabungan_masuk with closest timestamp match
                $sql_detail = "SELECT tm.id_jenis_tabungan, jt.nama_jenis 
                              FROM tabungan_masuk tm
                              LEFT JOIN jenis_tabungan jt ON jt.id = tm.id_jenis_tabungan
                              WHERE tm.id_pengguna = ?
                              ORDER BY ABS(UNIX_TIMESTAMP(tm.created_at) - UNIX_TIMESTAMP(?))
                              LIMIT 1";
                
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('is', $id_pengguna, $row['tanggal']);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            $id_jenis_tabungan = intval($row_detail['id_jenis_tabungan']);
                            if (!empty($row_detail['nama_jenis'])) {
                                $jenis_tabungan = $row_detail['nama_jenis'];
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            }
        }
        
        // Normalize status display: 'ditolak' -> 'rejected', 'pending'/'proses' for consistency
        $status_display = $row['status'];
        if (strtolower($status_display) === 'ditolak') {
            $status_display = 'rejected';
        } elseif (strtolower($status_display) === 'proses') {
            $status_display = 'pending';
        }
        
        error_log('[DEBUG] TX: id=' . $row['id_transaksi'] . ' jenis_tabungan=' . $jenis_tabungan . ' tanggal=' . $row['tanggal'] . ' timezone=UTC+7');
        
        // Ensure tanggal is in proper format
        $tanggal_final = $row['tanggal'];
        if (strlen($tanggal_final) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_final)) {
            // If only date (Y-m-d format), append time
            $tanggal_final = $tanggal_final . ' 00:00:00';
        }
        
        $data[] = [
            'id' => (int)$row['id_transaksi'],
            'id_transaksi' => (int)$row['id_transaksi'],
            'id_pengguna' => (int)$row['id_pengguna'],
            'jenis_transaksi' => $row['jenis_transaksi'],
            'id_jenis_tabungan' => $id_jenis_tabungan,
            'jumlah' => (int)$row['jumlah'],
            'saldo_sebelum' => (int)$row['saldo_sebelum'],
            'saldo_sesudah' => (int)$row['saldo_sesudah'],
            'keterangan' => $row['keterangan'] ?? '',
            'created_at' => $tanggal_final,
            'status' => $status_display,
            'jenis_tabungan' => $jenis_tabungan
        ];
    }
    $stmt_trans->close();

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'total' => count($data),
            'timestamp' => date('c'),
            'timezone' => 'Asia/Jakarta (UTC+7)'
        ]
    ]);
    exit();

} catch (Exception $e) {
    @file_put_contents(
        __DIR__ . '/api_debug.log',
        date('c') . " [get_riwayat_transaksi] Error: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
    exit();
}
?>
