<?php
/**
 * Admin Middleware
 * Fungsi: Hanya Admin yang bisa akses halaman ini
 */

require_once __DIR__ . '/Auth.php';

class AdminMiddleware {
    
    public static function handle() {
        // Cek apakah sudah login
        Auth::check();
        
        // Cek apakah user adalah admin
        if (!Auth::isAdmin()) {
            // Jika bukan admin, redirect ke dashboard petugas
            $_SESSION['error'] = 'Akses ditolak! Anda bukan Admin.';
            header('Location: /tabungan_gas/login/petugas/dashboard/');
            exit();
        }
        
        return true;
    }
}
