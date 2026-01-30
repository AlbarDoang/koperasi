<?php
header('Content-Type: application/json');
require_once dirname(__DIR__) . '/config/database.php';

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($userId <= 0) {
    die(json_encode(['error' => 'User ID required']));
}

$con = getConnection();

// Check what's in database for this user
$sql = "SELECT 
    id_transaksi, 
    id_anggota,
    jenis_transaksi,
    jumlah,
    status,
    tanggal,
    keterangan
FROM transaksi 
WHERE id_anggota = ? 
ORDER BY tanggal DESC 
LIMIT 20";

$stmt = $con->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'total' => count($data),
    'transactions' => $data
]);
?>
