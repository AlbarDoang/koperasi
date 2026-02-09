<?php
/**
 * COMPREHENSIVE TEST: Verify entire flow works
 * 1. Check recent transaction in database
 * 2. Verify its status
 * 3. Simulate what API will return
 * 4. Check if filter would catch it
 */
require __DIR__ . '/connection.php';

echo "=== COMPREHENSIVE RIWAYAT FLOW TEST ===\n\n";

// Get the MOST RECENT mulai_nabung transaction
$sql = "
    SELECT 
        m.id,
        m.jumlah,
        m.status as mulai_status,
        t.id_transaksi,
        t.status as txn_status,
        t.keterangan,
        t.jenis_transaksi,
        t.id_pengguna,
        t.tanggal
    FROM mulai_nabung m
    LEFT JOIN transaksi t ON t.keterangan LIKE CONCAT('Topup tunai (mulai_nabung ', m.id, ')')
        OR t.keterangan LIKE CONCAT('Mulai nabung tunai (mulai_nabung ', m.id, ')')
    ORDER BY m.id DESC
    LIMIT 3
";

$result = $connect->query($sql);
echo "TEST 1: Recent mulai_nabung and their transaksi status\n";
echo str_repeat("-", 70) . "\n";

if ($result && $result->num_rows > 0) {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        $mid = $row['id'];
        $mulai_status = $row['mulai_status'];
        $txn_status = $row['txn_status'];
        $keterangan = $row['keterangan'];
        $user_id = $row['id_pengguna'];
        
        echo "\n$count. mulai_nabung ID $mid:\n";
        echo "   mulai_nabung.status = $mulai_status\n";
        echo "   transaksi.id = {$row['id_transaksi']}\n";
        echo "   transaksi.status = $txn_status\n";
        echo "   keterangan = $keterangan\n";
        
        // Check what API would return
        if ($row['id_transaksi']) {
            $user_id = intval($row['id_pengguna']);
            
            // Simulate get_riwayat_transaksi.php
            $api_sql = "
                SELECT 
                    id_transaksi,
                    status,
                    jenis_transaksi,
                    keterangan
                FROM transaksi
                WHERE id_pengguna = ? AND id_transaksi = ?
            ";
            $stmt = $connect->prepare($api_sql);
            $txn_id = intval($row['id_transaksi']);
            $stmt->bind_param('ii', $user_id, $txn_id);
            $stmt->execute();
            $api_res = $stmt->get_result();
            
            if ($api_res && $api_res->num_rows > 0) {
                $api_row = $api_res->fetch_assoc();
                $api_status = $api_row['status'];
                
                // Simulate normalization (what _load() does)
                $normalized_status = $api_status;
                if (strtolower($api_status) === 'approved' || strtolower($api_status) === 'berhasil' || strtolower($api_status) === 'sukses') {
                    $normalized_status = 'success';
                } elseif (strtolower($api_status) === 'rejected' || strtolower($api_status) === 'ditolak' || strtolower($api_status) === 'tolak' || strtolower($api_status) === 'failed') {
                    $normalized_status = 'rejected';
                } else {
                    $normalized_status = 'pending';
                }
                
                echo "   \n   API WOULD RETURN:\n";
                echo "      Raw status: $api_status\n";
                echo "      After normalization: $normalized_status\n";
                
                // Simulate filter
                $is_final = ($normalized_status === 'success' || $normalized_status === 'rejected' || 
                           strtolower($api_status) === 'approved' || strtolower($api_status) === 'disetujui' ||
                           strtolower($api_status) === 'berhasil' || strtolower($api_status) === 'sukses' ||
                           strtolower($api_status) === 'done' || strtolower($api_status) === 'ditolak' ||
                           strtolower($api_status) === 'tolak' || strtolower($api_status) === 'failed' ||
                           strtolower($api_status) === 'gagal');
                
                if ($is_final) {
                    echo "   ✓ FILTER RESULT: Goes to \"SELESAI\" tab\n";
                } else {
                    echo "   ✗ FILTER RESULT: Stays in \"PROSES\" tab\n";
                }
            }
            $stmt->close();
        }
    }
    echo "\n";
} else {
    echo "No recent transactions found\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "SUMMARY:\n";
echo "- If transaksi.status = 'approved' → should go to SELESAI ✓\n";
echo "- If transaksi.status = 'ditolak' → should go to SELESAI ✓\n";
echo "- If transaksi.status = 'pending' → should stay in PROSES ✓\n";

$connect->close();
?>

