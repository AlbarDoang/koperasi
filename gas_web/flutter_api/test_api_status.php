<?php
/**
 * Test: Verify API returns correct updated status
 */
require __DIR__ . '/connection.php';

// Get most recent transaction
$sql = "SELECT id_transaksi, id_pengguna, status, keterangan, tanggal FROM transaksi ORDER BY tanggal DESC LIMIT 1";
$result = $connect->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $id_transaksi = $row['id_transaksi'];
    $id_pengguna = $row['id_pengguna'];
    $status = $row['status'];
    
    echo "Latest transaction:\n";
    echo "ID: $id_transaksi\n";
    echo "User: $id_pengguna\n";
    echo "Status: $status\n";
    echo "Keterangan: {$row['keterangan']}\n";
    echo "Tanggal: {$row['tanggal']}\n\n";
    
    // Now test API response
    echo "Testing API response for this user:\n";
    $api_sql = "SELECT id_transaksi, jenis_transaksi, jumlah, status, keterangan FROM transaksi WHERE id_pengguna = ? ORDER BY tanggal DESC LIMIT 5";
    $stmt = $connect->prepare($api_sql);
    $stmt->bind_param('i', $id_pengguna);
    $stmt->execute();
    $api_result = $stmt->get_result();
    
    $count = 0;
    while ($txn = $api_result->fetch_assoc()) {
        $count++;
        echo "  $count) ID={$txn['id_transaksi']} Status={$txn['status']} Amount={$txn['jumlah']} Keterangan={$txn['keterangan']}\n";
    }
}

$connect->close();
?>

