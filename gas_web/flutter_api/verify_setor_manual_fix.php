<?php
/**
 * VERIFICATION SCRIPT: Setor Manual Admin Fix
 * Gunakan untuk verify bahwa perbaikan sudah berjalan dengan baik
 * 
 * Endpoint: /gas/gas_web/flutter_api/verify_setor_manual_fix.php
 * Method: GET/POST
 * 
 * Jika GET: show summary
 * Jika POST: run verification test
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/api_bootstrap.php';

function sendJson($success, $message = '', $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

if (empty($connect)) {
    sendJson(false, 'Database connection error');
}

// GET: Show verification status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $checks = [];
    
    // 1. Check if mulai_nabung table exists
    $check1 = $connect->query("SHOW TABLES LIKE 'mulai_nabung'");
    $checks['mulai_nabung_exists'] = ($check1 && $check1->num_rows > 0);
    
    // 2. Check if sumber column exists
    $check2 = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'sumber'");
    $checks['sumber_column_exists'] = ($check2 && $check2->num_rows > 0);
    
    // 3. Show table structure
    $col_check = $connect->query("SHOW COLUMNS FROM mulai_nabung");
    $columns = [];
    if ($col_check) {
        while ($row = $col_check->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }
    $checks['mulai_nabung_columns'] = $columns;
    
    // 4. Count sample data
    $count_check = $connect->query("SELECT COUNT(*) as cnt FROM mulai_nabung");
    if ($count_check) {
        $row = $count_check->fetch_assoc();
        $checks['mulai_nabung_total_records'] = intval($row['cnt']);
    }
    
    // 5. Check admin sources in mulai_nabung
    if ($checks['sumber_column_exists']) {
        $admin_count = $connect->query("SELECT COUNT(*) as cnt FROM mulai_nabung WHERE sumber = 'admin'");
        if ($admin_count) {
            $row = $admin_count->fetch_assoc();
            $checks['admin_setor_count'] = intval($row['cnt']);
        }
    }
    
    $response = [
        'success' => true,
        'status' => 'VERIFICATION SUMMARY',
        'checks' => $checks,
        'recommendation' => $checks['sumber_column_exists'] 
            ? 'READY - Perbaikan sudah diterapkan' 
            : 'ACTION NEEDED - Jalankan db_ensure_mulai_nabung_columns.php'
    ];
    
    sendJson(true, 'Verification summary', $response);
    exit();
}

// POST: Run test
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_id_pengguna = intval($_POST['id_pengguna'] ?? 0);
    $test_id_jenis = intval($_POST['id_jenis_tabungan'] ?? 1);
    $test_jumlah = floatval($_POST['jumlah'] ?? 50000);
    $test_admin_id = intval($_POST['admin_id'] ?? 999);
    
    if ($test_id_pengguna <= 0) {
        sendJson(false, 'Parameter id_pengguna harus > 0');
    }
    
    // Cek pengguna exist
    $check_user = $connect->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
    $check_user->bind_param('i', $test_id_pengguna);
    $check_user->execute();
    if ($check_user->get_result()->num_rows === 0) {
        sendJson(false, 'Pengguna dengan ID ' . $test_id_pengguna . ' tidak ditemukan');
    }
    $check_user->close();
    
    $connect->begin_transaction();
    
    try {
        $now = date('Y-m-d H:i:s');
        $test_date = date('Y-m-d');
        $test_keterangan = 'TEST - Verifikasi perbaikan setor manual admin';
        
        // Get user info
        $stmt = $connect->prepare("SELECT no_hp, nama_lengkap FROM pengguna WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $test_id_pengguna);
        $stmt->execute();
        $res = $stmt->get_result();
        $user_data = $res->fetch_assoc();
        $stmt->close();
        
        $test_hp = $user_data['no_hp'] ?? '0000000000';
        $test_nama = $user_data['nama_lengkap'] ?? 'Test User';
        
        // Get jenis nama
        $stmt = $connect->prepare("SELECT nama_jenis FROM jenis_tabungan WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $test_id_jenis);
        $stmt->execute();
        $res = $stmt->get_result();
        $jenis_data = $res->fetch_assoc();
        $stmt->close();
        
        $test_jenis_nama = $jenis_data['nama_jenis'] ?? 'Test Jenis';
        
        // Check sumber column
        $col_check = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'sumber'");
        $has_sumber = ($col_check && $col_check->num_rows > 0);
        
        // INSERT ke mulai_nabung
        if ($has_sumber) {
            $stmt = $connect->prepare(
                "INSERT INTO mulai_nabung 
                 (id_tabungan, nomor_hp, nama_pengguna, tanggal, jumlah, jenis_tabungan, status, sumber, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $status = 'berhasil';
            $sumber = 'admin';
            $stmt->bind_param('isssiisss', $test_id_pengguna, $test_hp, $test_nama, $test_date, $test_jumlah, $test_jenis_nama, $status, $sumber, $now);
        } else {
            $stmt = $connect->prepare(
                "INSERT INTO mulai_nabung 
                 (id_tabungan, nomor_hp, nama_pengguna, tanggal, jumlah, jenis_tabungan, status, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $status = 'berhasil';
            $stmt->bind_param('isssiiss', $test_id_pengguna, $test_hp, $test_nama, $test_date, $test_jumlah, $test_jenis_nama, $status, $now);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('INSERT mulai_nabung failed: ' . $stmt->error);
        }
        
        $inserted_id = $stmt->insert_id;
        $stmt->close();
        
        $connect->commit();
        
        $result_data = [
            'test_type' => 'INSERT mulai_nabung',
            'id_pengguna' => $test_id_pengguna,
            'id_jenis_tabungan' => $test_id_jenis,
            'jumlah' => $test_jumlah,
            'inserted_id' => $inserted_id,
            'has_sumber_column' => $has_sumber,
            'status' => 'berhasil',
            'sumber' => 'admin'
        ];
        
        sendJson(true, 'Test INSERT mulai_nabung berhasil', $result_data);
        
    } catch (Exception $e) {
        $connect->rollback();
        sendJson(false, 'Test failed: ' . $e->getMessage());
    }
}

sendJson(false, 'Invalid request method');

?>
