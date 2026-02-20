<?php
header("Content-Type: application/json");
require_once "koneksi.php";

if (!isset($_GET['id_pengguna']) || empty($_GET['id_pengguna'])) {
    echo json_encode([
        "success" => false,
        "message" => "Parameter id_pengguna tidak ditemukan"
    ]);
    exit;
}

$id_pengguna = $_GET['id_pengguna'];

try {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifikasi WHERE id_pengguna = ? AND read_status = 0");
    $stmt->bind_param("i", $id_pengguna);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode([
        "success" => true,
        "total" => (int)$row['total']
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan: " . $e->getMessage()
    ]);
}
?>