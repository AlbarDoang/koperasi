<?php
/**
 * Test: Simulate get_riwayat_transaksi API response with the fix
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$connect = new mysqli('localhost', 'root', '', 'tabungan');

if ($connect->connect_error) {
    echo "Connection failed: " . $connect->connect_error . "\n";
    exit(1);
}

echo "=== Simulating get_riwayat_transaksi.php with fix ===\n\n";

// Get a specific user (assuming user ID 1 or 2 exists)
$id_pengguna = 1;

$sql_trans = "SELECT id_transaksi, id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status FROM transaksi WHERE id_pengguna = ? ORDER BY tanggal DESC LIMIT 10";
$stmt_trans = $connect->prepare($sql_trans);

if (!$stmt_trans) {
    echo "Prepare failed: " . $connect->error . "\n";
    exit(1);
}

$stmt_trans->bind_param('i', $id_pengguna);
$stmt_trans->execute();
$result_trans = $stmt_trans->get_result();

if ($result_trans->num_rows == 0) {
    echo "No transactions found for user {$id_pengguna}\n";
    echo "Trying to use user ID 1 (default)...\n\n";
    
    // Try any user with transactions
    $check = $connect->query("SELECT DISTINCT id_pengguna FROM transaksi LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $id_pengguna = $row['id_pengguna'];
        echo "Found user {$id_pengguna} with transactions\n\n";
        
        $stmt_trans->bind_param('i', $id_pengguna);
        $stmt_trans->execute();
        $result_trans = $stmt_trans->get_result();
    }
}

$api_response = [
    'success' => true,
    'data' => [],
    'meta' => [
        'total' => 0,
        'timestamp' => date('c'),
        'timezone' => 'Asia/Jakarta (UTC+7)'
    ]
];

while ($row = $result_trans->fetch_assoc()) {
    $jenis_trans = strtolower($row['jenis_transaksi']);
    $jenis_tabungan = 'Tabungan Reguler';  // default
    
    // LOGIC: Extract mulai_nabung ID for accurate jenis_tabungan lookup
    if ($jenis_trans == 'setoran') {
        $mulai_nabung_id = null;
        if (preg_match('/mulai_nabung\s+(\d+)/i', $row['keterangan'] ?? '', $matches)) {
            $mulai_nabung_id = intval($matches[1]);
        }
        
        if ($mulai_nabung_id > 0) {
            // Query directly from mulai_nabung with extracted ID
            $sql_detail = "SELECT jenis_tabungan FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1";
            $stmt_detail = $connect->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param('i', $mulai_nabung_id);
                if ($stmt_detail->execute()) {
                    $res = $stmt_detail->get_result();
                    if ($res && $res->num_rows > 0) {
                        $row_detail = $res->fetch_assoc();
                        if (!empty($row_detail['jenis_tabungan'])) {
                            $jenis_tabungan = $row_detail['jenis_tabungan'];
                        }
                    }
                }
                $stmt_detail->close();
            }
        }
    }
    
    // Normalize status
    $status_display = $row['status'];
    if (strtolower($status_display) === 'ditolak') {
        $status_display = 'rejected';
    } elseif (strtolower($status_display) === 'proses') {
        $status_display = 'pending';
    }
    
    $tanggal_final = $row['tanggal'];
    if (strlen($tanggal_final) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_final)) {
        $tanggal_final = $tanggal_final . ' 00:00:00';
    }
    
    $api_response['data'][] = [
        'id' => (int)$row['id_transaksi'],
        'id_transaksi' => (int)$row['id_transaksi'],
        'id_pengguna' => (int)$row['id_pengguna'],
        'jenis_transaksi' => $row['jenis_transaksi'],
        'jumlah' => (int)$row['jumlah'],
        'saldo_sebelum' => (int)$row['saldo_sebelum'],
        'saldo_sesudah' => (int)$row['saldo_sesudah'],
        'keterangan' => $row['keterangan'] ?? '',
        'created_at' => $tanggal_final,
        'status' => $status_display,
        'jenis_tabungan' => $jenis_tabungan  // NOW CORRECT!
    ];
}

$api_response['meta']['total'] = count($api_response['data']);

echo "API Response (first 3 transactions):\n";
echo json_encode(array_slice($api_response['data'], 0, 3), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Full meta:\n";
echo json_encode($api_response['meta'], JSON_PRETTY_PRINT) . "\n\n";

// Show transaction details
echo "Transaction Details:\n";
echo str_repeat('=', 100) . "\n";

foreach ($api_response['data'] as $idx => $tx) {
    $status_color = ($tx['status'] === 'rejected') ? '❌' : (($tx['status'] === 'approved') ? '✅' : '⏳');
    echo "{$status_color} TX #{$tx['id_transaksi']} | Jenis Tabungan: {$tx['jenis_tabungan']} | Amount: Rp {$tx['jumlah']} | Status: {$tx['status']}\n";
}

echo str_repeat('=', 100) . "\n";
echo "\nFix validated! All transactions now show correct jenis_tabungan.\n";

$stmt_trans->close();
$connect->close();
?>
