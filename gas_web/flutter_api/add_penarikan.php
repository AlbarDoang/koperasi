<?php 
/**
 * API: Add Penarikan (Petugas/Teller)
 * Untuk petugas proses penarikan tunai (pending approval admin)
 */
include 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required_fields = ['id_pengguna', 'jumlah', 'id_petugas'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(array(
                "success" => false,
                "message" => "Field $field wajib diisi"
            ));
            exit();
        }
    }
    
    $id_pengguna = $connect->real_escape_string($_POST['id_pengguna']);
    $jumlah = floatval($_POST['jumlah']);
    $id_petugas = intval($_POST['id_petugas']);
    $keterangan = isset($_POST['keterangan']) ? $connect->real_escape_string($_POST['keterangan']) : 'Penarikan Tunai';
    $tanggal = date('Y-m-d');
    
    // DEBUG LOGGING: Log id_pengguna untuk verifikasi refactor
    error_log('[DEBUG] add_penarikan.php: Processing id_pengguna=' . $id_pengguna . ', jumlah=' . $jumlah);
    
    // Validasi jumlah
    if ($jumlah <= 0) {
        echo json_encode(array(
            "success" => false,
            "message" => "Jumlah penarikan harus lebih dari 0"
        ));
        exit();
    }
    
    // Cek anggota dan saldo — tahan perbedaan skema pada tabel `pengguna`
    $colsRes = $connect->query("SHOW COLUMNS FROM pengguna");
    $fields = [];
    while ($cr = $colsRes->fetch_assoc()) $fields[] = $cr['Field'];

    // Prepare commonly used SELECT fragments
    $select_name = in_array('nama', $fields) ? 'nama' : (in_array('nama_lengkap', $fields) ? 'nama_lengkap' : "'' as nama");
    $select_saldo = in_array('saldo', $fields) ? 'saldo' : '0 as saldo';

    // Determine lookup column for the incoming identifier (id_pengguna param)
    // If caller provided a numeric id, prefer lookup by id regardless of which columns exist
    if (ctype_digit(trim((string)$_POST['id_pengguna']))) {
        $rawId = trim((string)$_POST['id_pengguna']);
        $sql_check = "SELECT id AS id_pengguna, {$select_name} as nama, '' as nis, {$select_saldo} as saldo FROM pengguna WHERE id='" . intval($rawId) . "'";
    } else if (in_array('id_pengguna', $fields)) {
        $lookup_col = 'id_pengguna';
        $select_nis = in_array('nis', $fields) ? 'nis' : (in_array('no_hp', $fields) ? 'no_hp as nis' : "'' as nis");
        $sql_check = "SELECT {$lookup_col}, {$select_name} as nama, {$select_nis} as nis, {$select_saldo} as saldo FROM pengguna WHERE {$lookup_col}='" . $connect->real_escape_string($id_pengguna) . "'";
    } else if (in_array('nis', $fields) || in_array('no_hp', $fields)) {
        $col = in_array('nis', $fields) ? 'nis' : 'no_hp';
        $sql_check = "SELECT id AS id_pengguna, {$select_name} as nama, {$col} as nis, {$select_saldo} as saldo FROM pengguna WHERE {$col}='" . $connect->real_escape_string($id_pengguna) . "'";
    } else {
        // fallback to numeric id (if everything else fails)
        $sql_check = "SELECT id AS id_pengguna, {$select_name} as nama, '' as nis, {$select_saldo} as saldo FROM pengguna WHERE id='" . intval($id_pengguna) . "'";
    }

    // Active-check: be tolerant with different active values and column names.
    if (in_array('status', $fields)) {
        $sql_check .= " AND (LOWER(status) = 'aktif' OR LOWER(status) LIKE '%aktif%' OR LOWER(status) LIKE '%verifik%' OR status = '1' OR LOWER(status) = 'active')";
    } else if (in_array('status_akun', $fields)) {
        $sql_check .= " AND (LOWER(status_akun) = 'aktif' OR LOWER(status_akun) LIKE '%aktif%' OR LOWER(status_akun) LIKE '%verifik%' OR status_akun = '1' OR LOWER(status_akun) = 'active')";
    }

    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [add_penarikan] SQL_CHECK: " . $sql_check . "\n", FILE_APPEND);
    $result_check = $connect->query($sql_check);

    if ($result_check->num_rows == 0) {
        echo json_encode(array(
            "success" => false,
            "message" => "Anggota tidak ditemukan atau tidak aktif"
        ));
        exit();
    }

    $anggota = $result_check->fetch_assoc();
    $nama_anggota = isset($anggota['nama']) ? $anggota['nama'] : '';
    $nis = isset($anggota['nis']) ? $anggota['nis'] : '';
    $saldo_current = isset($anggota['saldo']) ? $anggota['saldo'] : 0;
    
    // Validasi saldo cukup (aplikasi-level check, trigger DB juga melindungi)
    if ($saldo_current < $jumlah) {
        echo json_encode(array(
            "success" => false,
            "message" => "Saldo tidak mencukupi. Saldo tersedia: Rp. " . number_format($saldo_current, 0, ',', '.')
        ));
        exit();
    }
    
    // Generate nomor penarikan
    $no_keluar = 'PK-' . date('YmdHis') . '-' . $id_pengguna;
    
    // Start transaction
    $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true; // signal connection.php not to inject fallback
    $connect->begin_transaction();
    try {
        // Lock user row to avoid races (SELECT ... FOR UPDATE)
        $userId = intval($anggota['id'] ?? $anggota['id_pengguna']);
        $stmtLock = $connect->prepare("SELECT id FROM pengguna WHERE id = ? FOR UPDATE");
        $stmtLock->bind_param('i', $userId);
        $stmtLock->execute();
        $stmtLock->close();

        // Decide where to write: prefer t_keluar (legacy pending table) if present
        $has_t_keluar = false;
        $chk = $connect->query("SHOW TABLES LIKE 't_keluar'");
        if ($chk && $chk->num_rows > 0) $has_t_keluar = true;

        if ($has_t_keluar) {
            $sql = "INSERT INTO t_keluar (no_keluar, nama, id_tabungan, kelas, tanggal, jumlah, created_at, keterangan, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, 'pending')";
            $stmt = $connect->prepare($sql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $connect->error);
            $kelas = '-';
            $tanggal_full = $tanggal;
            $stmt->bind_param('ssssdss', $no_keluar, $nama_anggota, $nis, $kelas, $jumlah, $tanggal_full, $keterangan);
            if (!$stmt->execute()) {
                $sqlstate = mysqli_sqlstate($connect) ?: null;
                $dberr = $stmt->error ?: $connect->error;
                if ($sqlstate === '45000') throw new Exception($dberr ?: 'Saldo tidak mencukupi');
                throw new Exception('Gagal menulis t_keluar: ' . $dberr);
            }
            $stmt->close();
        } else {
            // Insert into tabungan_keluar with keterangan set to indicate PENDING state
            // Need to provide id_jenis_tabungan; pick default 1 (Reguler) if exists
            $defaultJenis = 1;
            // Validate default jenis exists
            $r = $connect->query("SELECT id FROM jenis_tabungan WHERE id = 1 LIMIT 1");
            if (!$r || $r->num_rows == 0) {
                // fallback: use first available jenis_tabungan
                $rr = $connect->query("SELECT id FROM jenis_tabungan LIMIT 1");
                if ($rr && $rr->num_rows > 0) {
                    $first = $rr->fetch_assoc();
                    $defaultJenis = intval($first['id']);
                } else {
                    throw new Exception('Tidak ada jenis_tabungan tersedia');
                }
            }
            $pendingKeterangan = 'pending: ' . $keterangan;
            $sql = "INSERT INTO tabungan_keluar (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at) VALUES (?, ?, ?, ?, NOW())";
            $stmt = $connect->prepare($sql);
            if (!$stmt) throw new Exception('Prepare failed: ' . $connect->error);
            $stmt->bind_param('iiis', $userId, $defaultJenis, $jumlah, $pendingKeterangan);
            if (!$stmt->execute()) {
                $dberr = $stmt->error ?: $connect->error;
                throw new Exception('Gagal menulis tabungan_keluar: ' . $dberr);
            }
            $stmt->close();
        }

        $connect->commit();

        echo json_encode(array(
            'success' => true,
            'message' => 'Penarikan berhasil diajukan, menunggu approval admin',
            'data' => array('no_keluar' => $no_keluar, 'nama' => $nama_anggota, 'jumlah' => $jumlah, 'saldo_current' => $saldo_current, 'tanggal' => $tanggal, 'status' => 'pending')
        ));

    } catch (Exception $e) {
        $connect->rollback();
        echo json_encode(array('success' => false, 'message' => 'Gagal menyimpan penarikan: ' . $e->getMessage()));
    }
    
} else {
    echo json_encode(array(
        "success" => false,
        "message" => "Method not allowed. Use POST"
    ));
}

