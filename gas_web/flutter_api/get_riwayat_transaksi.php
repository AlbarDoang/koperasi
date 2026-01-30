<?php
/**
 * API: Get Riwayat Transaksi (Semua Jenis)
 * Mengambil data dari tabel transaksi
 * Menampilkan semua jenis transaksi: setoran, penarikan, transfer_masuk, transfer_keluar
 * Filter: id_anggota dan status = 'approved'
 * 
 * Params (GET/POST): id_pengguna (map ke id_anggota)
 */
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

// Get id_pengguna from POST or GET
$id_pengguna = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : '';
if (empty($id_pengguna)) {
    $id_pengguna = isset($_GET['id_pengguna']) ? trim($_GET['id_pengguna']) : '';
}

// Validate id_pengguna
$id_pengguna = intval($id_pengguna);
if ($id_pengguna <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id_pengguna wajib diisi dan harus numerik'
    ]);
    exit();
}

if (empty($connect)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    // Query unified dari tabel transaksi
    // Menampilkan SEMUA jenis transaksi dengan SEMUA status (pending, approved, rejected)
    // Filter: id_anggota
    // Status breakdown:
    // - 'pending'/'proses': waiting for admin approval
    // - 'approved': approved/completed successfully
    // - 'rejected'/'ditolak': rejected/denied
    $sql = "
        SELECT
            id_transaksi AS id,
            jenis_transaksi,
            jumlah,
            saldo_sebelum,
            saldo_sesudah,
            keterangan,
            tanggal AS created_at,
            status,
            CAST(
              SUBSTRING_INDEX(
                SUBSTRING_INDEX(keterangan, 'mulai_nabung ', -1),
              ')', 1) AS UNSIGNED
            ) AS id_mulai_nabung
        FROM transaksi
        WHERE id_anggota = ?
        ORDER BY tanggal DESC
    ";

    $stmt = $connect->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $connect->error);
    }

    // Bind parameter: id_pengguna map ke id_anggota
    $stmt->bind_param('i', $id_pengguna);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $data = [];

    while ($row = $result->fetch_assoc()) {
        // Normalize status display: 'ditolak' -> 'rejected', 'pending'/'proses' for consistency
        $status_display = $row['status'];
        if (strtolower($status_display) === 'ditolak') {
            $status_display = 'rejected';
        } elseif (strtolower($status_display) === 'proses') {
            $status_display = 'pending';
        }
        
        $data[] = [
            'id' => (int)$row['id'],
            'id_transaksi' => (int)$row['id'],
            'id_mulai_nabung' => !empty($row['id_mulai_nabung']) ? (int)$row['id_mulai_nabung'] : null,
            'jenis_transaksi' => $row['jenis_transaksi'],  // setoran, penarikan, transfer_masuk, transfer_keluar
            'jumlah' => (int)$row['jumlah'],
            'saldo_sebelum' => (int)$row['saldo_sebelum'],
            'saldo_sesudah' => (int)$row['saldo_sesudah'],
            'keterangan' => $row['keterangan'] ?? '',
            'created_at' => $row['created_at'],
            'status' => $status_display  // 'pending', 'approved', 'rejected'
        ];
    }

    $stmt->close();

    @ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'total' => count($data),
            'timestamp' => date('c')
        ]
    ]);
    exit();

} catch (Exception $e) {
    @file_put_contents(
        __DIR__ . '/api_debug.log',
        date('c') . " [get_riwayat_transaksi] Error: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    @ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
    exit();
}
?>
