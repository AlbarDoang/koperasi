<?php
/**
 * FILE: set_pin.php
 * FUNGSI: Halaman untuk user set PIN (6 digit) setelah akun diverifikasi
 * ALUR:
 * 1. User input PIN 6 digit
 * 2. Konfirmasi PIN
 * 3. Validasi kedua PIN sama
 * 4. Update tabel pengguna kolom 'pin' dengan hash PIN
 * 5. Redirect ke login.php dengan pesan sukses
 */

session_start();

require_once 'flutter_api/connection.php';

// Debugging: log every access to set_pin.php to trace unexpected redirects
$__sp_raw_no_hp = isset($_GET['no_hp']) ? $_GET['no_hp'] : '';
$__sp_log = date('c') . " [ACCESS] method={$_SERVER['REQUEST_METHOD']} raw_no_hp={$__sp_raw_no_hp} remote_addr={$_SERVER['REMOTE_ADDR']} referer=" . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'NONE') . " ua=" . (isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'],0,200) : 'NONE') . " session_id=" . session_id() . "\n";
@file_put_contents(__DIR__ . '/tmp_set_pin_access.log', $__sp_log, FILE_APPEND);


// Validasi parameter no_hp dari URL
$no_hp = isset($_GET['no_hp']) ? trim($_GET['no_hp']) : '';
if (empty($no_hp)) {
    $_SESSION['error'] = 'Nomor HP tidak valid. Silakan mulai dari awal.';
    // Redirect to the login page so users authenticate first
    header('Location: login/');
    exit();
}

// Normalize phone lookup: use centralized helper and support both local and international for legacy rows
$no_hp_orig = $no_hp;
require_once __DIR__ . '/flutter_api/helpers.php';
$no_hp_local = sanitizePhone($no_hp_orig); // returns 08... or empty if invalid
$no_hp_int = phone_to_international62($no_hp_orig); // returns 62... or false
if (empty($no_hp_local)) {
    $_SESSION['error'] = 'Nomor HP tidak valid. Silakan mulai dari awal.';
    header('Location: aktivasi_akun.php');
    exit();
}

// Cek apakah user sudah disetujui (status_akun = APPROVED)
$stmt_cek = $connect->prepare("SELECT id, nama_lengkap, status_akun FROM pengguna WHERE no_hp = ? LIMIT 1");
if (!$stmt_cek) {
    $_SESSION['error'] = 'Kesalahan database: ' . $connect->error;
    header('Location: aktivasi_akun.php');
    exit();
}

$stmt_cek->bind_param('s', $no_hp_local);
$stmt_cek->execute();
$result_cek = $stmt_cek->get_result();
if ($result_cek->num_rows === 0) {
    $stmt_cek->close();
    // Log for debugging when incoming phone formats don't match DB rows
    error_log(sprintf("[set_pin] Pengguna tidak ditemukan untuk no_hp='%s' atau alt='%s'", $no_hp, $no_hp_alt));
    $_SESSION['error'] = 'Nomor HP tidak terdaftar.';
    header('Location: aktivasi_akun.php');
    exit();
}

$user = $result_cek->fetch_assoc();
$stmt_cek->close();

// Cek status akun
$status = strtolower(trim($user["status_akun"]));
if ($status !== 'approved') {
    $_SESSION["error"] = "Akun Anda belum disetujui. Silakan tunggu persetujuan admin dan login kembali.";
    // Only allow setting PIN after user has successfully logged in and been approved
    header('Location: login/');
    exit();
}

$id_pengguna = $user['id'];
$nama_user = $user['nama_lengkap'];

// Jika ada POST request untuk set PIN
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pin = isset($_POST["pin"]) ? trim($_POST["pin"]) : "";
    $pin_confirm = isset($_POST["pin_confirm"]) ? trim($_POST["pin_confirm"]) : "";
    
    // Validasi input
        $_SESSION["error"] = "PIN wajib diisi di kedua kolom";
        $_SESSION["error"] = "PIN harus 6 digit angka";
        $_SESSION["error"] = "PIN tidak cocok. Silakan coba lagi.";
    if (empty($pin) || empty($pin_confirm)) {
        $_SESSION['error'] = 'PIN wajib diisi di kedua kolom';
        header("Location: set_pin.php?no_hp=" . urlencode($no_hp));
        exit();
    }
    
    // Validasi format PIN (harus 6 digit)
    if (!preg_match('/^\d{6}$/', $pin)) {
        $_SESSION['error'] = 'PIN harus 6 digit angka';
        header("Location: set_pin.php?no_hp=" . urlencode($no_hp));
        exit();
    }
    
    // Validasi PIN dan konfirmasi PIN harus sama
    if ($pin !== $pin_confirm) {
        $_SESSION['error'] = 'PIN tidak cocok. Silakan coba lagi.';
        header("Location: set_pin.php?no_hp=" . urlencode($no_hp));
        exit();
    }
    
    // Hash PIN
    $pin_hash = password_hash($pin, PASSWORD_DEFAULT);
    
    // Update PIN ke database
    $stmt_update = $connect->prepare("UPDATE pengguna SET pin = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?");
    if (!$stmt_update) {
        $_SESSION['error'] = 'Kesalahan database: ' . $connect->error;
        header("Location: set_pin.php?no_hp=" . urlencode($no_hp));
        exit();
    }
    
    $stmt_update->bind_param('si', $pin_hash, $id_pengguna);
    if (!$stmt_update->execute()) {
        $_SESSION['error'] = 'Gagal menyimpan PIN: ' . $stmt_update->error;
        $stmt_update->close();
        header("Location: set_pin.php?no_hp=" . urlencode($no_hp));
        exit();
    }
    $stmt_update->close();
    
    // Redirect ke login dengan pesan sukses
    $_SESSION['success'] = 'PIN berhasil dibuat. Silakan login kembali menggunakan nomor HP dan password Anda.';
    header('Location: login/?success=set_pin');
    exit();
}

// Jika GET request, tampilkan form
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set PIN</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #999;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .header .user-info {
            color: #667eea;
            font-weight: 600;
            font-size: 14px;
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
            font-size: 14px;
            transition: border-color 0.3s;
            letter-spacing: 3px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        .info {
            background-color: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #333;
            line-height: 1.5;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fee;
            border: 1px solid #fcc;
            color: #c33;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Set PIN Anda</h1>
            <p>Buat PIN 6 digit untuk keamanan akun</p>
            <div class="user-info"><?php echo htmlspecialchars($nama_user); ?></div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <strong>💡 Informasi PIN:</strong><br>
            • PIN harus 6 digit angka<br>
            • Gunakan PIN yang mudah diingat<br>
            • Jangan bagikan PIN kepada siapa pun
        </div>
        
        <form method="POST" action="set_pin.php?no_hp=<?php echo urlencode($no_hp); ?>">
            <div class="form-group">
                <label for="pin">PIN (6 digit)</label>
                <input 
                    type="password" 
                    id="pin" 
                    name="pin" 
                    placeholder="••••••"
                    maxlength="6"
                    inputmode="numeric"
                    required
                    autofocus
                >
            </div>
            
            <div class="form-group">
                <label for="pin_confirm">Konfirmasi PIN</label>
                <input 
                    type="password" 
                    id="pin_confirm" 
                    name="pin_confirm" 
                    placeholder="••••••"
                    maxlength="6"
                    inputmode="numeric"
                    required
                >
            </div>
            
            <button type="submit" class="btn">
                ✓ Set PIN
            </button>
        </form>
    </div>
    
    <script>
        // Auto-format input PIN menjadi hanya angka
        document.getElementById('pin').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        document.getElementById('pin_confirm').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
