<?php
/**
 * Authentication Middleware
 * Fungsi: Memastikan user sudah login sebelum mengakses halaman
 */

class Auth {
    
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Cek apakah user sudah login
        if (!isset($_SESSION['id_user']) || !isset($_SESSION['akses'])) {
            // Jika belum login, redirect ke halaman login
            $_SESSION['error'] = 'Silakan login terlebih dahulu!';
            header('Location: /tabungan_gas/login/');
            exit();
        }
        
        return true;
    }
    
    public static function user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Return data user yang sedang login
        if (isset($_SESSION['id_user'])) {
            return [
                'id' => $_SESSION['id_user'] ?? '',
                'nama' => $_SESSION['nama_user'] ?? '',
                'akses' => $_SESSION['akses'] ?? '',
                'foto' => $_SESSION['nama_foto'] ?? 'default.png'
            ];
        }
        return null;
    }
    
    public static function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['akses']) && $_SESSION['akses'] === 'admin';
    }
    
    public static function isPetugas() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['akses']) && $_SESSION['akses'] === 'petugas';
    }
    
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
        header('Location: /tabungan_gas/login/');
        exit();
    }
}
