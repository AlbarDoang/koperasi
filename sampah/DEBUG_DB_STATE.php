<?php
/**
 * DEBUG: Check what's actually in the database
 */

$connect = new mysqli('localhost', 'root', '', 'tabungan');
if ($connect->connect_error) {
    die("Connection error: " . $connect->connect_error);
}

$test_user_hp = "08199060817";

echo "=== DATABASE STATE DEBUG ===\n\n";

// Check pengguna table
echo "--- Pengguna Table ---\n";
$res = $connect->query("SELECT id, no_hp, nama_lengkap, saldo FROM pengguna WHERE no_hp = '$test_user_hp' LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    print_r($row);
    $user_id = $row['id'];
    echo "User ID: $user_id\n\n";
} else {
    die("User not found!\n");
}

// Check mulai_nabung table
echo "--- Mulai_nabung entries for this user ---\n";
$res = $connect->query("SELECT id, nomor_hp, jenis_tabungan, jumlah, status, created_at FROM mulai_nabung WHERE nomor_hp = '$test_user_hp' ORDER BY created_at DESC LIMIT 5");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']}, Status: {$row['status']}, Jumlah: {$row['jumlah']}, Created: {$row['created_at']}\n";
    }
} else {
    echo "No mulai_nabung entries found\n";
}
echo "\n";

// Check transaksi table
echo "--- Transaction entries for this user ---\n";
$res = $connect->query("SELECT id, id_anggota, jenis_transaksi, jumlah, status, keterangan, tanggal FROM transaksi WHERE id_anggota = $user_id ORDER BY tanggal DESC LIMIT 10");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']}, Status: {$row['status']}, Jumlah: {$row['jumlah']}, Desc: {$row['keterangan']}, Date: {$row['tanggal']}\n";
    }
} else {
    echo "No transaction entries found\n";
}
echo "\n";

// Check API debug log
echo "--- Latest API Debug Log ---\n";
if (file_exists('/laragon/www/gas/gas_web/flutter_api/api_debug.log')) {
    $lines = file('/laragon/www/gas/gas_web/flutter_api/api_debug.log');
    $latest = array_slice($lines, -30);
    foreach ($latest as $line) {
        echo trim($line) . "\n";
    }
} else {
    echo "No API debug log found\n";
}

$connect->close();
?>
