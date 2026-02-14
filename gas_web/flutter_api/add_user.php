<?php 
/**
 * API: Add User/Anggota
 * Untuk menambahkan anggota baru
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'connection.php';
include 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required_fields = ['id_tabungan', 'nama', 'no_wa', 'username', 'password2'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            sendJsonResponse(false, "Field $field wajib diisi");
        }
    }
    
    $id_tabungan = $connect->real_escape_string($_POST['id_tabungan']);
    $nama = $connect->real_escape_string($_POST['nama']);
    $no_wa = sanitizePhone($_POST['no_wa']);
    $role = isset($_POST['role']) ? $connect->real_escape_string($_POST['role']) : 'anggota';
    $username = $connect->real_escape_string($_POST['username']);
    $password = sha1($_POST['password2']);
    
    // Cek apakah username sudah ada
    $sql_check = "SELECT username FROM pengguna WHERE username='$username'";
    $result_check = $connect->query($sql_check);
    
    if ($result_check->num_rows > 0) {
        sendJsonResponse(false, 'Username sudah digunakan');
    }
    
    // Cek apakah no HP sudah ada
    $sql_check_hp = "SELECT no_hp FROM pengguna WHERE no_hp='$no_wa'";
    $result_check_hp = $connect->query($sql_check_hp);
    
    if ($result_check_hp->num_rows > 0) {
        sendJsonResponse(false, 'Nomor HP sudah terdaftar');
    }
    

    
    // Insert ke database — kolom sesuai schema pengguna (status_akun ENUM)
    // ENUM status_akun: 'draft','submitted','pending','approved','rejected'
    $hashed_password = password_hash($_POST['password2'], PASSWORD_DEFAULT);
    $stmt_insert = $connect->prepare("INSERT INTO pengguna (no_hp, kata_sandi, nama_lengkap, status_akun, saldo, created_at, updated_at) VALUES (?, ?, ?, 'pending', 0, NOW(), NOW())");
    if (!$stmt_insert) {
        sendJsonResponse(false, "Gagal menyiapkan query: " . $connect->error);
    }
    $stmt_insert->bind_param('sss', $no_wa, $hashed_password, $nama);
    $result = $stmt_insert->execute();
    
    if ($result) {
        $stmt_insert->close();
        sendJsonResponse(true, "Berhasil menambahkan $role");
    } else {
        $err = $stmt_insert->error;
        $stmt_insert->close();
        sendJsonResponse(false, "Gagal menambahkan data: " . $err);
    }
    
} else {
    sendJsonResponse(false, 'Method not allowed. Use POST');
}
