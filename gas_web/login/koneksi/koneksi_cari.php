<?php
/**
 * Koneksi untuk fitur pencarian
 * Menggunakan database terpusat dari /config/database.php
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Variabel $database untuk OOP mysqli sudah tersedia dari config
// Gunakan $koneksi untuk object-oriented style
$database = $koneksi;
?>