<?php
$host = "localhost";
$user = "root";
$pass = ""; // biasanya kosong kalau XAMPP/Laragon
$db   = "tabungan"; // ganti dengan nama database kamu

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode([
        "success" => false,
        "message" => "Koneksi database gagal: " . $conn->connect_error
    ]));
}
?>