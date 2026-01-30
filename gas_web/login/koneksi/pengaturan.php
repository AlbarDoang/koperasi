<?php 
/**
 * Load Pengaturan dari Database
 * File ini hanya load data pengaturan, koneksi sudah ada di config.php
 */

// Pastikan koneksi sudah ada (dari config.php)
if (!isset($con)) {
    include 'config.php';
}

// Prefer new pengaturan_koperasi table first
$rpk = $con->query("SELECT * FROM pengaturan_koperasi LIMIT 1");
if ($rpk && $rpk->num_rows > 0) {
    $rp = $rpk->fetch_assoc();
    $id_set            = intval($rp['id']);
    $nama_sekolah      = trim($rp['nama'] ?? 'Koperasi GAS');
    $email_sekolah     = trim($rp['email'] ?? 'koperasigas@gmail.com');
    $alamat_sekolah    = trim($rp['alamat'] ?? '-');
    $singkatan_sekolah = strlen($nama_sekolah) > 0 ? substr($nama_sekolah,0,10) : 'GAS';
    $no_telp           = trim($rp['telepon'] ?? '-');
    $nama_jawab        = trim($rp['penanggung'] ?? 'Administrator');
    $logo_field        = trim($rp['logo'] ?? '');
    $logo_sekolah      = ($logo_field === '' || strtolower(basename($logo_field)) === 'logo.png') ? '../brand/logo.png' : $logo_field;
    $primary_color     = trim($rp['primary_color'] ?? '#FF6B00');
    $footer_text       = trim($rp['footer_text'] ?? '');
} else {
    // Fallback to legacy pengaturan table if koperasi settings not present
    $sql = $con->query("SELECT * FROM pengaturan LIMIT 1");

    if ($sql && $sql->num_rows > 0) {
        $row = $sql->fetch_assoc();
        
        $id_set            = $row['id_pengaturan'];
        $nama_sekolah      = $row['nama'];
        $email_sekolah     = $row['email'];
        $alamat_sekolah    = $row['alamat'];
        $singkatan_sekolah = $row['singkatan'];
        $no_telp           = $row['no_telepon'];
        $nama_jawab        = $row['penaggung'];
        $logo_field        = trim($row['logo'] ?? '');
        $logo_sekolah      = ($logo_field === '' || strtolower(basename($logo_field)) === 'logo.png') ? '../brand/logo.png' : $logo_field;
        $primary_color     = '#FF6B00';
        $footer_text       = ''; 
    } else {
        // Default values jika data tidak ada
        $id_set            = 0;
        $nama_sekolah      = 'Koperasi GAS';
        $email_sekolah     = 'koperasigas@gmail.com';
        $alamat_sekolah    = '-';
        $singkatan_sekolah = 'GAS';
        $no_telp           = '-';
        $nama_jawab        = 'Administrator';
        $logo_sekolah      = '../brand/logo.png';
        $primary_color     = '#FF6B00';
        $footer_text       = '';
    }
}
?>