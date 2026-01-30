<?php
header('Content-Type: application/json; charset=utf-8');
include 'connection.php';

// For testing only - show password hashes to find valid test account
$result = $connect->query("SELECT id, no_hp, nama_lengkap, kata_sandi FROM pengguna LIMIT 5");
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        'id' => $row['id'],
        'no_hp' => $row['no_hp'],
        'nama' => $row['nama_lengkap'],
        'kata_sandi_hash' => substr($row['kata_sandi'], 0, 20) . '...', // Show first 20 chars
        'hash_type' => (strlen($row['kata_sandi']) > 40) ? 'bcrypt/argon2' : 'old_hash'
    ];
}

echo json_encode($users, JSON_PRETTY_PRINT);
