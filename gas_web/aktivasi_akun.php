<?php
/**
 * FILE: aktivasi_akun.php
 * TUJUAN: Endpoint untuk request OTP melalui WhatsApp
 * 
 * ALUR:
 * 1. User input nomor HP
 * 2. Validasi nomor HP terdaftar di tabel pengguna
 * 3. Generate OTP 6 digit random
 * 4. Simpan ke tabel otp_codes (kolom: no_wa, kode_otp, expired_at, status='belum', created_at)
 * 5. Kirim OTP ke WhatsApp via Fonnte API
 * 6. Tampilkan pesan sukses dan redirect ke verifikasi_otp.php
 * 
 * PENTING: 
 * - TIDAK mengubah status_akun sama sekali!
 * - Status hanya berubah di verifikasi_otp.php setelah OTP diverifikasi dengan benar
 * - File ini HANYA untuk: validasi HP → generate OTP → simpan DB → kirim WhatsApp
 */

session_start();

// Koneksi database
require_once __DIR__ . '/config/database.php';

// Include centralized Fonnte configuration
require_once __DIR__ . '/config/fonnte_constants.php';

// Include helper function OTP
require_once __DIR__ . '/otp_helper.php';

// Default messages
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_hp = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : '';

    // ====================================================================
    // STEP 1: Validasi input nomor HP
    // ====================================================================
    // Validasi: tidak kosong
    if ($no_hp === '') {
        $error = 'Nomor HP wajib diisi';
    }

    // Validasi: hanya angka dan minimal 10 digit (sebelum normalize)
    if (empty($error) && !preg_match('/^\d{10,}$/', $no_hp)) {
        $error = 'Nomor HP harus berupa angka minimal 10 digit';
    }

    // Prepare canonical forms: local (08...) for DB lookup and international (62...) for OTP
    if (empty($error)) {
        $no_hp_local = sanitizePhone($no_hp);
        if (empty($no_hp_local)) {
            $error = 'Format nomor HP tidak valid. Gunakan format: 081xxx atau 628xxx';
        } else {
            $no_hp_int = phone_to_international62($no_hp);
            if ($no_hp_int === false) {
                $error = 'Format nomor HP tidak valid';
            }
        }
    }

    // ====================================================================
    // STEP 2: Cek apakah nomor HP terdaftar di tabel pengguna (DB menyimpan 08...)
    // ====================================================================
    if (empty($error)) {
        $sql_check = "SELECT id, status_akun FROM pengguna WHERE no_hp = ? LIMIT 1";
        $stmt_check = $con->prepare($sql_check);
        if (!$stmt_check) {
            $error = 'Kesalahan database: ' . $con->error;
        } else {
            $stmt_check->bind_param('s', $no_hp_local);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            
            // Nomor HP tidak ditemukan
            if ($result->num_rows === 0) {
                $error = 'Nomor HP tidak terdaftar. Silakan daftar terlebih dahulu.';
            } else {
                // Ambil status akun (untuk informasi saja, tidak blokir)
                $user = $result->fetch_assoc();
                $status_akun_norm = strtolower(trim($user['status_akun'] ?? ''));
                
                // Hanya informasi jika sudah disetujui (approved)
                // Tapi tetap biarkan kirim OTP lagi jika user mau
                if ($status_akun_norm === 'approved') {
                    // Tidak error, hanya info - user bisa request OTP lagi
                    // Ini untuk case: user lupa PIN atau ingin reset
                }
            }
            $stmt_check->close();
        }
    }

    // ====================================================================
    // STEP 3: Generate OTP dan simpan ke tabel otp_codes
    // ====================================================================
    if (empty($error)) {
        // Generate OTP: 6 digit random
        $kode_otp = generateOTP();

        // Hitung waktu kadaluarsa: NOW + 2 menit
        $expired_at = date('Y-m-d H:i:s', strtotime('+2 minutes'));

        // Simpan ke tabel otp_codes sesuai struktur: id, no_wa, kode_otp, expired_at, status, created_at
        // Use international form for OTP table and sending
        $sql_insert = "INSERT INTO otp_codes (no_wa, kode_otp, expired_at, status, created_at) VALUES (?, ?, ?, 'belum', NOW())";
        $stmt_insert = $con->prepare($sql_insert);
        if (!$stmt_insert) {
            $error = 'Kesalahan saat menyiapkan query: ' . $con->error;
        } else {
            $stmt_insert->bind_param('sss', $no_hp_int, $kode_otp, $expired_at);
            if (!$stmt_insert->execute()) {
                $error = 'Gagal menyimpan OTP: ' . $stmt_insert->error;
            } else {
                // ============================================================
                // STEP 4: Kirim OTP ke WhatsApp via Fonnte API
                // ============================================================
                $fonnte_result = sendOTPViaFonnte($no_hp_int, $kode_otp, FONNTE_TOKEN);
                
                // Cek apakah pengiriman berhasil
                if ($fonnte_result['success']) {
                    $success = 'Kode OTP telah dikirim melalui WhatsApp ke ' . htmlspecialchars($no_hp_int, ENT_QUOTES, 'UTF-8') . '.';
                } else {
                    // Jika pengiriman gagal, hapus OTP dari database (otp_codes)
                    $sql_delete = "DELETE FROM otp_codes WHERE no_wa = ? AND kode_otp = ?";
                    $stmt_delete = $con->prepare($sql_delete);
                    if ($stmt_delete) {
                        $stmt_delete->bind_param('ss', $no_hp_int, $kode_otp);
                        $stmt_delete->execute();
                        $stmt_delete->close();
                    }
                    
                    $error = $fonnte_result['message'];
                }
            }
            $stmt_insert->close();
        }
    }

    // ====================================================================
    // STEP 5: Tampilkan hasil dan redirect jika sukses
    // ====================================================================
    if (!empty($error)) {
        // Show form again with error (handled in HTML below)
    } else if (!empty($success)) {
        // DEBUG: Tampilkan OTP di browser untuk testing (HAPUS DI PRODUCTION)
        echo "<div style='background:#fff3cd;padding:15px;margin:10px 0;border:1px solid #ffc107;border-radius:4px;'>";
        echo "<strong>🐛 DEBUG OTP (HAPUS DI PRODUCTION):</strong> " . htmlspecialchars($kode_otp, ENT_QUOTES, 'UTF-8');
        echo "</div>";
        
        echo "<div style='background:#d4edda;padding:15px;margin:10px 0;border:1px solid #28a745;border-radius:4px;'>";
        echo "✓ " . htmlspecialchars($success, ENT_QUOTES, 'UTF-8');
        echo "</div>";

        // Redirect ke verifikasi_otp.php setelah 2 detik
        $redirect = 'verifikasi_otp.php?no_hp=' . urlencode($no_hp);
        header('Refresh: 2; URL=' . $redirect);
        exit();
    }
}

