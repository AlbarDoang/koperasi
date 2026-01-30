<?php
/**
 * API: Saldo Per Jenis Tabungan
 * Method: GET
 * 
 * Input: id_pengguna (required)
 * 
 * Output: Array saldo per jenis tabungan
 */

require_once 'config.php';

// Hanya accept GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json(false, 'Method tidak diizinkan. Gunakan GET');
}

// Validasi input
validate_required(['id_pengguna']);

// Sanitize input
$id_pengguna = sanitize_int($_GET['id_pengguna']);

if ($id_pengguna <= 0) {
    send_json(false, 'ID pengguna tidak valid');
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

// Query saldo per jenis tabungan
$query = "
    SELECT 
        j.id,
        j.nama,
        COALESCE(SUM(CASE WHEN m.id IS NOT NULL THEN m.jumlah ELSE 0 END), 0) as total_masuk,
        COALESCE(SUM(CASE WHEN k.id IS NOT NULL THEN k.jumlah ELSE 0 END), 0) as total_keluar
    FROM jenis_tabungan j
    LEFT JOIN tabungan_masuk m ON j.id = m.id_jenis_tabungan AND m.id_pengguna = ?
    LEFT JOIN tabungan_keluar k ON j.id = k.id_jenis_tabungan AND k.id_pengguna = ?
    GROUP BY j.id, j.nama
    ORDER BY j.nama ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    send_json(false, 'Prepare gagal: ' . $conn->error);
}

$stmt->bind_param('ii', $id_pengguna, $id_pengguna);

if (!$stmt->execute()) {
    send_json(false, 'Query gagal: ' . $stmt->error);
}

$result = $stmt->get_result();
$data = [];

while ($row = $result->fetch_assoc()) {
    $saldo = $row['total_masuk'] - $row['total_keluar'];
    $data[] = [
        'id' => (int) $row['id'],
        'jenis' => $row['nama'],
        'saldo' => (int) $saldo,
        'total_masuk' => (int) $row['total_masuk'],
        'total_keluar' => (int) $row['total_keluar']
    ];
}

$stmt->close();

send_json(true, 'Data saldo per jenis berhasil diambil', $data);
