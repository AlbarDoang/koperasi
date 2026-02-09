<?php
/**
 * Test Script: Verifikasi fix untuk jenis_tabungan display bug
 * 
 * Masalah: query mengambil jenis_tabungan terbaru, bukan jenis_tabungan spesifik transaksi
 * Solusi: extract mulai_nabung ID dari keterangan dan query langsung ke mulai_nabung table
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Direct connection without api_bootstrap to avoid JSON wrapping
$connect = new mysqli('localhost', 'root', '', 'tabungan');

if ($connect->connect_error) {
    echo "Connection failed: " . $connect->connect_error . "\n";
    exit(1);
}

echo "=== TEST: Jenis Tabungan Fix ===\n\n";

// Test 1: Check if we can extract mulai_nabung ID from keterangan
echo "TEST 1: Extract mulai_nabung ID from keterangan\n";
echo "------\n";

$test_strings = [
    'Mulai nabung tunai (mulai_nabung 149)',
    'Setoran Tabungan Disetujui (mulai_nabung 298)',
    'Setoran Tabungan Ditolak (mulai_nabung 42)',
    'Some other description'
];

foreach ($test_strings as $str) {
    if (preg_match('/mulai_nabung\s+(\d+)/i', $str, $matches)) {
        echo "✓ '{$str}' → ID: {$matches[1]}\n";
    } else {
        echo "✗ '{$str}' → No match\n";
    }
}

echo "\n";

// Test 2: Verify mulai_nabung table has jenis_tabungan data
echo "TEST 2: Check mulai_nabung table with jenis_tabungan\n";
echo "------\n";

$sql = "SELECT id_mulai_nabung, jenis_tabungan, jumlah, status FROM mulai_nabung ORDER BY id_mulai_nabung DESC LIMIT 5;";
$result = $connect->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " recent mulai_nabung records:\n";
    while ($row = $result->fetch_assoc()) {
        $jenis = $row['jenis_tabungan'] ?? 'NULL';
        $status = $row['status'] ?? 'NULL';
        echo "  ID: {$row['id_mulai_nabung']}, Jenis: '{$jenis}', Status: {$status}\n";
    }
} else {
    echo "No mulai_nabung data found or query failed.\n";
}

echo "\n";

// Test 3: Check transaksi table with keterangan containing mulai_nabung ID
echo "TEST 3: Check transaksi table with keterangan\n";
echo "------\n";

$sql = "SELECT id_transaksi, jenis_transaksi, keterangan, status FROM transaksi WHERE jenis_transaksi = 'setoran' ORDER BY id_transaksi DESC LIMIT 5;";
$result = $connect->query($sql);

if ($result && $result->num_rows > 0) {
    echo "Found " . $result->num_rows . " recent setoran transactions:\n";
    while ($row = $result->fetch_assoc()) {
        $keterangan = $row['keterangan'] ?? '';
        $status = $row['status'] ?? 'NULL';
        
        // Try to extract mulai_nabung ID
        $mulai_id = null;
        if (preg_match('/mulai_nabung\s+(\d+)/i', $keterangan, $matches)) {
            $mulai_id = $matches[1];
        }
        
        echo "  TX_ID: {$row['id_transaksi']}, Status: {$status}\n";
        echo "    Keterangan: '{$keterangan}'\n";
        echo "    Extracted mulai_nabung ID: " . ($mulai_id ? $mulai_id : "NONE") . "\n";
        
        // If we found an ID, verify it corresponds to correct jenis_tabungan
        if ($mulai_id) {
            $mn_sql = "SELECT jenis_tabungan FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1";
            $mn_stmt = $connect->prepare($mn_sql);
            if ($mn_stmt) {
                $mn_stmt->bind_param('i', $mulai_id);
                $mn_stmt->execute();
                $mn_res = $mn_stmt->get_result();
                if ($mn_res && $mn_res->num_rows > 0) {
                    $mn_row = $mn_res->fetch_assoc();
                    echo "    ✓ Found jenis_tabungan from mulai_nabung: '{$mn_row['jenis_tabungan']}'\n";
                } else {
                    echo "    ✗ mulai_nabung ID {$mulai_id} not found\n";
                }
                $mn_stmt->close();
            }
        }
    }
} else {
    echo "No setoran transactions found.\n";
}

echo "\n=== TEST COMPLETE ===\n";

$connect->close();
?>
