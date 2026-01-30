<?php
/**
 * Koneksi untuk export data
 * Menggunakan database terpusat dari /config/database.php
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Format untuk library export yang memerlukan variabel individual
$db_host = DB_HOST;
$db_port = '3306';
$db_name = DB_NAME;
$db_user = DB_USER;
$db_pass = DB_PASS;
?>