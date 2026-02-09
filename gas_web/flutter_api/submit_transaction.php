<?php
/**
 * API: Submit Pending Transaction (Setoran/Top Up)
 * Untuk submit transaksi dari mobile yang perlu approval admin
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required = ['id_pengguna', 'nominal', 'metode'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            echo json_encode([
                "success" => false,
                "message" => "Field $field wajib diisi"
            ]);
            exit();
        }
    }
    
    $id_pengguna = $connect->real_escape_string($_POST['id_pengguna']);
    $nominal = floatval($_POST['nominal']);
    $metode = $connect->real_escape_string($_POST['metode']);
    $nomor_tujuan = isset($_POST['nomor_tujuan']) ? $connect->real_escape_string($_POST['nomor_tujuan']) : '';
    $bukti_transfer = isset($_POST['bukti_transfer']) ? $connect->real_escape_string($_POST['bukti_transfer']) : '';
    $keterangan = isset($_POST['keterangan']) ? $connect->real_escape_string($_POST['keterangan']) : '';
    
    // Validasi nominal
    if ($nominal <= 0) {
        echo json_encode([
            "success" => false,
            "message" => "Nominal harus lebih dari 0"
        ]);
        exit();
    }
    
    // Cek apakah user exist
    $check = $connect->query("SELECT id_pengguna, nama FROM pengguna WHERE id_pengguna='$id_pengguna'");
    if ($check->num_rows == 0) {
        echo json_encode([
            "success" => false,
            "message" => "User tidak ditemukan"
        ]);
        exit();
    }
    
    try {
        // Insert ke pending_transactions
        $sql = "INSERT INTO pending_transactions (
                    id_pengguna, jenis_transaksi, jumlah, metode_pembayaran, bukti_pembayaran
                ) VALUES (
                    '$id_pengguna', 'setoran', $nominal, '$metode', '$bukti_transfer'
                )";
        
        if ($connect->query($sql)) {
            $id_pending = $connect->insert_id;
            
            echo json_encode([
                "success" => true,
                "message" => "Transaksi berhasil diajukan. Menunggu approval admin.",
                "data" => [
                    "id_pending" => $id_pending,
                    "nominal" => $nominal,
                    "status" => "pending"
                ]
            ]);
        } else {
            throw new Exception($connect->error);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "message" => "Gagal submit transaksi: " . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed"
    ]);
}

