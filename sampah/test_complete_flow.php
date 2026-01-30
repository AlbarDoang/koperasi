<?php
/**
 * COMPREHENSIVE FLOW TEST: Simulate complete mulai_nabung submission flow
 * 
 * This tests:
 * 1. buat_mulai_nabung creates exactly 1 pending transaksi per submission
 * 2. get_riwayat_transaksi returns correct id_mulai_nabung
 * 3. admin_verifikasi_mulai_nabung updates status WITHOUT creating duplicates
 * 4. Dedup key matching works correctly
 */

require 'gas_web/flutter_api/connection.php';

echo "=== COMPREHENSIVE FLOW TEST ===\n\n";

// Test 1: Check recent mulai_nabung entries and their transaksi
echo "TEST 1: Recent mulai_nabung and transaksi count\n";
echo str_repeat("-", 60) . "\n";

$result = $connect->query("
    SELECT 
        m.id,
        m.nomor_hp,
        m.jumlah,
        m.status as mulai_status,
        COUNT(t.id_transaksi) as txn_count,
        GROUP_CONCAT(CONCAT('txn_id=', t.id_transaksi, '|status=', t.status) SEPARATOR '; ') as txns
    FROM mulai_nabung m
    LEFT JOIN transaksi t ON m.nomor_hp = t.id_anggota  
        AND t.keterangan LIKE CONCAT('Mulai nabung tunai (mulai_nabung ', m.id, ')')
    WHERE m.id >= 290
    GROUP BY m.id
    ORDER BY m.id DESC
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "mulai_nabung ID {$row['id']}: status={$row['mulai_status']}, nomor_hp={$row['nomor_hp']}, jumlah={$row['jumlah']}\n";
        echo "  Transactions: {$row['txn_count']} transaksi\n";
        echo "  Details: {$row['txns']}\n";
        
        if ($row['txn_count'] > 1) {
            echo "  ⚠️  DUPLICATE TRANSACTIONS DETECTED!\n";
        } elseif ($row['txn_count'] == 1) {
            echo "  ✓ OK - exactly 1 transaction\n";
        } else {
            echo "  ❌ NO TRANSACTIONS FOUND!\n";
        }
        echo "\n";
    }
} else {
    echo "No recent mulai_nabung found\n";
}

// Test 2: Check API response structure
echo "\n\nTEST 2: API Response (simulated get_riwayat_transaksi)\n";
echo str_repeat("-", 60) . "\n";

$testUserId = 1;  // Adjust to match an actual user ID
$result = $connect->query("
    SELECT 
        t.id_transaksi,
        t.id_anggota,
        t.jenis_transaksi,
        t.jumlah,
        t.status,
        t.keterangan,
        t.tanggal,
        CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(t.keterangan, 'mulai_nabung ', -1), ')', 1) AS UNSIGNED) AS id_mulai_nabung
    FROM transaksi t
    WHERE t.jenis_transaksi = 'setoran'
    AND t.status IN ('pending', 'approved', 'rejected')
    ORDER BY t.id_transaksi DESC
    LIMIT 5
");

if ($result && $result->num_rows > 0) {
    $dedup_keys = array();
    while ($row = $result->fetch_assoc()) {
        echo "Transaksi ID {$row['id_transaksi']}: \n";
        echo "  jenis={$row['jenis_transaksi']}, jumlah={$row['jumlah']}, status={$row['status']}\n";
        echo "  keterangan: {$row['keterangan']}\n";
        echo "  id_mulai_nabung (extracted): {$row['id_mulai_nabung']}\n";
        
        // Simulate dedup key generation (matching riwayat.dart logic)
        if (!empty($row['id_mulai_nabung'])) {
            $dedup_key = "mulai_nabung:" . $row['id_mulai_nabung'];
        } else {
            $dedup_key = "id_transaksi:" . $row['id_transaksi'];
        }
        
        if (isset($dedup_keys[$dedup_key])) {
            echo "  ⚠️  DUPLICATE DEDUP KEY: $dedup_key (seen before!)\n";
        } else {
            echo "  ✓ OK - Dedup key: $dedup_key\n";
            $dedup_keys[$dedup_key] = true;
        }
        echo "\n";
    }
} else {
    echo "No setoran transaksi found\n";
}

// Test 3: Verify admin_verifikasi cleanup logic
echo "\n\nTEST 3: Check for duplicate pending transactions\n";
echo str_repeat("-", 60) . "\n";

$result = $connect->query("
    SELECT 
        m.id as mulai_id,
        COUNT(t.id_transaksi) as pending_count,
        GROUP_CONCAT(CONCAT('txn_id=', t.id_transaksi) SEPARATOR ', ') as pending_txns
    FROM mulai_nabung m
    LEFT JOIN transaksi t ON t.keterangan LIKE CONCAT('Mulai nabung tunai (mulai_nabung ', m.id, ')')
        AND t.status = 'pending'
    WHERE m.id >= 290
    GROUP BY m.id
    HAVING pending_count > 1
");

if ($result && $result->num_rows > 0) {
    echo "❌ FOUND DUPLICATE PENDING TRANSACTIONS:\n";
    while ($row = $result->fetch_assoc()) {
        echo "mulai_nabung {$row['mulai_id']}: {$row['pending_count']} pending txns - {$row['pending_txns']}\n";
    }
} else {
    echo "✓ OK - No duplicate pending transactions found\n";
}

// Test 4: Verify status transition
echo "\n\nTEST 4: Status transition check\n";
echo str_repeat("-", 60) . "\n";

$result = $connect->query("
    SELECT 
        m.id,
        m.status as mulai_status,
        t.status as txn_status,
        COUNT(*) as cnt
    FROM mulai_nabung m
    LEFT JOIN transaksi t ON t.keterangan LIKE CONCAT('Mulai nabung tunai (mulai_nabung ', m.id, ')')
    WHERE m.id >= 290
    GROUP BY m.id, m.status, t.status
    ORDER BY m.id DESC
");

if ($result && $result->num_rows > 0) {
    echo "Status transitions (mulai_nabung → transaksi):\n";
    while ($row = $result->fetch_assoc()) {
        $check = "?";
        if ($row['mulai_status'] == $row['txn_status']) {
            $check = "✓";
        } elseif ($row['mulai_status'] == 'menunggu_penyerahan' && $row['txn_status'] == 'pending') {
            $check = "✓";
        } elseif ($row['mulai_status'] == 'menunggu_admin' && $row['txn_status'] == 'pending') {
            $check = "✓";
        } else {
            $check = "⚠️";
        }
        
        echo "  $check mulai_nabung({$row['id']})={$row['mulai_status']} → txn={$row['txn_status']}\n";
    }
} else {
    echo "No transitions found\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "TEST COMPLETE\n";

$connect->close();
?>
