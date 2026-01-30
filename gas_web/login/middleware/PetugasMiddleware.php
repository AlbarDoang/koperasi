<?php
/**
 * Petugas Middleware
 * Fungsi: Hanya Petugas yang bisa akses halaman ini
 */

require_once __DIR__ . '/Auth.php';

class PetugasMiddleware {
    
    public static function handle() {
        // Cek apakah sudah login
        Auth::check();
        
        // Cek apakah user adalah petugas
        if (!Auth::isPetugas()) {
            // Jika bukan petugas, redirect ke dashboard admin
            $_SESSION['error'] = 'Akses ditolak! Anda bukan Petugas.';
            header('Location: /tabungan_gas/login/admin/dashboard/');
            exit();
        }
        
        return true;
    }
}
