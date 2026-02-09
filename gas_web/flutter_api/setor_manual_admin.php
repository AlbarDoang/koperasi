<?php
/**
 * API: Setor Saldo Manual oleh Admin
 * Endpoint: /gas_web/flutter_api/setor_manual_admin.php
 * Method: POST
 * 
 * Parameters:
 *   - id_pengguna (integer, required): ID pengguna
 *   - id_jenis_tabungan (integer, required): ID jenis tabungan
 *   - jumlah (decimal, required): Nominal setoran
 *   - tanggal_setor (date, optional): Tanggal setoran (default: hari ini)
 *   - keterangan (string, optional): Keterangan setoran
 *   - admin_id (integer, required): ID admin yang melakukan setor
 * 
 * Behavior:
 *   1. Validasi input
 *   2. Cek pengguna dan jenis tabungan exist
 *   3. Gunakan database transaction
 *   4. Jika saldo tabungan belum ada → create entry baru
 *   5. Jika sudah ada → tambahkan saldo
 *   6. Simpan histori ke tabel tabungan_masuk dengan sumber = 'admin_manual'
 *   7. Return success dengan data hasil setoran
 */

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

// Set timezone ke Indonesia (UTC+7)
date_default_timezone_set('Asia/Jakarta');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/api_bootstrap.php';

// Helper function untuk send JSON response
function sendJson($success, $message = '', $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

// Validasi method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJson(false, 'Method not allowed');
}

// Cek koneksi database
if (empty($connect)) {
    sendJson(false, 'Database connection error');
}

// Get parameters
$id_pengguna = isset($_POST['id_pengguna']) ? intval($_POST['id_pengguna']) : 0;
$id_jenis_tabungan = isset($_POST['id_jenis_tabungan']) ? intval($_POST['id_jenis_tabungan']) : 0;

// NORMALISASI JUMLAH: hapus Rp, titik, koma, spasi
// Input bisa: "500000" atau "500.000" atau "Rp 500.000" atau "Rp500,000"
$jumlah_raw = isset($_POST['jumlah']) ? trim($_POST['jumlah']) : '0';
// Hapus 'Rp' dan karakter non-numerik
$jumlah_raw = preg_replace('/[^\d,.-]/', '', $jumlah_raw);
// Pastikan format: replace koma dengan titik jika ada ribuan (ciri 500,000 atau 500.000)
// Logic: jika ada titik/koma, yang paling terakhir adalah decimal separator
$jumlah_raw = str_replace(' ', '', $jumlah_raw); // hapus spasi
// Jika ada koma dan titik, tentukan mana yang decimal separator (biasanya yang terakhir)
if (strpos($jumlah_raw, ',') !== false && strpos($jumlah_raw, '.') !== false) {
    // Ada kedua-duanya: 1.000,50 atau 1,000.50
    $last_dot = strrpos($jumlah_raw, '.');
    $last_comma = strrpos($jumlah_raw, ',');
    if ($last_comma > $last_dot) {
        // Format Indonesia: 1.000,50 → remove dot, keep comma as decimal
        $jumlah_raw = str_replace('.', '', $jumlah_raw);
        $jumlah_raw = str_replace(',', '.', $jumlah_raw);
    } else {
        // Format US: 1,000.50 → remove comma
        $jumlah_raw = str_replace(',', '', $jumlah_raw);
    }
} else if (strpos($jumlah_raw, ',') !== false) {
    // Hanya ada koma - bisa ribuan atau decimal
    // Jika 3 digit setelah koma, itu ribuan; jika ≤2, itu decimal
    $parts = explode(',', $jumlah_raw);
    if (strlen($parts[count($parts)-1]) == 3) {
        // Format Indonesia ribuan: 500,000 → 500000
        $jumlah_raw = str_replace(',', '', $jumlah_raw);
    } else {
        // Decimal: 500,50 → 500.50
        $jumlah_raw = str_replace(',', '.', $jumlah_raw);
    }
} else if (strpos($jumlah_raw, '.') !== false) {
    // Hanya ada titik - bisa ribuan atau decimal
    $parts = explode('.', $jumlah_raw);
    if (strlen($parts[count($parts)-1]) == 3) {
        // Format US ribuan: 500.000 → 500000
        $jumlah_raw = str_replace('.', '', $jumlah_raw);
    }
    // Jika ≤2 digit setelah titik, keep as is (sudah decimal format)
}

