<?php
/**
 * API: Add Transfer (Mobile User)
 * Untuk transfer antar akun di mobile app
 */
include 'connection.php';
date_default_timezone_set("Asia/Jakarta");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get IP
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        $ip=$_SERVER['HTTP_CLIENT_IP'];
    } elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip=$_SERVER['REMOTE_ADDR'];
    }
    
    $tanggal = date('Y-m-d');
    $no_transfer = 'TF-' . date('YmdHis');
    
    // Get input
    $id_pengirim = $connect->real_escape_string($_POST['id_pengirim'] ?? $_POST['id_tabungan'] ?? $_POST['user_id'] ?? '');
    $id_penerima = $connect->real_escape_string($_POST['id_penerima'] ?? $_POST['id_target'] ?? $_POST['target'] ?? '');
    // PIN (6 digits) used to authorize transfer. Required.
    $pin = $connect->real_escape_string($_POST['pin'] ?? '');
    $keterangan = $connect->real_escape_string($_POST['keterangan'] ?? $_POST['ket'] ?? 'Transfer');
    $nominal = floatval($_POST['nominal'] ?? 0);
    
    // Validasi input
    if (empty($id_pengirim) || empty($id_penerima) || empty($pin) || $nominal <= 0) {
        echo json_encode(array(
            "success" => false,
            "message" => "Data transfer tidak lengkap"
        ));
        exit();
    }

    // PIN must be 6 numeric digits
    if (!preg_match('/^[0-9]{6}$/', $pin)) {
        echo json_encode(array(
            "success" => false,
            "message" => "PIN tidak valid"
        ));
        exit();
    }
    
    // Start transaction
    $connect->begin_transaction();
    
    try {
        // Helper: check if a column exists in a table
        $column_exists = function($table, $col) use ($connect) {
            $r = $connect->query("SHOW COLUMNS FROM `" . $connect->real_escape_string($table) . "` LIKE '" . $connect->real_escape_string($col) . "'");
            return ($r && $r->num_rows > 0);
        };

        // Build safe select fragments for name/status/kelas/pin
        if ($column_exists('pengguna', 'nama')) {
            $name_select = "nama as nama";
        } else if ($column_exists('pengguna', 'nama_lengkap')) {
            $name_select = "nama_lengkap as nama";
        } else {
            $name_select = "'' as nama";
        }

        if ($column_exists('pengguna', 'kelas')) {
            $kelas_select = "COALESCE(kelas, '') as kelas";
        } else {
            $kelas_select = "'' as kelas";
        }

        if ($column_exists('pengguna', 'pin')) {
            $pin_select = "pin";
        } else {
            $pin_select = "NULL as pin";
        }

        if ($column_exists('pengguna', 'password')) {
            $password_select = 'password';
        } else if ($column_exists('pengguna', 'kata_sandi')) {
            $password_select = 'kata_sandi as password';
        } else {
            $password_select = 'NULL as password';
        }
        // 1. Validasi pengirim (dengan PIN)
        // Support lookup by DB id, id_anggota, nis or phone number (no_hp)
        // Support schemas using `id_pengguna` instead of `id_anggota`.
        // Select conservative set of columns compatible with different schemas
        // Build WHERE matching only existing columns
        $where_parts = ["id='$id_pengirim'", "no_hp='$id_pengirim'"];
        if ($column_exists('pengguna', 'nis')) $where_parts[] = "nis='$id_pengirim'";
        $where_sql = '(' . implode(' OR ', $where_parts) . ')';
        // Build status check depending on available columns
        $status_checks = [];
        if ($column_exists('pengguna', 'status')) $status_checks[] = "status='aktif'";
        if ($column_exists('pengguna', 'status_akun')) $status_checks[] = "LOWER(status_akun) = 'approved'";
        $status_sql = count($status_checks) ? ' AND (' . implode(' OR ', $status_checks) . ')' : '';
        $sql_pengirim = "SELECT id, no_hp, {$name_select}, {$kelas_select}, saldo, {$password_select}, {$pin_select} FROM pengguna 
            WHERE {$where_sql} {$status_sql} LIMIT 1";
        $result_pengirim = $connect->query($sql_pengirim);
        if ($result_pengirim === false) {
            error_log('add_transfer.php: pengirim lookup error: ' . $connect->error);
            throw new Exception('Terjadi kesalahan pada server (pengirim)');
        }
        if ($result_pengirim->num_rows == 0) {
            throw new Exception("Pengirim tidak ditemukan");
        }
        
        $data_pengirim = $result_pengirim->fetch_assoc();
        $nama_pengirim = $data_pengirim['nama'];
        $kelas_pengirim = $data_pengirim['kelas'];
        $saldo_pengirim = floatval($data_pengirim['saldo']);
        // prefer id_pengguna when present, else use DB id
        $id_pengirim_actual = !empty($data_pengirim['id_pengguna']) ? $data_pengirim['id_pengguna'] : $data_pengirim['id'];
        // numeric DB id (used for ledger updates and transaksi.id_tabungan)
        $db_id_pengirim = intval($data_pengirim['id']);
        
        // Verifikasi PIN
        if (empty($data_pengirim['pin'])) {
            throw new Exception("PIN belum diatur. Silakan atur PIN terlebih dahulu di menu Pengaturan.");
        }
        if (!password_verify($pin, $data_pengirim['pin'])) {
            throw new Exception("PIN salah");
        }
        
        // 2. Validasi penerima
        // 2. Validasi penerima -- lookup by id, id_anggota, nis or phone
        // Support schemas using `id_pengguna` instead of `id_anggota`.
        $where_parts2 = ["id='$id_penerima'", "no_hp='$id_penerima'"];
        if ($column_exists('pengguna', 'nis')) $where_parts2[] = "nis='$id_penerima'";
        $where_sql2 = '(' . implode(' OR ', $where_parts2) . ')';
        $sql_penerima = "SELECT id, no_hp, {$name_select}, {$kelas_select}, saldo FROM pengguna 
            WHERE {$where_sql2} {$status_sql} LIMIT 1";
        $result_penerima = $connect->query($sql_penerima);
        if ($result_penerima === false) {
            error_log('add_transfer.php: penerima lookup error: ' . $connect->error);
            throw new Exception('Terjadi kesalahan pada server (penerima)');
        }
        if ($result_penerima->num_rows == 0) {
            throw new Exception("Penerima tidak ditemukan");
        }
        
        $data_penerima = $result_penerima->fetch_assoc();
        $nama_penerima = $data_penerima['nama'];
        $kelas_penerima = $data_penerima['kelas'];
        $saldo_penerima = floatval($data_penerima['saldo']);
        $id_penerima_actual = !empty($data_penerima['id_pengguna']) ? $data_penerima['id_pengguna'] : $data_penerima['id'];
        // numeric DB id (used for ledger updates and transaksi.id_tabungan)
        $db_id_penerima = intval($data_penerima['id']);
        
        // 3. Validasi saldo cukup
        if ($saldo_pengirim < $nominal) {
            throw new Exception("Saldo tidak cukup. Saldo Anda: Rp. " . number_format($saldo_pengirim, 0, ',', '.'));
        }
        
        // 4. Cek tidak ada duplikasi (only if t_transfer exists)
        $result_check = null;
        $has_t_transfer_check = $connect->query("SHOW TABLES LIKE 't_transfer'");
        if ($has_t_transfer_check && $has_t_transfer_check->num_rows > 0) {
            $sql_check = "SELECT id_transfer FROM t_transfer WHERE no_transfer='$no_transfer' LIMIT 1";
            $result_check = $connect->query($sql_check);
            if ($result_check && $result_check->num_rows > 0) {
                throw new Exception("Nomor transfer sudah ada");
            }
        }
        
        // 5/6. Insert ledger entries instead of direct saldo update
        include_once __DIR__ . '/../login/function/ledger_helpers.php';
        // Use explicit wallet debit/credit to ensure transfers only touch FREE wallet (pengguna.saldo)
        $ok1 = wallet_debit($connect, $db_id_pengirim, $nominal, 'Transfer to ' . $id_penerima_actual);
        if (!$ok1) {
            throw new Exception('Saldo tidak mencukupi untuk transfer atau gagal debit wallet');
        }
        $ok2 = wallet_credit($connect, $db_id_penerima, $nominal, 'Transfer from ' . $id_pengirim_actual);
        if (!$ok2) {
            // attempt to rollback sender debit by crediting back
            wallet_credit($connect, $db_id_pengirim, $nominal, 'Rollback failed transfer');
            throw new Exception('Gagal mengkredit penerima setelah debit');
        }
        
        // 7. Insert ke t_transfer
        // Insert into t_transfer only if table exists (some deployments don't have this table)
        $has_t_transfer = $connect->query("SHOW TABLES LIKE 't_transfer'");
        if ($has_t_transfer && $has_t_transfer->num_rows > 0) {
            $stmt = $connect->prepare("INSERT INTO t_transfer 
                        (no_transfer, id_pengirim, nama_pengirim, kelas_pengirim, id_penerima, nama_penerima, kelas_penerima, jumlah, keterangan, tanggal, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                error_log('add_transfer.php: prepare failed t_transfer: ' . $connect->error);
                throw new Exception('Terjadi kesalahan pada server (prepare transfer)');
            }
            $stmt->bind_param('sssssssdss', $no_transfer, $id_pengirim_actual, $nama_pengirim, $kelas_pengirim, $id_penerima_actual, $nama_penerima, $kelas_penerima, $nominal, $keterangan, $tanggal);
            $ok = $stmt->execute();
            if (!$ok) {
                error_log('add_transfer.php: execute failed t_transfer: ' . $stmt->error);
                $stmt->close();
                throw new Exception('Terjadi kesalahan pada server (insert transfer)');
            }
            $stmt->close();
        } else {
            // Not fatal: continue without t_transfer table
            error_log('add_transfer.php: t_transfer table missing, skipping insert');
        }
        
        // 8. Insert ke transaksi (untuk histori pengirim - keluar)
        // Insert transaksi records compatible with current schema (uses id_anggota)
        $saldo_sebelum_pengirim = $saldo_pengirim;
        $saldo_sesudah_pengirim = $saldo_pengirim - $nominal;
        $stmt2 = $connect->prepare("INSERT INTO transaksi (id_anggota, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, 'transfer_keluar', ?, ?, ?, ?, ?, 'approved')");
        if (!$stmt2) {
            error_log('add_transfer.php: prepare failed transaksi keluar: ' . $connect->error);
            throw new Exception('Terjadi kesalahan pada server (prepare transaksi keluar)');
        }
        $stmt2->bind_param('iddsss', $db_id_pengirim, $nominal, $saldo_sebelum_pengirim, $saldo_sesudah_pengirim, $keterangan, $tanggal);
        $ok = $stmt2->execute();
        if (!$ok) {
            error_log('add_transfer.php: execute failed transaksi keluar: ' . $stmt2->error);
            $stmt2->close();
            throw new Exception('Terjadi kesalahan pada server (insert transaksi keluar)');
        }
        $stmt2->close();
        
        // 9. Insert ke transaksi (untuk histori penerima - masuk)
        $saldo_sebelum_penerima = $saldo_penerima;
        $saldo_sesudah_penerima = $saldo_penerima + $nominal;
        $stmt3 = $connect->prepare("INSERT INTO transaksi (id_anggota, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, 'transfer_masuk', ?, ?, ?, ?, ?, 'approved')");
        if (!$stmt3) {
            error_log('add_transfer.php: prepare failed transaksi masuk: ' . $connect->error);
            throw new Exception('Terjadi kesalahan pada server (prepare transaksi masuk)');
        }
        $stmt3->bind_param('iddsss', $db_id_penerima, $nominal, $saldo_sebelum_penerima, $saldo_sesudah_penerima, $keterangan, $tanggal);
        $ok = $stmt3->execute();
        if (!$ok) {
            error_log('add_transfer.php: execute failed transaksi masuk: ' . $stmt3->error);
            $stmt3->close();
            throw new Exception('Terjadi kesalahan pada server (insert transaksi masuk)');
        }
        $stmt3->close();
        // 10. Insert notification for penerima so recipient receives an in-app notification
        // Create table if doesn't exist (non-fatal)
        $create_notif_table = "CREATE TABLE IF NOT EXISTS notifikasi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_pengguna INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            data JSON DEFAULT NULL,
            read_status TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $connect->query($create_notif_table);

        // Prepare notification content
        $nominal_formatted = 'Rp ' . number_format($nominal, 0, ',', '.');
        $title_notif = 'Transfer Masuk';
        $message_notif = "Anda menerima transfer $nominal_formatted dari {$nama_pengirim}.";
        $data_json = json_encode(array('no_transfer' => $no_transfer, 'nama_pengirim' => $nama_pengirim, 'nominal' => $nominal));

        // Use safe notification helper to filter and dedupe
        require_once __DIR__ . '/notif_helper.php';
        $notif_res = safe_create_notification($connect, $db_id_penerima, 'transaksi', $title_notif, $message_notif, $data_json);
        if ($notif_res === false) {
            // logged inside helper
            if ($stmt_notif) {
                // keep old stmt_notif variable context in case references remain
                try { @$stmt_notif->close(); } catch (Exception $_) {}
            }
        }
        
        // Commit transaction
        $connect->commit();
        
        echo json_encode(array(
            "success" => true,
            "message" => "Transfer berhasil",
            "data" => array(
                "no_transfer" => $no_transfer,
                "nama_pengirim" => $nama_pengirim,
                "nama_penerima" => $nama_penerima,
                "nominal" => $nominal,
                "saldo_baru" => $saldo_pengirim - $nominal,
                "tanggal" => $tanggal
            )
        ));
        
    } catch (Exception $e) {
        $connect->rollback();
        // Write debug info to log for diagnosis (safe for dev only)
        @file_put_contents(__DIR__ . '/add_transfer_error.log', date('c') . " ERROR: " . $e->getMessage() . " POST: " . json_encode($_POST) . " DBERR: " . $connect->error . "\n", FILE_APPEND);
        echo json_encode(array(
            "success" => false,
            "message" => "Transfer gagal: " . $e->getMessage()
        ));
    }
    
} else {
    echo json_encode(array(
        "success" => false,
        "message" => "Method not allowed. Use POST"
    ));
}
