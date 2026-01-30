<?php
/**
 * FILE: verifikasi_otp.php
 * DESKRIPSI: Halaman verifikasi kode OTP
 * 
 * ALUR:
 * 1. Terima input no_hp (dari URL GET parameter)
 * 2. Terima input kode_otp (dari form POST)
 * 3. Validasi OTP: cek di tabel otp_codes WHERE no_wa=?, kode_otp=?, status='belum', expired_at>NOW()
 * 4. Jika OTP valid:
 *    - Dalam TRANSACTION:
 *      a. Update otp_codes SET status='sudah' WHERE id=?
 *      b. Update pengguna SET status_akun='pending' WHERE no_hp=? (menunggu persetujuan admin)
 *    - Setelah ini redirect ke halaman login (jangan langsung ke set_pin)
 * 5. Jika OTP invalid/expired:
 *    - Tampilkan error
 *    - Tetap di halaman verifikasi_otp.php
 *    - status_akun tetap PENDING
 * 
 * PENTING: 
 * - Hanya file ini yang boleh mengubah status_akun menjadi 'pending' setelah OTP divalidasi
 * - OTP hanya diverifikasi DI SINI, bukan di file lain
 * - Jika OTP salah/expired, JANGAN sampai lanjut ke set_pin.php
 */

session_start();

// Include koneksi database
require_once __DIR__ . '/config/database.php';

// Include helper function OTP
require_once __DIR__ . '/otp_helper.php';

