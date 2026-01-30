<?php
/**
 * FILE: message_templates.php
 * TUJUAN: Template pesan WhatsApp profesional dan manajemen anti-spam
 * 
 * Fitur:
 * 1. Template pesan untuk 4 jenis notifikasi
 * 2. Validasi rate limiting (anti-spam 60 detik)
 * 3. Delay sebelum request ke Fonnte
 * 4. Styling pesan profesional, netral, dan aman dari deteksi spam
 */

// ============================================================================
// TEMPLATE PESAN - 4 JENIS NOTIFIKASI
// ============================================================================

/**
 * Template: OTP Aktivasi Akun
 * 
 * Gunakan untuk user yang baru mendaftar dan perlu verifikasi nomor HP
 * Bahasa: sopan, jelas, dan formal sesuai spek Koperasi GAS
 * 
 * @param string $nama_user Nama lengkap user (opsional)
 * @param string $kode_otp Kode OTP 6 digit
 * @param int $valid_minutes Durasi valid OTP (menit, default: 2)
 * @param string $app_name Nama aplikasi (default: "Koperasi GAS")
 * @return string Pesan siap kirim
 */
function getMessageOTPActivation($nama_user, $kode_otp, $valid_minutes = 2, $app_name = "Koperasi GAS") {
    $message = "Koperasi GAS\n";
    $message .= "Terima kasih telah mendaftar.\n";
    $message .= "Kode OTP untuk aktivasi akun Anda adalah:\n";
    $message .= "{$kode_otp}\n\n";
    $message .= "Kode ini bersifat rahasia dan berlaku selama {$valid_minutes} menit.\n";
    $message .= "Jangan bagikan kode ini kepada siapa pun.";
    
    return $message;
}

/**
 * Template: OTP Reset Password
 * 
 * Gunakan untuk user yang ingin reset password
 * Bahasa: netral, profesional, sesuai spek Koperasi GAS
 * 
 * @param string $nama_user Nama lengkap user (opsional)
 * @param string $kode_otp Kode OTP 6 digit
 * @param int $valid_minutes Durasi valid OTP (menit, default: 2)
 * @param string $app_name Nama aplikasi (default: "Koperasi GAS")
 * @return string Pesan siap kirim
 */
function getMessageOTPForgotPassword($nama_user, $kode_otp, $valid_minutes = 2, $app_name = "Koperasi GAS") {
    $message = "Koperasi GAS\n";
    $message .= "Kode OTP untuk reset password akun Anda adalah:\n";
    $message .= "{$kode_otp}\n\n";
    $message .= "Kode ini bersifat rahasia dan berlaku selama {$valid_minutes} menit.\n";
    $message .= "Jangan bagikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai admin.";
    
    return $message;
}

/**
 * Template: Akun Disetujui
 * 
 * Gunakan ketika admin menyetujui pendaftaran user
 * Bahasa: positif namun profesional, tidak berlebihan
 * 
 * @param string $nama_user Nama lengkap user
 * @param string $app_name Nama aplikasi (opsional, untuk backward compatibility)
 * @return string Pesan siap kirim
 */
function getMessageAccountApproved($nama_user, $app_name = "Tabungan") {
    $message = "Halo {$nama_user},\n\n";
    $message .= "Akun Anda telah berhasil disetujui dan diaktifkan.\n";
    $message .= "Silakan login dan atur PIN transaksi Anda untuk mulai menggunakan seluruh layanan kami.";
    
    return $message;
}

/**
 * Template: Akun Ditolak
 * 
 * Gunakan ketika admin menolak pendaftaran user
 * Bahasa: netral, sopan, dan tidak menyinggung
 * 
 * @param string $nama_user Nama lengkap user
 * @param string $alasan_penolakan Alasan penolakan (opsional)
 * @param string $app_name Nama aplikasi (opsional, untuk backward compatibility)
 * @return string Pesan siap kirim
 */
function getMessageAccountRejected($nama_user, $alasan_penolakan = null, $app_name = "Tabungan") {
    $message = "Halo {$nama_user},\n\n";
    $message .= "Terima kasih atas pendaftaran yang telah Anda lakukan.\n";
    $message .= "Setelah dilakukan peninjauan, akun Anda belum dapat kami aktifkan saat ini.\n\n";
    
    if (!empty($alasan_penolakan)) {
        $message .= "Alasan: {$alasan_penolakan}\n\n";
    }
    
    $message .= "Silakan periksa kembali data yang dikirim atau hubungi admin jika diperlukan.";
    
    return $message;
}

