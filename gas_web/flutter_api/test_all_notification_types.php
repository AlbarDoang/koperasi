<?php
/**
 * TEST: Verify all three notification types appear correctly
 */
include 'connection.php';
header('Content-Type: application/json');

// Test user 3 has all three notification types
$user_id = 3;

$sql = "SELECT id, type, title, message, created_at FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $connect->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

$all_notifs = [];
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

while ($row = $res->fetch_assoc()) {
    $title_l = mb_strtolower($row['title'] ?? '');
    $all_notifs[] = [
        'id' => (int)$row['id'],
        'type' => $row['type'],
        'title' => $row['title'],
        'message' => $row['message'],
        'created_at' => $row['created_at'],
    ];
    
    if (strpos($title_l, 'menunggu') !== false || strpos($title_l, 'dikirim') !== false) {
        $pending_count++;
    } elseif (strpos($title_l, 'disetujui') !== false || strpos($title_l, 'berhasil') !== false) {
        $approved_count++;
    } elseif (strpos($title_l, 'ditolak') !== false) {
        $rejected_count++;
    }
}

$stmt->close();

echo json_encode([
    'success' => true,
    'test_user_id' => $user_id,
    'total_notifications' => count($all_notifs),
    'pending_count' => $pending_count,
    'approved_count' => $approved_count,
    'rejected_count' => $rejected_count,
    'notifications' => $all_notifs,
    'test_passed' => ($pending_count > 0 && $approved_count > 0 && $rejected_count > 0)
], JSON_PRETTY_PRINT);
?>
