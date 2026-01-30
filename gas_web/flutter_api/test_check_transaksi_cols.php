<?php
include 'connection.php';
header('Content-Type: application/json');

// Check table structure
$sql = "SHOW COLUMNS FROM transaksi";
$res = $connect->query($sql);

$columns = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $columns[] = $row;
    }
}

echo json_encode(['success' => true, 'columns' => $columns], JSON_PRETTY_PRINT);
?>