$jumlah = floatval($jumlah_raw);

$tanggal_setor = isset($_POST['tanggal_setor']) ? trim($_POST['tanggal_setor']) : date('Y-m-d');
$keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';
$admin_id = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;

// DEBUG: Log parameters
error_log('Setor Manual Debug:');
error_log('  id_pengguna: ' . $id_pengguna);
error_log('  id_jenis_tabungan: ' . $id_jenis_tabungan);
error_log('  jumlah: ' . $jumlah);
error_log('  admin_id: ' . $admin_id);
error_log('  POST data: ' . json_encode($_POST));

// Validasi input
if ($id_pengguna <= 0) {
    sendJson(false, 'ID pengguna tidak valid');
}

if ($id_jenis_tabungan <= 0) {
    sendJson(false, 'ID jenis tabungan tidak valid');
}

if ($jumlah <= 0) {
    sendJson(false, 'Jumlah setoran harus lebih dari 0');
}

if ($admin_id <= 0) {
    sendJson(false, 'ID admin tidak valid');
}

// Validasi tanggal
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_setor)) {
    $tanggal_setor = date('Y-m-d');
}

// Start transaction
$connect->begin_transaction();

try {
    // Cek pengguna exist
    $stmt_user = $connect->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
    if (!$stmt_user) {
        throw new Exception('Prepare error: ' . $connect->error);
    }
    $stmt_user->bind_param('i', $id_pengguna);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();
    
    if ($res_user->num_rows === 0) {
        $stmt_user->close();
        throw new Exception('Pengguna tidak ditemukan');
    }
    $stmt_user->close();

    // Cek jenis tabungan exist dan ambil nama jenis
    $stmt_jenis = $connect->prepare("SELECT id, nama_jenis FROM jenis_tabungan WHERE id = ? LIMIT 1");
    if (!$stmt_jenis) {
        throw new Exception('Prepare error: ' . $connect->error);
    }
    $stmt_jenis->bind_param('i', $id_jenis_tabungan);
    $stmt_jenis->execute();
    $res_jenis = $stmt_jenis->get_result();
    
    $nama_jenis_tabungan = 'Tabungan Reguler'; // default fallback
    if ($res_jenis->num_rows === 0) {
        $stmt_jenis->close();
        throw new Exception('Jenis tabungan tidak ditemukan');
    } else {
        $row_jenis = $res_jenis->fetch_assoc();
        $nama_jenis_tabungan = $row_jenis['nama_jenis'] ?? 'Tabungan Reguler';
    }
    $stmt_jenis->close();

    // Ensure nama_jenis_tabungan has "Tabungan " prefix for display
    if (strpos($nama_jenis_tabungan, 'Tabungan') === false) {
        $nama_jenis_tabungan = 'Tabungan ' . $nama_jenis_tabungan;
    }

    // Validasi admin_id hanya perlu check apakah ID valid (> 0)
    // Tidak perlu SELECT dari database karena admin mungkin tidak ada di tabel pengguna
    // atau record admin sudah dihapus tapi masih bisa melakukan transaksi
    if ($admin_id <= 0) {
        throw new Exception('Admin ID tidak valid');
    }

    // Default keterangan if empty
    if (empty($keterangan)) {
        $keterangan = 'Setor saldo manual oleh admin';
    }

    // Insert ke tabungan_masuk dengan sumber 'admin_manual'
    $now = date('Y-m-d H:i:s');
    $sumber = 'admin_manual';
    $status_val = 'approved'; // Status 'approved' bukan 'berhasil' agar sesuai dengan sistem notifikasi
    
    // Detect if extended columns exist
    $has_tanggal = false;
    $has_sumber = false;
    $has_status = false;
    $has_admin_id = false;
    $has_jenis_tabungan_col = false;
    
    $col_check = $connect->query("SHOW COLUMNS FROM tabungan_masuk");
    if ($col_check) {
        $col_names = [];
        while ($col_row = $col_check->fetch_assoc()) {
            $col_names[] = $col_row['Field'];
        }
        $has_tanggal = in_array('tanggal', $col_names);
        $has_sumber = in_array('sumber', $col_names);
        $has_status = in_array('status', $col_names);
        $has_admin_id = in_array('admin_id', $col_names);
        $has_jenis_tabungan_col = in_array('jenis_tabungan', $col_names);
    }
    
    // STEP 1: SKIP UPDATE pengguna.saldo
    // Setor manual HANYA menambah saldo TABUNGAN yang dipilih, BUKAN saldo bebas/utama pengguna
    $has_saldo = false; // Not used, kept for compatibility

    // STEP 2: Build and execute insert query untuk tabungan_masuk
    // Use a simpler approach: build the full query with prepared statement
    $stmt_insert = null;
    
    if ($has_tanggal && $has_sumber && $has_status && $has_admin_id && $has_jenis_tabungan_col) {
        // All extended columns exist - use full insert with jenis_tabungan string
        $stmt_insert = $connect->prepare(
            "INSERT INTO tabungan_masuk 
             (id_pengguna, id_jenis_tabungan, jumlah, keterangan, tanggal, jenis_tabungan, sumber, status, admin_id, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_insert) {
            throw new Exception('Prepare error: ' . $connect->error);
        }
        $stmt_insert->bind_param('iidssssisss', $id_pengguna, $id_jenis_tabungan, $jumlah, $keterangan, $tanggal_setor, $nama_jenis_tabungan, $sumber, $status_val, $admin_id, $now, $now);
    } else if ($has_tanggal && $has_sumber && $has_status && $has_admin_id) {
        // Extended columns without jenis_tabungan string
        $stmt_insert = $connect->prepare(
            "INSERT INTO tabungan_masuk 
             (id_pengguna, id_jenis_tabungan, jumlah, keterangan, tanggal, sumber, status, admin_id, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_insert) {
            throw new Exception('Prepare error: ' . $connect->error);
        }
        $stmt_insert->bind_param('iidsssisss', $id_pengguna, $id_jenis_tabungan, $jumlah, $keterangan, $tanggal_setor, $sumber, $status_val, $admin_id, $now, $now);
    } else {
        // Fallback: only use basic columns if extended columns don't exist
        $stmt_insert = $connect->prepare(
            "INSERT INTO tabungan_masuk 
             (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt_insert) {
            throw new Exception('Prepare error: ' . $connect->error);
        }
        $stmt_insert->bind_param('iidsss', $id_pengguna, $id_jenis_tabungan, $jumlah, $keterangan, $now, $now);
    }
    
    if (!$stmt_insert->execute()) {
        error_log('INSERT FAILED - Error: ' . $stmt_insert->error);
        error_log('INSERT FAILED - Errno: ' . $stmt_insert->errno);
        error_log('Has tanggal: ' . ($has_tanggal ? 'YES' : 'NO'));
        error_log('Has sumber: ' . ($has_sumber ? 'YES' : 'NO'));
        error_log('Has status: ' . ($has_status ? 'YES' : 'NO'));
        error_log('Has admin_id: ' . ($has_admin_id ? 'YES' : 'NO'));
        error_log('Has jenis_tabungan_col: ' . ($has_jenis_tabungan_col ? 'YES' : 'NO'));
        throw new Exception('Insert error: ' . $stmt_insert->error);
    }
    
    $tabungan_masuk_id = $stmt_insert->insert_id;
    $stmt_insert->close();

    // STEP 3: Insert ke tabel transaksi untuk Riwayat Transaksi
    // Ambil total saldo tabungan SEBELUM setoran
    $stmt_saldo_before = $connect->prepare(
        "SELECT COALESCE(SUM(jumlah), 0) as total_saldo 
         FROM tabungan_masuk 
         WHERE id_pengguna = ? AND id_jenis_tabungan = ?"
    );
    if (!$stmt_saldo_before) {
        throw new Exception('Prepare saldo_before error: ' . $connect->error);
    }
    $stmt_saldo_before->bind_param('ii', $id_pengguna, $id_jenis_tabungan);
    $stmt_saldo_before->execute();
    $res_saldo_before = $stmt_saldo_before->get_result();
    $row_saldo = $res_saldo_before->fetch_assoc();
    
    // Note: saldo_before adalah SEBELUM insert di STEP 2, 
    // tapi kita sudah insert di STEP 2, jadi saldo_before = total - jumlah_sekarang
    $saldo_sesudah = floatval($row_saldo['total_saldo']);
    $saldo_sebelum = $saldo_sesudah - $jumlah;
    
    if ($saldo_sebelum < 0) {
        $saldo_sebelum = 0; // Tidak boleh negatif
    }
    
    $stmt_saldo_before->close();
    
    // Insert ke tabel transaksi dengan struktur yang benar
    // Struktur: id_transaksi, id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status
    $jenis_transaksi = 'setoran';
    // CRITICAL: Add tabungan_masuk ID to keterangan so it can be matched during approval
    $keterangan_trans = $keterangan ? 'Setoran manual oleh admin - ' . $keterangan : 'Setoran manual oleh admin';
    $keterangan_trans .= ' (tabungan_masuk ' . intval($tabungan_masuk_id) . ')';
    $status_trans = 'approved';
    
    $stmt_transaksi = $connect->prepare(
        "INSERT INTO transaksi 
         (id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt_transaksi) {
        throw new Exception('Prepare transaksi error: ' . $connect->error);
    }
    
    $stmt_transaksi->bind_param('isdddsss', $id_pengguna, $jenis_transaksi, $jumlah, $saldo_sebelum, $saldo_sesudah, $keterangan_trans, $now, $status_trans);
    
    if (!$stmt_transaksi->execute()) {
        // Log error tapi jangan stop proses (non-fatal)
        error_log('Transaksi insert warning (non-fatal): ' . $stmt_transaksi->error);
    }
    $id_transaksi = $stmt_transaksi->insert_id;
    $stmt_transaksi->close();

    // STEP 4: Insert ke tabel mulai_nabung untuk konsistensi data
    // Peringatan: WAJIB insert ke mulai_nabung agar setoran manual muncul di halaman "Tabungan Masuk"
    // Tabel mulai_nabung adalah sumber data untuk halaman riwayat setoran user
    try {
        // Cek apakah kolom 'sumber' ada di mulai_nabung
        $has_sumber_col = false;
        $col_check_mn = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'sumber'");
        if ($col_check_mn && $col_check_mn->num_rows > 0) {
            $has_sumber_col = true;
        }
        
        // Get user info (nomor_hp dan nama_pengguna) untuk mulai_nabung
        $stmt_user_info = $connect->prepare("SELECT no_hp, nama_lengkap FROM pengguna WHERE id = ? LIMIT 1");
        if (!$stmt_user_info) {
            throw new Exception('Prepare user_info error: ' . $connect->error);
        }
        $stmt_user_info->bind_param('i', $id_pengguna);
        $stmt_user_info->execute();
        $res_user_info = $stmt_user_info->get_result();
        
        $nomor_hp = '';
        $nama_pengguna = '';
        if ($res_user_info->num_rows > 0) {
            $row_user_info = $res_user_info->fetch_assoc();
            $nomor_hp = $row_user_info['no_hp'] ?? '';
            $nama_pengguna = $row_user_info['nama_lengkap'] ?? '';
        }
        $stmt_user_info->close();
        
        // Tentukan status untuk mulai_nabung
        // Status 'approved' di tabungan_masuk → 'berhasil' di mulai_nabung
        $status_mulai = 'berhasil';
        
        // Tentukan sumber
        $sumber_mulai = 'admin';
        
        // Build INSERT statement untuk mulai_nabung
        if ($has_sumber_col) {
            // Jika kolom sumber sudah ada, include ke dalam INSERT
            $stmt_mulai = $connect->prepare(
                "INSERT INTO mulai_nabung 
                 (id_tabungan, nomor_hp, nama_pengguna, tanggal, jumlah, jenis_tabungan, status, sumber, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt_mulai) {
                throw new Exception('Prepare mulai_nabung error: ' . $connect->error);
            }
            $now_datetime = date('Y-m-d H:i:s');
            $stmt_mulai->bind_param('isssdssss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $sumber_mulai, $now_datetime);
        } else {
            // Jika kolom sumber belum ada, insert tanpa sumber
            $stmt_mulai = $connect->prepare(
                "INSERT INTO mulai_nabung 
                 (id_tabungan, nomor_hp, nama_pengguna, tanggal, jumlah, jenis_tabungan, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt_mulai) {
                throw new Exception('Prepare mulai_nabung error: ' . $connect->error);
            }
            $now_datetime = date('Y-m-d H:i:s');
            $stmt_mulai->bind_param('isssdsss', $id_pengguna, $nomor_hp, $nama_pengguna, $tanggal_setor, $jumlah, $nama_jenis_tabungan, $status_mulai, $now_datetime);
        }
        
        if (!$stmt_mulai->execute()) {
            // Log error tapi jangan stop proses (non-fatal) - untuk backward compatibility
            error_log('WARNING [setor_manual] Insert mulai_nabung gagal (non-fatal): ' . $stmt_mulai->error);
            error_log('WARNING [setor_manual] Kolom sumber tersedia: ' . ($has_sumber_col ? 'YES' : 'NO'));
        } else {
            $mulai_nabung_id = $stmt_mulai->insert_id;
            error_log('[setor_manual] Insert mulai_nabung berhasil: id=' . $mulai_nabung_id);
        }
        $stmt_mulai->close();
        
    } catch (Exception $e) {
        // Log error tapi jangan stop transaksi (non-fatal)
        error_log('WARNING [setor_manual] mulai_nabung insert error (non-fatal): ' . $e->getMessage());
    }

    // STEP 5: Insert ke tabel notifikasi
    require_once __DIR__ . '/notif_helper.php';
    
    $notif_title = 'Setoran Tabungan Berhasil';
    $notif_message = 'Admin telah menambahkan saldo ' . $nama_jenis_tabungan . ' Anda sebesar Rp' . number_format($jumlah, 0, ',', '.');
    
    $notif_data = json_encode([
        'type' => 'setoran_manual',
        'id_tabungan_masuk' => $tabungan_masuk_id,
        'id_transaksi' => $id_transaksi,
        'jumlah' => $jumlah,
        'admin_id' => $admin_id,
        'jenis_tabungan' => $nama_jenis_tabungan,
        'status' => 'berhasil'
    ]);
    
    $notif_id = safe_create_notification(
        $connect,
        $id_pengguna,
        'tabungan',
        $notif_title,
        $notif_message,
        $notif_data
    );
    
    if ($notif_id === false) {
        error_log('[setor_manual] Notifikasi gagal atau duplikat untuk user ' . $id_pengguna);
    }

    // STEP 6: Commit transaction
    $connect->commit();

    // Prepare response data
    $response_data = [
        'id' => $tabungan_masuk_id,
        'id_pengguna' => $id_pengguna,
        'id_jenis_tabungan' => $id_jenis_tabungan,
        'jumlah' => $jumlah,
        'tanggal' => $tanggal_setor,
        'keterangan' => $keterangan,
        'sumber' => $sumber,
        'status' => $status_val,
        'admin_id' => $admin_id,
        'saldo_updated' => $has_saldo,
        'notif_id' => $notif_id
    ];

    sendJson(true, 'Setor saldo manual berhasil disimpan ke tabungan', $response_data);

} catch (Exception $e) {
    // Rollback on error
    $connect->rollback();
    sendJson(false, $e->getMessage());
}

?>

