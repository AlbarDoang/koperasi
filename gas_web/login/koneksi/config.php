<?php

/**
 * Login System Configuration
 * Menggunakan database terpusat dari /config/database.php
 */

// Include centralized database configuration first
require_once dirname(dirname(__DIR__)) . '/config/database.php';

// Include db.php untuk backward compatibility
include 'db.php';

// Variabel $con dan $koneksi sudah tersedia dari database.php
// Tidak perlu membuat koneksi baru lagi

// URL Config untuk redirect - Auto-detect Path
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

// Auto-detect: dapatkan path relatif dari DOCUMENT_ROOT
// File ini ada di: /login/koneksi/config.php
// Kita naik 2 level untuk sampai ke folder /login/
$current_dir = dirname(dirname(__FILE__)); // naik ke /login/
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']); // Normalize
$current_dir_normalized = str_replace('\\', '/', $current_dir); // Normalize
$relative_path = str_replace($doc_root, '', $current_dir_normalized);
$url = $protocol . '://' . $host . $relative_path;
$url = rtrim($url, '/');

// Base path untuk include files
$base_path = $current_dir . '/';