// ============================================================================
// ANTI-SPAM: VALIDASI RATE LIMITING
// ============================================================================

/**
 * Validasi: Cegah OTP request terlalu cepat (maksimal 1x per 60 detik)
 * 
 * Strategi:
 * - Gunakan database lock atau file temporary untuk tracking
 * - Jika request terlalu cepat: return error, jangan kirim WhatsApp
 * - Log setiap attempt untuk audit
 * 
 * @param string $no_hp Nomor HP user (format: 62xxx)
 * @param int $min_seconds Minimal detik antar request (default: 60)
 * @return array ['allowed' => bool, 'message' => string, 'retry_after' => int (seconds)]
 */
function checkRateLimitOTP($no_hp, $min_seconds = 60) {
    // Validasi nomor HP
    if (empty($no_hp)) {
        return ['allowed' => false, 'message' => 'Nomor HP tidak valid'];
    }
    
    // Gunakan file temporary untuk rate limiting (simple & reliable)
    $rate_limit_dir = __DIR__ . '/.rate_limit';
    if (!is_dir($rate_limit_dir)) {
        @mkdir($rate_limit_dir, 0755, true);
    }
    
    // Hash nomor HP untuk filename (keamanan)
    $phone_hash = md5($no_hp);
    $lock_file = $rate_limit_dir . '/' . $phone_hash . '.lock';
    
    // Cek file lock
    if (file_exists($lock_file)) {
        $last_request_time = (int)file_get_contents($lock_file);
        $current_time = time();
        $time_elapsed = $current_time - $last_request_time;
        
        if ($time_elapsed < $min_seconds) {
            $retry_after = $min_seconds - $time_elapsed;
            @file_put_contents(__DIR__ . '/.rate_limit_log.txt', 
                date('Y-m-d H:i:s') . " | RATE_LIMITED phone={$no_hp} elapsed={$time_elapsed}s retry_after={$retry_after}s\n", 
                FILE_APPEND);
            
            return [
                'allowed' => false, 
                'message' => "Terlalu banyak permintaan. Silakan coba lagi dalam {$retry_after} detik.",
                'retry_after' => $retry_after
            ];
        }
    }
    
    // Update lock file (timestamp sekarang)
    file_put_contents($lock_file, time());
    // Cleanup: hapus file lock setelah min_seconds * 2 untuk efisiensi
    // Bisa ditambahkan cron job untuk cleanup berkala
    
    return ['allowed' => true, 'message' => 'OTP request allowed'];
}

// ============================================================================
// DELAY SEBELUM REQUEST KE FONNTE
// ============================================================================

/**
 * Delay kecil sebelum request ke Fonnte API
 * 
 * Tujuan: Mengurangi peak load dan mencegah throttling dari Fonnte
 * Delay: 1-2 detik (disesuaikan berdasarkan kondisi server)
 * 
 * @param int $delay_seconds Delay dalam detik (default: 1)
 * @return void
 */
function addDelayBeforeFontneRequest($delay_seconds = 1) {
    if ($delay_seconds > 0 && $delay_seconds <= 5) {
        usleep($delay_seconds * 1000000); // Convert detik ke microseconds
    }
}

// ============================================================================
// HELPER: CLEANUP RATE LIMIT CACHE
// ============================================================================

/**
 * Cleanup: Hapus file lock yang sudah expired
 * 
 * Jalankan: Via cron job setiap jam
 * Command: php -r "require_once 'gas_web/message_templates.php'; cleanupRateLimitCache();"
 * 
 * @param int $max_age_seconds File yang lebih lama dari ini akan dihapus (default: 1 jam)
 * @return int Jumlah file yang dihapus
 */
function cleanupRateLimitCache($max_age_seconds = 3600) {
    $rate_limit_dir = __DIR__ . '/.rate_limit';
    if (!is_dir($rate_limit_dir)) {
        return 0;
    }
    
    $current_time = time();
    $deleted_count = 0;
    
    foreach (glob($rate_limit_dir . '/*.lock') as $lock_file) {
        $file_time = filemtime($lock_file);
        if ($current_time - $file_time > $max_age_seconds) {
            if (unlink($lock_file)) {
                $deleted_count++;
            }
        }
    }
    
    return $deleted_count;
}

?>
