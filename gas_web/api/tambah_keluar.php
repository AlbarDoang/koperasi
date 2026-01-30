<?php
/**
 * API: Tambah Tabungan Keluar (Penarikan)
 * Method: POST
 * 
 * Input:
 * - id_pengguna (required)
 * - id_jenis_tabungan (required)
 * - jumlah (required)
 * - keterangan (optional)
 * 
 * Validasi: Saldo harus cukup untuk penarikan
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

// Start transaction
$conn->begin_transaction();

try {
    // Cek pengguna exist
    $check_user = $conn->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
    $check_user->bind_param('i', $id_pengguna);
    $check_user->execute();
    if ($check_user->get_result()->num_rows === 0) {
        $check_user->close();
        throw new Exception('Pengguna tidak ditemukan');
    }
    $check_user->close();

    // Cek jenis tabungan exist
    $check_jenis = $conn->prepare("SELECT id FROM jenis_tabungan WHERE id = ? LIMIT 1");
    $check_jenis->bind_param('i', $id_jenis_tabungan);
    $check_jenis->execute();
    if ($check_jenis->get_result()->num_rows === 0) {
        $check_jenis->close();
        throw new Exception('Jenis tabungan tidak ditemukan');
    }
    $check_jenis->close();

    // Hitung saldo jenis tabungan (masuk - keluar)
    $saldo_query = "
        SELECT 
            COALESCE(SUM(CASE WHEN tipe = 'masuk' THEN jumlah ELSE 0 END), 0) as total_masuk,
            COALESCE(SUM(CASE WHEN tipe = 'keluar' THEN jumlah ELSE 0 END), 0) as total_keluar
        FROM (
            SELECT 'masuk' as tipe, jumlah FROM tabungan_masuk 
            WHERE id_pengguna = ? AND id_jenis_tabungan = ?
            UNION ALL
            SELECT 'keluar' as tipe, jumlah FROM tabungan_keluar 
            WHERE id_pengguna = ? AND id_jenis_tabungan = ?
        ) as combined
    ";

    $saldo_stmt = $conn->prepare($saldo_query);
    if (!$saldo_stmt) {
        throw new Exception('Prepare saldo gagal: ' . $conn->error);
    }

    $saldo_stmt->bind_param('iiii', $id_pengguna, $id_jenis_tabungan, $id_pengguna, $id_jenis_tabungan);
    $saldo_stmt->execute();
    $result = $saldo_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Tidak ada transaksi, saldo = 0
        $total_masuk = 0;
        $total_keluar = 0;
    } else {
        $row = $result->fetch_assoc();
        $total_masuk = $row['total_masuk'] ?? 0;
        $total_keluar = $row['total_keluar'] ?? 0;
    }
    $saldo_stmt->close();

    $saldo = $total_masuk - $total_keluar;

    // Cek saldo cukup
    if ($saldo < $jumlah) {
        $conn->rollback();
        send_json(false, "Saldo tidak cukup. Saldo: Rp " . number_format($saldo, 0, ',', '.'));
    }

    // Insert ke tabungan_keluar
    $query = "INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at)
              VALUES (?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Prepare insert gagal: ' . $conn->error);
    }

    $stmt->bind_param('iiis', $id_pengguna, $id_jenis_tabungan, $jumlah, $keterangan);

    if (!$stmt->execute()) {
        throw new Exception('Gagal menambahkan penarikan: ' . $stmt->error);
    }

    $new_id = $stmt->insert_id;
    $stmt->close();

    // Commit transaction
    $conn->commit();

    send_json(true, 'Penarikan berhasil diproses', [
        'id' => $new_id,
        'id_pengguna' => $id_pengguna,
        'id_jenis_tabungan' => $id_jenis_tabungan,
        'jumlah' => $jumlah,
        'keterangan' => $keterangan,
        'saldo_setelah' => $saldo - $jumlah
    ]);

} catch (Exception $e) {
    $conn->rollback();
    send_json(false, $e->getMessage());
}
