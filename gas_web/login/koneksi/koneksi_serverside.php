<?php
/**
 * Koneksi untuk server-side processing (DataTables)
 * Menggunakan database terpusat dari /config/database.php
 */
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Format untuk library yang memerlukan array config
$gaSql['user']       = DB_USER;
$gaSql['password']   = DB_PASS;
$gaSql['db']         = DB_NAME;
$gaSql['server']     = DB_HOST;
?>