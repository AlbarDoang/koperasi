<?php
/**
 * API: Total Saldo Tabungan
 * Method: GET
 * 
 * Input: id_pengguna (required)
 * 
 * Output: Total saldo akumulatif seluruh jenis tabungan
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

// Query total saldo
$query = "
    SELECT 
        COALESCE(SUM(m.jumlah), 0) as total_masuk,
        COALESCE(SUM(k.jumlah), 0) as total_keluar
    FROM pengguna p
    LEFT JOIN tabungan_masuk m ON p.id = m.id_pengguna
    LEFT JOIN tabungan_keluar k ON p.id = k.id_pengguna
    WHERE p.id = ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    send_json(false, 'Prepare gagal: ' . $conn->error);
}

$stmt->bind_param('i', $id_pengguna);

if (!$stmt->execute()) {
    send_json(false, 'Query gagal: ' . $stmt->error);
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$total_masuk = (int) ($row['total_masuk'] ?? 0);
$total_keluar = (int) ($row['total_keluar'] ?? 0);
$total_saldo = $total_masuk - $total_keluar;

$stmt->close();

send_json(true, 'Total saldo berhasil diambil', [
    'total_saldo' => $total_saldo,
    'total_masuk' => $total_masuk,
    'total_keluar' => $total_keluar
]);
