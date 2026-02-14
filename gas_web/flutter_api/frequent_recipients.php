<?php
include 'connection.php';
header('Content-Type: application/json');

$id_pengirim = isset($_POST['id_pengirim']) ? trim($_POST['id_pengirim']) : '';
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

if ($id_pengirim === '') {
    echo json_encode(['success' => false, 'message' => 'Missing id_pengirim']);
    exit;
}

$safe_id = $connect->real_escape_string($id_pengirim);
$recipients = [];

// Detect which name column exists in pengguna table
$name_col = 'nama_lengkap';
$chk = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'nama'");
if ($chk && $chk->num_rows > 0) {
    $name_col = 'nama';
}

// Strategy 1: Try t_transfer table if it exists
$has_t_transfer = $connect->query("SHOW TABLES LIKE 't_transfer'");
if ($has_t_transfer && $has_t_transfer->num_rows > 0) {
    $sql = "SELECT t.id_penerima AS id, COALESCE(p.{$name_col}, '') AS nama, COALESCE(p.no_hp, '') AS no_hp,
                   COUNT(*) AS transfers, MAX(t.tanggal) AS last_transfer
            FROM t_transfer t
            LEFT JOIN pengguna p ON t.id_penerima = CAST(p.id AS CHAR)
            WHERE t.id_pengirim = '{$safe_id}'
              AND t.id_penerima IS NOT NULL
              AND t.id_penerima != ''
            GROUP BY t.id_penerima
            ORDER BY transfers DESC, last_transfer DESC
            LIMIT " . intval($limit);
    $result = $connect->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row;
        }
    }
}

// Strategy 2: Fall back to transaksi table (correlation between transfer_keluar and transfer_masuk)
if (empty($recipients)) {
    // Detect which id column is used in transaksi
    $id_col = 'id_pengguna';
    $chk2 = $connect->query("SHOW COLUMNS FROM transaksi LIKE 'id_pengguna'");
    if (!$chk2 || $chk2->num_rows == 0) {
        // try id_anggota
        $chk3 = $connect->query("SHOW COLUMNS FROM transaksi LIKE 'id_anggota'");
        if ($chk3 && $chk3->num_rows > 0) {
            $id_col = 'id_anggota';
        }
    }

    // Match transfer_keluar from sender with corresponding transfer_masuk from recipient
    // by same tanggal and jumlah (keterangan differs between sender/receiver so cannot be used)
    $sql = "SELECT tm.{$id_col} AS id,
                   COALESCE(p.{$name_col}, '') AS nama,
                   COALESCE(p.no_hp, '') AS no_hp,
                   COUNT(*) AS transfers,
                   MAX(tk.tanggal) AS last_transfer
            FROM transaksi tk
            INNER JOIN transaksi tm
                ON tm.jenis_transaksi = 'transfer_masuk'
                AND tm.tanggal = tk.tanggal
                AND tm.jumlah = tk.jumlah
                AND tm.{$id_col} != tk.{$id_col}
            LEFT JOIN pengguna p ON tm.{$id_col} = p.id
            WHERE tk.{$id_col} = '{$safe_id}'
              AND tk.jenis_transaksi = 'transfer_keluar'
              AND tk.status = 'approved'
            GROUP BY tm.{$id_col}
            ORDER BY transfers DESC, last_transfer DESC
            LIMIT " . intval($limit);

    $result = $connect->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row;
        }
    }
}

echo json_encode(['success' => true, 'recipients' => $recipients]);


