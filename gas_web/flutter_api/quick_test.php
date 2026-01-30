<?php
require __DIR__ . '/connection.php';

echo "=== MULAI NABUNG FLOW TEST ===\n\n";

// Check recent entries
$sql = "SELECT 
    m.id, m.jumlah, m.status,
    (SELECT COUNT(*) FROM transaksi t 
     WHERE t.keterangan LIKE CONCAT('Mulai nabung tunai (mulai_nabung ', m.id, ')')) as txn_count
FROM mulai_nabung m
WHERE m.id >= 290
ORDER BY m.id DESC LIMIT 10";

$result = $connect->query($sql);
echo "Recent mulai_nabung entries:\n";
echo str_repeat("-", 60) . "\n";

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $cnt = $row['txn_count'];
    $status = $row['status'];
    $jumlah = $row['jumlah'];
    
    $check = ($cnt == 1) ? "✓" : ($cnt > 1 ? "❌" : "?");
    echo "$check ID $id: amount={$jumlah}, status={$status}, txn_count={$cnt}\n";
}

echo "\n";
$connect->close();
?>
