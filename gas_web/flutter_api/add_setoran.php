<?php 
/**
 * API: Add Setoran (Petugas/Teller)
 * Untuk petugas input setoran tunai (cash) ke saldo digital anggota
 */
include 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required_fields = ['id_anggota', 'jumlah', 'id_petugas'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(array(
                "success" => false,
                "message" => "Field $field wajib diisi"
            ));
            exit();
        }
    }
    
    $id_anggota = $connect->real_escape_string($_POST['id_anggota']);
    $jumlah = floatval($_POST['jumlah']);
    $id_petugas = intval($_POST['id_petugas']);
    $keterangan = isset($_POST['keterangan']) ? $connect->real_escape_string($_POST['keterangan']) : 'Setoran Tunai';
    $tanggal = date('Y-m-d');
    
    // Validasi jumlah
    if ($jumlah <= 0) {
        echo json_encode(array(
            "success" => false,
            "message" => "Jumlah setoran harus lebih dari 0"
        ));
        exit();
    }
    
    // Cek anggota ada atau tidak
    $sql_check = "SELECT id, id_anggota, nama, nis FROM pengguna WHERE id_anggota='$id_anggota' AND status='aktif'";
    $result_check = $connect->query($sql_check);
    
    if ($result_check->num_rows == 0) {
        echo json_encode(array(
            "success" => false,
            "message" => "Anggota tidak ditemukan atau tidak aktif"
        ));
        exit();
    }
    
    $anggota = $result_check->fetch_assoc();
    $internal_user_id = intval($anggota['id']);
    $nama_anggota = $anggota['nama'];
$nis = $anggota['nis'];

// Generate nomor setoran
    $no_masuk = 'SM-' . date('YmdHis') . '-' . $id_anggota;
    
    // Start transaction
    $connect->begin_transaction();
    
    try {
        // 1. Insert ledger masuk (masuk) via helper
        include_once __DIR__ . '/../login/function/ledger_helpers.php';
        $ok = insert_ledger_masuk($connect, intval($id_anggota), $jumlah, 'Setoran: ' . $keterangan, 1, $id_petugas);
        if (!$ok) throw new Exception('Gagal menulis ledger setoran');
        
        // 2. Insert ke tabel t_masuk
        $sql_masuk = "INSERT INTO t_masuk (no_masuk, nama, id_tabungan, kelas, tanggal, jumlah, created_at, kegiatan, id_petugas) 
                      VALUES ('$no_masuk', '$nama_anggota', '$nis', '-', '$tanggal', '$jumlah', NOW(), '$keterangan', '$id_petugas')";
        $connect->query($sql_masuk);
        
        // 3. (Saldo updated by helper)
        
        // 4. Insert ke tabel transaksi (untuk histori)
        $sql_transaksi = "INSERT INTO transaksi (no_masuk, nama, id_tabungan, kelas, kegiatan, jumlah_masuk, jumlah_keluar, tanggal, petugas, kegiatan2, ip, created_at)
                         VALUES ('$no_masuk', '$nama_anggota', '$nis', '-', '$keterangan', '$jumlah', 0, '$tanggal', 'Petugas', '$keterangan', '', NOW())";
        $connect->query($sql_transaksi);
        
        // Commit transaction
        $connect->commit();
        
        // Get saldo terbaru
        $sql_saldo = "SELECT saldo FROM pengguna WHERE id_anggota='$id_anggota'";
        $result_saldo = $connect->query($sql_saldo);
        $row_saldo = $result_saldo->fetch_assoc();

        // Create notification for the user (topup)
        try {
            require_once __DIR__ . '/notif_helper.php';
            $title = 'Setoran Berhasil';
            $message_notif = 'Setoran tabungan Anda telah diterima dan ditambahkan ke saldo.';
            safe_create_notification($connect, $internal_user_id, 'mulai_nabung', $title, $message_notif, json_encode(['no_masuk' => $no_masuk, 'amount' => $jumlah]));
        } catch (Exception $_e) {
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " add_setoran notif err: " . $_e->getMessage() . "\n", FILE_APPEND);
        }
        
        echo json_encode(array(
            "success" => true,
            "message" => "Setoran berhasil dicatat",
            "data" => array(
                "no_masuk" => $no_masuk,
                "nama" => $nama_anggota,
                "jumlah" => $jumlah,
                "saldo_baru" => $row_saldo['saldo'],
                "tanggal" => $tanggal
            )
        ));
        
    } catch (Exception $e) {
        $connect->rollback();
        echo json_encode(array(
            "success" => false,
            "message" => "Gagal menyimpan setoran: " . $e->getMessage()
        ));
    }
    
} else {
    echo json_encode(array(
        "success" => false,
        "message" => "Method not allowed. Use POST"
    ));
}
