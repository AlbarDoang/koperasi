<?php
// Quick check for duplicate notifications
include 'connection.php';
date_default_timezone_set('Asia/Jakarta');

$sql = "SELECT id_pengguna, type, title, COUNT(*) as cnt FROM notifikasi WHERE type IS NOT NULL GROUP BY id_pengguna, type, title HAVING cnt > 1 ORDER BY cnt DESC LIMIT 10";

$result = $connect->query($sql);
if (!$result) {
    die("Query error: " . $connect->error);
}

echo "=== TOP 10 DUPLICATE NOTIFICATION GROUPS ===\n\n";
$total = 0;
while ($row = $result->fetch_assoc()) {
    $count = $row['cnt'];
    $duplicate = $count - 1;
    echo "User: " . str_pad($row['id_pengguna'], 4);
    echo " | Type: " . str_pad($row['type'], 20);
    echo " | Title: " . substr($row['title'], 0, 45);
    echo " | Duplicates: " . $duplicate . "\n";
    $total += $duplicate;
}

echo "\n=== SUMMARY ===\n";
echo "Total duplicate notifications to clean: $total\n";
?>
