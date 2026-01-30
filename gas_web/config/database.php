<?php
/**
 * Database Configuration - CENTRALIZED
 * File ini digunakan oleh semua sistem (Web & API untuk Mobile)
 * Hanya file ini yang boleh mengatur koneksi database
 */

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tabungan');

// Avoid long hangs when MySQL is down
ini_set('default_socket_timeout', 5);
ini_set('mysqli.connect_timeout', 5);

/**
 * Get MySQLi Connection (Procedural Style)
 * @return mysqli|false
 */
function getConnection() {
    static $con = null;
    
    if ($con === null) {
        $mysqli_init = mysqli_init();
        if ($mysqli_init) {
            mysqli_options($mysqli_init, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            if (!@mysqli_real_connect($mysqli_init, DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
                error_log('Database connection failed: ' . mysqli_connect_error());
                return false;
            }
            mysqli_set_charset($mysqli_init, "utf8");
            $con = $mysqli_init;
        }
    }
    
    return $con;
}

/**
 * Get MySQLi Connection (Object-Oriented Style)
 * @return mysqli|false
 */
function getConnectionOOP() {
    static $koneksi = null;
    
    if ($koneksi === null) {
        $koneksi = mysqli_init();
        if ($koneksi) {
            $koneksi->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            if (!$koneksi->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME)) {
                error_log('Database connection failed: ' . $koneksi->connect_error);
                return false;
            }
            $koneksi->set_charset("utf8");
        }
    }
    
    return $koneksi;
}

// Create connections for backward compatibility
$con = getConnection();
$koneksi = getConnectionOOP();
$connect = $koneksi; // untuk flutter_api yang menggunakan $connect

// Jangan output apapun di sini, biar API yang atur outputnya
// if (!$con || !$koneksi) {
//     die(json_encode([
//         'success' => false,
//         'message' => 'Tidak dapat terhubung ke database. Pastikan MySQL sudah berjalan dan database "tabungan" sudah dibuat.'
//     ]));
// }