// ============================================================================
// BAGIAN 1: HANDLE POST REQUEST (Verifikasi OTP)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil no_hp dari GET parameter
    $no_hp = isset($_GET['no_hp']) ? trim($_GET['no_hp']) : '';
    
    // Ambil kode OTP dari form
    $kode_otp = isset($_POST['kode_otp']) ? trim($_POST['kode_otp']) : '';
    
    // Validasi: no_hp wajib ada
    if (empty($no_hp)) {
        $_SESSION['error'] = 'Parameter nomor HP tidak valid';
        header('Location: aktivasi_akun.php');
        exit();
    }
    
    // Validasi: kode OTP wajib diisi
    if (empty($kode_otp)) {
        $_SESSION['error'] = 'Kode OTP wajib diisi';
        header('Location: verifikasi_otp.php?no_hp=' . urlencode($no_hp));
        exit();
    }
    
    // Validasi: kode OTP harus 6 digit
    if (!preg_match('/^\d{6}$/', $kode_otp)) {
        $_SESSION['error'] = 'Kode OTP harus berupa 6 digit angka';
        header('Location: verifikasi_otp.php?no_hp=' . urlencode($no_hp));
        exit();
    }
    
    // ========================================================================
    // STEP 1: Cek OTP di tabel otp_codes (sesuai struktur)
    // - WHERE no_wa = ?
    // - AND kode_otp = ?
    // - AND status = 'belum' (belum digunakan)
    // - AND expired_at > NOW() (belum expired)
    // - ORDER BY created_at DESC LIMIT 1 (ambil yang paling baru)
    // ========================================================================
    // Note: do not rely on DB's NOW() comparison due to possible timezone drift between PHP and DB.
    // Fetch the latest OTP matching no_wa and kode_otp, then compare expiry in PHP.
    $sql_check_otp = "SELECT id, no_wa, kode_otp, expired_at 
                      FROM otp_codes 
                      WHERE no_wa = ? AND kode_otp = ? AND status = 'belum' 
                      ORDER BY created_at DESC LIMIT 1";
    
    $stmt_check = $con->prepare($sql_check_otp);
    
    if (!$stmt_check) {
        $_SESSION['error'] = 'Kesalahan database: ' . $con->error;
        header('Location: verifikasi_otp.php?no_hp=' . urlencode($no_hp));
        exit();
    }
    
    // Normalize the phone into no_wa format (e.g., 62xxx) to match otp_codes.no_wa
    $no_wa = preg_replace('/[^0-9]/', '', $no_hp);
    if (substr($no_wa, 0, 1) === '0') {
        $no_wa = '62' . substr($no_wa, 1);
    }
    if (substr($no_wa, 0, 2) !== '62') {
        $no_wa = '62' . $no_wa;
    }

    $stmt_check->bind_param('ss', $no_wa, $kode_otp);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    // Jika OTP tidak ditemukan
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        $_SESSION['error'] = 'Kode OTP salah atau sudah kadaluarsa. Silakan coba lagi atau minta OTP baru.';
        header('Location: verifikasi_otp.php?no_hp=' . urlencode($no_hp));
        exit();
    }
    
    $otp_record = $result_check->fetch_assoc();
    $otp_id = $otp_record['id'];

    // Verify expiry using PHP time to avoid timezone mismatch issues
    $now_ts = time();
    $expired_ts = strtotime($otp_record['expired_at']);
    if ($expired_ts === false || $expired_ts < $now_ts) {
        $stmt_check->close();
        $_SESSION['error'] = 'Kode OTP salah atau sudah kadaluarsa. Silakan coba lagi atau minta OTP baru.';
        header('Location: verifikasi_otp.php?no_hp=' . urlencode($no_hp));
        exit();
    }

    $stmt_check->close();
    
    // ========================================================================
    // STEP 2: Mulai TRANSACTION untuk atomicity
    // ========================================================================
    $con->begin_transaction();
    
    try {
        // ====================================================================
        // STEP 2a: Tandai OTP sebagai sudah digunakan (used = 1)
        // ====================================================================
        $sql_mark_used = "UPDATE otp_codes 
                  SET status = 'sudah' 
                  WHERE id = ?";
        
        $stmt_mark = $con->prepare($sql_mark_used);
        if (!$stmt_mark) {
            throw new Exception('Kesalahan query OTP: ' . $con->error);
        }
        
        $stmt_mark->bind_param('i', $otp_id);
        if (!$stmt_mark->execute()) {
            throw new Exception('Gagal menandai OTP: ' . $stmt_mark->error);
        }
        $stmt_mark->close();
        
        // ====================================================================
        // STEP 2b: Decide whether to mark account as PENDING (require admin) or AUTO-APPROVE
        // ====================================================================
        $require_admin_verification = true;
        $rtab = $con->query("SHOW TABLES LIKE 'pengaturan_koperasi'");
        if ($rtab && $rtab->num_rows > 0) {
            $rpk = $con->query("SELECT require_admin_verification FROM pengaturan_koperasi LIMIT 1");
            if ($rpk && $rpk->num_rows > 0) {
                $cfg = $rpk->fetch_assoc(); $require_admin_verification = isset($cfg['require_admin_verification']) ? (bool)$cfg['require_admin_verification'] : true;
            }
        }

        // Some installations may not have status_verifikasi column
        $has_verif_col = $con->query("SHOW COLUMNS FROM pengguna LIKE 'status_verifikasi'");

        if ($require_admin_verification) {
            // Mark as pending for admin approval
            if ($has_verif_col && $has_verif_col->num_rows > 0) {
                $sql_verify = "UPDATE pengguna SET status_akun = 'pending', status_verifikasi = 'pending' WHERE no_hp = ?";
            } else {
                $sql_verify = "UPDATE pengguna SET status_akun = 'pending' WHERE no_hp = ?";
            }
        } else {
            // Auto-approve user to 'approved'
            if ($has_verif_col && $has_verif_col->num_rows > 0) {
                $sql_verify = "UPDATE pengguna SET status_akun = 'approved', status_verifikasi = 'approved' WHERE no_hp = ?";
            } else {
                $sql_verify = "UPDATE pengguna SET status_akun = 'approved' WHERE no_hp = ?";
            }
        }
        
        $stmt_verify = $con->prepare($sql_verify);
        if (!$stmt_verify) {
            throw new Exception('Kesalahan query pengguna: ' . $con->error);
        }
        
        // Bind using normalized local phone (08...) for pengguna.no_hp storage
        require_once __DIR__ . '/flutter_api/helpers.php';
        $no_hp_local = sanitizePhone($no_wa);
        if (empty($no_hp_local)) {
            throw new Exception('Nomor HP tidak valid saat update pengguna');
        }
        $stmt_verify->bind_param('s', $no_hp_local);
        if (!$stmt_verify->execute()) {
            throw new Exception('Gagal menandai akun untuk verifikasi/approved: ' . $stmt_verify->error);
        }
        
        // Cek apakah ada row yang ter-update
        if ($stmt_verify->affected_rows === 0) {
            throw new Exception('Nomor HP tidak ditemukan di database');
        }
        
        $stmt_verify->close();
        
        // ====================================================================
        // STEP 3: Commit transaction
        // ====================================================================
        $con->commit();
        
        // Inform user and redirect to login (do NOT redirect to set PIN)
        // As an additional compatibility measure, include a short success key in the redirect
        // so clients that don't carry session cookies still see the message.
        $__v_log = date('c') . " [VERIF_OK] user_no_wa={$no_wa} remote_addr={$_SERVER['REMOTE_ADDR']} referer=" . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'NONE') . " ua=" . (isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'],0,200) : 'NONE') . " session_id=" . session_id() . " location=login/?success=aktivasi\n";
        @file_put_contents(__DIR__ . '/tmp_verifikasi_redirect.log', $__v_log, FILE_APPEND);
        $_SESSION['success'] = 'Pengajuan aktivasi akun diterima, silakan tunggu persetujuan admin.';
        header('Location: login/?success=aktivasi');
        exit();
        
    } catch (Exception $e) {
        // Jika ada error, rollback transaction
        $con->rollback();
        $_SESSION['error'] = 'Gagal memverifikasi akun: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header('Location: verifikasi_otp.php?no_hp=' . urlencode($no_hp));
        exit();
    }
}

