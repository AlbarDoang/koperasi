<?php 
/**
 * Database Configuration - REDIRECT to Centralized Config
 * File ini hanya untuk backward compatibility
 * Koneksi sebenarnya ada di /config/database.php
 */

// Include centralized database configuration
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Untuk backward compatibility dengan code lama
$database = DB_NAME;
?>