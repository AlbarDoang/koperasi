<?php
// config/db.php
// Simple procedural mysqli connection wrapper
declare(strict_types=1);

// Enable errors for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = ''; // sesuaikan
$dbname = 'tabungan'; // ganti sesuai database kamu

// Connect
$con = mysqli_connect($host, $user, $pass, $dbname);

// Check connection
if (!$con) {
    die(json_encode([
        'status' => false,
        'message' => 'Koneksi database gagal',
        'error' => mysqli_connect_error()
    ]));
}