// Tampilkan form (GET atau POST dengan error)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Aktivasi Akun - Kirim OTP</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;background:#f2f4f8;padding:20px}
        .card{max-width:420px;margin:30px auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 6px 24px rgba(0,0,0,.08)}
        h1{font-size:20px;margin-bottom:8px}
        p.lead{color:#555;margin-bottom:18px}
        .form-group{margin-bottom:12px}
        label{display:block;margin-bottom:6px;font-weight:600}
        input[type=tel]{width:100%;padding:10px;border:1px solid #d7dce6;border-radius:6px}
        button{display:block;width:100%;padding:10px;background:#0b5ed7;color:#fff;border:none;border-radius:6px;cursor:pointer}
        .alert{padding:10px;border-radius:6px;margin-bottom:12px}
        .alert-error{background:#fff2f2;border:1px solid #ffd6d6;color:#8b1e1e}
        .alert-success{background:#e9f9ee;border:1px solid #c6efd3;color:#06693a}
    </style>
</head>
<body>
    <div class="card">
        <h1>Aktivasi Akun</h1>
        <p class="lead">Masukkan nomor HP Anda (contoh: 62812345678). Sistem akan mengirimkan kode OTP.</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="post" action="aktivasi_akun.php">
            <div class="form-group">
                <label for="no_hp">Nomor HP</label>
                <input id="no_hp" name="no_hp" type="tel" placeholder="62812345678" value="<?php echo isset($no_hp) ? htmlspecialchars($no_hp, ENT_QUOTES, 'UTF-8') : ''; ?>" minlength="10" required>
            </div>
            <button type="submit">Kirim Kode OTP</button>
        </form>
    </div>
</body>
</html>
