<?php
/**
 * Koneksi untuk select queries
 * Menggunakan database terpusat dari /config/database.php
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Variabel $connect untuk backward compatibility
$connect = $con;
?>