// ============================================================================
// BAGIAN 2: HANDLE GET REQUEST (Tampilkan Form)
// ============================================================================

// Ambil no_hp dari URL parameter
$no_hp = isset($_GET['no_hp']) ? htmlspecialchars($_GET['no_hp'], ENT_QUOTES, 'UTF-8') : '';

// Jika no_hp tidak ada, redirect ke aktivasi_akun
if (empty($no_hp)) {
    header('Location: aktivasi_akun.php');
    exit();
}

// ========================================================================
// Ambil nama pengguna untuk greeting
// ========================================================================
$nama_lengkap = '';
$sql_user = "SELECT nama_lengkap FROM pengguna WHERE no_hp = ? LIMIT 1";
$stmt_user = $con->prepare($sql_user);

if ($stmt_user) {
    require_once __DIR__ . '/flutter_api/helpers.php';
    $no_hp_local = sanitizePhone($no_hp);
    $stmt_user->bind_param('s', $no_hp_local);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    
    if ($result_user->num_rows > 0) {
        $user_data = $result_user->fetch_assoc();
        $nama_lengkap = $user_data['nama_lengkap'];
    }
    
    $stmt_user->close();
}

// Ambil pesan error/success dari session
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';

// Hapus session setelah ditampilkan (prevent duplicate message)
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 400px;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #999;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .greeting {
            text-align: center;
            background: #f0f4ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .greeting strong {
            color: #667eea;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
            letter-spacing: 8px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .btn:active {
            background: #485fb8;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
        
        .alert-success {
            background-color: #efe;
            border: 1px solid #cfc;
            color: #3c3;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: #999;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>✅ Verifikasi OTP</h1>
            <p>Masukkan kode OTP yang telah dikirim</p>
        </div>
        
        <!-- Greeting dengan nama pengguna -->
        <?php if (!empty($nama_lengkap)): ?>
            <div class="greeting">
                Halo <strong><?php echo htmlspecialchars($nama_lengkap, ENT_QUOTES, 'UTF-8'); ?></strong>, silakan masukkan kode OTP Anda.
            </div>
        <?php endif; ?>
        
        <!-- Tampilkan pesan error jika ada -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <strong>❌ Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <!-- Tampilkan pesan success jika ada -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <strong>✓ Sukses:</strong> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <!-- Form input OTP -->
        <form method="POST" action="verifikasi_otp.php?no_hp=<?php echo urlencode($no_hp); ?>">
            <div class="form-group">
                <label for="kode_otp">Kode OTP (6 Digit)</label>
                <input 
                    type="text" 
                    id="kode_otp" 
                    name="kode_otp" 
                    placeholder="000000"
                    inputmode="numeric"
                    maxlength="6"
                    pattern="\d{6}"
                    required
                    autofocus
                >
            </div>
            
            <button type="submit" class="btn">
                ✓ Verifikasi OTP
            </button>
        </form>
        
        <!-- Footer -->
        <div class="footer">
            <p><a href="aktivasi_akun.php">Minta kode OTP baru</a></p>
        </div>
    </div>
</body>
</html>
