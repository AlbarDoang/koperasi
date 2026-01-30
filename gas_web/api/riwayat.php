<?php
/**
 * API: Riwayat Tabungan (Masuk & Keluar)
 * Method: GET
 * 
 * Input:
 * - id_pengguna (required)
 * - id_jenis_tabungan (optional - jika kosong tampilkan semua jenis)
 * - limit (optional, default: 100)
 * - offset (optional, default: 0)
 * 
 * Output: Array gabungan transaksi masuk dan keluar
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
$id_jenis_tabungan = isset($_GET['id_jenis_tabungan']) ? sanitize_int($_GET['id_jenis_tabungan']) : null;
$limit = isset($_GET['limit']) ? sanitize_int($_GET['limit']) : 100;
$offset = isset($_GET['offset']) ? sanitize_int($_GET['offset']) : 0;

// Validasi nilai
if ($id_pengguna <= 0) {
    send_json(false, 'ID pengguna tidak valid');
}

if ($limit < 1 || $limit > 1000) {
    $limit = 100;
}

if ($offset < 0) {
    $offset = 0;
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

// Cek jenis tabungan jika ditentukan
if ($id_jenis_tabungan !== null && $id_jenis_tabungan > 0) {
    $check_jenis = $conn->prepare("SELECT id FROM jenis_tabungan WHERE id = ? LIMIT 1");
    $check_jenis->bind_param('i', $id_jenis_tabungan);
    $check_jenis->execute();
    if ($check_jenis->get_result()->num_rows === 0) {
        $check_jenis->close();
        send_json(false, 'Jenis tabungan tidak ditemukan');
    }
    $check_jenis->close();
}

// Build query dengan UNION
$where_clause = "id_pengguna = ?";
$params = [$id_pengguna];
$param_types = 'i';

if ($id_jenis_tabungan !== null && $id_jenis_tabungan > 0) {
    $where_clause .= " AND id_jenis_tabungan = ?";
    $params[] = $id_jenis_tabungan;
    $param_types .= 'i';
}

$query = "
    (
        SELECT 
            'masuk' as tipe,
            id,
            id_pengguna,
            id_jenis_tabungan,
            jumlah,
            keterangan,
            created_at as tanggal,
            updated_at
        FROM tabungan_masuk
        WHERE {$where_clause}
    )
    UNION ALL
    (
        SELECT 
            'keluar' as tipe,
            id,
            id_pengguna,
            id_jenis_tabungan,
            jumlah,
            keterangan,
            created_at as tanggal,
            updated_at
        FROM tabungan_keluar
        WHERE {$where_clause}
    )
    ORDER BY tanggal DESC
    LIMIT ? OFFSET ?
";

// Tambahkan limit dan offset ke params
$params[] = $limit;
$params[] = $offset;
$param_types .= 'ii';

$stmt = $conn->prepare($query);
if (!$stmt) {
    send_json(false, 'Prepare gagal: ' . $conn->error);
}

// Bind parameters dinamis
$stmt->bind_param($param_types, ...$params);

if (!$stmt->execute()) {
    send_json(false, 'Query gagal: ' . $stmt->error);
}

$result = $stmt->get_result();
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'tipe' => $row['tipe'],
        'id' => (int) $row['id'],
        'id_pengguna' => (int) $row['id_pengguna'],
        'id_jenis_tabungan' => (int) $row['id_jenis_tabungan'],
        'jumlah' => (int) $row['jumlah'],
        'keterangan' => $row['keterangan'],
        'tanggal' => $row['tanggal'],
        'created_at' => $row['updated_at']
    ];
}

$stmt->close();

send_json(true, 'Riwayat transaksi berhasil diambil', $data);
