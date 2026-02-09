<?php
$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'tabungan';
$connect = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

// Check all tabungan_masuk entries with full details
$result = $connect->query("SELECT id, id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at FROM tabungan_masuk ORDER BY id DESC LIMIT 10");
echo "=== TABUNGAN_MASUK (Last 10) ===\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} | User: {$row['id_pengguna']} | Jenis: {$row['id_jenis_tabungan']} | Jumlah: {$row['jumlah']} | Ket: {$row['keterangan']}\n";
}

// Check what happens when response query is run
echo "\n=== RESPONSE QUERY RESULT (BUGGY) ===\n";
$result2 = $connect->query("SELECT jumlah FROM tabungan_masuk WHERE id_pengguna = 1 AND id_jenis_tabungan = 2 LIMIT 1");
$row2 = $result2->fetch_assoc();
echo "LIMIT 1 Result: " . $row2['jumlah'] . "\n";

// Show what should be queried instead
echo "\n=== CORRECT RESPONSE QUERY (SUM) ===\n";
$result3 = $connect->query("SELECT COALESCE(SUM(jumlah),0) as total FROM tabungan_masuk WHERE id_pengguna = 1 AND id_jenis_tabungan = 2");
$row3 = $result3->fetch_assoc();
echo "SUM Result: " . $row3['total'] . "\n";

$connect->close();
?>
