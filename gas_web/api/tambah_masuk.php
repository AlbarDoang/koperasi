<?php
/**
 * API: Tambah Tabungan Masuk (Setoran)
 * Method: POST
 * 
 * Input:
 * - id_pengguna (required)
 * - id_jenis_tabungan (required)
 * - jumlah (required)
 * - keterangan (optional)
 */

require_once 'config.php';

// Hanya accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(false, 'Method tidak diizinkan. Gunakan POST');
}

// Validasi input
validate_required(['id_pengguna', 'id_jenis_tabungan', 'jumlah']);

// Sanitize input
$id_pengguna = sanitize_int($_POST['id_pengguna']);
$id_jenis_tabungan = sanitize_int($_POST['id_jenis_tabungan']);
$jumlah = sanitize_int($_POST['jumlah']);
$keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : NULL;

// Validasi nilai
if ($jumlah <= 0) {
    send_json(false, 'Jumlah harus lebih dari 0');
}

if ($id_pengguna <= 0 || $id_jenis_tabungan <= 0) {
    send_json(false, 'ID pengguna atau jenis tabungan tidak valid');
}

// Cek pengguna exist
$check_user = $conn->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
$check_user->bind_param('i', $id_pengguna);
$check_user->execute();
if ($check_user->get_result()->num_rows === 0) {
    $check_user->close();
    send_json(false, 'Pengguna tidak ditemukan');
}
$check_user->close();

// Cek jenis tabungan exist
$check_jenis = $conn->prepare("SELECT id FROM jenis_tabungan WHERE id = ? LIMIT 1");
$check_jenis->bind_param('i', $id_jenis_tabungan);
$check_jenis->execute();
if ($check_jenis->get_result()->num_rows === 0) {
    $check_jenis->close();
    send_json(false, 'Jenis tabungan tidak ditemukan');
}
$check_jenis->close();

// Insert ke tabungan_masuk
$query = "INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at)
          VALUES (?, ?, ?, ?, NOW(), NOW())";

$stmt = $conn->prepare($query);
if (!$stmt) {
    send_json(false, 'Prepare gagal: ' . $conn->error);
}

$stmt->bind_param('iiis', $id_pengguna, $id_jenis_tabungan, $jumlah, $keterangan);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    $stmt->close();
    
    send_json(true, 'Setoran berhasil ditambahkan', [
        'id' => $new_id,
        'id_pengguna' => $id_pengguna,
        'id_jenis_tabungan' => $id_jenis_tabungan,
        'jumlah' => $jumlah,
        'keterangan' => $keterangan
    ]);
} else {
    $stmt->close();
    send_json(false, 'Gagal menambahkan setoran: ' . $conn->error);
}
