<?php
include 'connection.php';
header('Content-Type: application/json');

// Show all notifications (no filter)
$sql = "SELECT id, id_pengguna, type, title, message, data, read_status, created_at FROM notifikasi ORDER BY created_at DESC LIMIT 50";
$res = $connect->query($sql);

$list = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $list[] = [
            'id' => (int)$row['id'],
            'id_pengguna' => (int)$row['id_pengguna'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'data' => $row['data'],
            'read' => (bool)$row['read_status'],
            'created_at' => $row['created_at'],
        ];
    }
}

echo json_encode(['success' => true, 'total' => count($list), 'data' => $list], JSON_PRETTY_PRINT);
?>
