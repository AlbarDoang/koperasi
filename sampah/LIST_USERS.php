<?php
$connect = new mysqli('localhost', 'root', '', 'tabungan');
if ($connect->connect_error) {
    die("Connection error: " . $connect->connect_error);
}

echo "=== ALL USERS IN DATABASE ===\n";
$res = $connect->query("SELECT id, no_hp, nama_lengkap FROM pengguna LIMIT 10");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']}, No HP: {$row['no_hp']}, Nama: {$row['nama_lengkap']}\n";
    }
} else {
    echo "No users found\n";
}

$connect->close();
?>
