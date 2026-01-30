<?php
// Clear Proses/Pending Transactions for Testing
header('Content-Type: application/json');

// Connect to database
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'tabungan';  // Correct database name

$connect = new mysqli($host, $user, $pass, $db);
if ($connect->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $connect->connect_error]));
}

try {
    // Delete all pending/proses transactions (setoran type)
    $sql = "DELETE FROM transaksi WHERE jenis_transaksi = 'setoran' AND status IN ('pending', 'proses')";
    
    if ($connect->query($sql)) {
        $deletedRows = $connect->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => "Deleted $deletedRows pending transactions",
            'deleted_count' => $deletedRows
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Delete failed: ' . $connect->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$connect->close();
?>
