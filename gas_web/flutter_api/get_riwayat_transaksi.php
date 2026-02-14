<?php
/**
 * API: Get Riwayat Transaksi (Semua Jenis)
 * Mengambil data dari tabel transaksi
 * Menampilkan semua jenis transaksi: setoran, penarikan, transfer_masuk, transfer_keluar
 * Filter: id_pengguna dan status = 'approved'
 * 
 * Timezone: Asia/Jakarta (UTC+7)
 * 
 * Params (GET/POST): id_pengguna
 */

// Set timezone ke Indonesia (UTC+7)
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Prevent PHP warnings/HTML from breaking JSON responses
ini_set('display_errors', '0');

ob_start();

include 'connection.php';

// Set MySQL timezone ke +07:00
if ($connect) {
    mysqli_query($connect, "SET time_zone = '+07:00'");
}

// Get id_pengguna from POST or GET
$id_pengguna = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : '';
if (empty($id_pengguna)) {
    $id_pengguna = isset($_GET['id_pengguna']) ? trim($_GET['id_pengguna']) : '';
}

// Validate id_pengguna
$id_pengguna = intval($id_pengguna);
if ($id_pengguna <= 0) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id_pengguna wajib diisi dan harus numerik'
    ]);
    exit();
}

if (empty($connect)) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    // Query all transaksi for this user
    $sql_trans = "SELECT id_transaksi, no_transaksi, id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status FROM transaksi WHERE id_pengguna = ? ORDER BY tanggal DESC";
    $stmt_trans = $connect->prepare($sql_trans);
    if (!$stmt_trans) {
        throw new Exception('Prepare failed: ' . $connect->error);
    }
    $stmt_trans->bind_param('i', $id_pengguna);
    if (!$stmt_trans->execute()) {
        throw new Exception('Execute failed: ' . $stmt_trans->error);
    }
    $result_trans = $stmt_trans->get_result();
    $data = [];
    
    while ($row = $result_trans->fetch_assoc()) {
        $jenis_trans = strtolower($row['jenis_transaksi']);
        $id_jenis_tabungan = null;
        $jenis_tabungan = 'Tabungan Reguler';  // default
        
        // LOGIC: Query dari tabungan_keluar atau tabungan_masuk untuk ambil jenis_tabungan yang benar
        if ($jenis_trans == 'penarikan') {
            // STRATEGY: Try multiple methods to find the correct tabungan_keluar row
            // 1. Extract tabungan_keluar_id directly from keterangan (most accurate)
            // 2. Extract mulai_nabung ID from keterangan
            // 3. Fallback to closest timestamp match (least accurate)
            
            $tab_keluar_id = null;
            $mulai_nabung_id = null;
            
            // Method 1: Extract tabungan_keluar_id from keterangan
            if (preg_match('/tabungan_keluar_id=(\d+)/i', $row['keterangan'] ?? '', $matches_tk)) {
                $tab_keluar_id = intval($matches_tk[1]);
            }
            // Also try extracting from TK- format (no_keluar field)
            if ($tab_keluar_id === null && preg_match('/TK-(\d+)/i', $row['keterangan'] ?? '', $matches_tk2)) {
                $tab_keluar_id = intval($matches_tk2[1]);
            }
            
            // Method 2: Extract mulai_nabung ID from keterangan
            if (preg_match('/mulai_nabung\s+(\d+)/i', $row['keterangan'] ?? '', $matches)) {
                $mulai_nabung_id = intval($matches[1]);
            }
            
            $found_jenis = false;
            
            // Try Method 1: Direct tabungan_keluar_id lookup (most accurate)
            if ($tab_keluar_id > 0 && !$found_jenis) {
                $sql_detail = "SELECT tk.id_jenis_tabungan, jt.nama_jenis 
                              FROM tabungan_keluar tk
                              LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                              WHERE tk.id = ?
                              LIMIT 1";
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('i', $tab_keluar_id);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            $id_jenis_tabungan = intval($row_detail['id_jenis_tabungan']);
                            if (!empty($row_detail['nama_jenis'])) {
                                $jenis_tabungan = $row_detail['nama_jenis'];
                                $found_jenis = true;
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            }
            
            // Try Method 2: mulai_nabung JOIN
            if ($mulai_nabung_id > 0 && !$found_jenis) {
                $sql_detail = "SELECT COALESCE(mn.jenis_tabungan, jt.nama_jenis) as jenis_name
                              FROM tabungan_keluar tk
                              LEFT JOIN mulai_nabung mn ON mn.id_mulai_nabung = ?
                              LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                              WHERE tk.id_pengguna = ?
                              LIMIT 1";
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('ii', $mulai_nabung_id, $id_pengguna);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            if (!empty($row_detail['jenis_name'])) {
                                $jenis_tabungan = $row_detail['jenis_name'];
                                $found_jenis = true;
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            }
            
            // Method 3 Fallback: closest timestamp match
            if (!$found_jenis) {
                $sql_detail = "SELECT tk.id_jenis_tabungan, jt.nama_jenis 
                              FROM tabungan_keluar tk
                              LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                              WHERE tk.id_pengguna = ?
                              ORDER BY ABS(UNIX_TIMESTAMP(tk.created_at) - UNIX_TIMESTAMP(?))
                              LIMIT 1";
                
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('is', $id_pengguna, $row['tanggal']);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            $id_jenis_tabungan = intval($row_detail['id_jenis_tabungan']);
                            if (!empty($row_detail['nama_jenis'])) {
                                $jenis_tabungan = $row_detail['nama_jenis'];
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            }
            
        } elseif ($jenis_trans == 'setoran') {
            // Query from mulai_nabung (direct) or tabungan_masuk (fallback)
            // Try to extract mulai_nabung ID from keterangan first for accurate matching
            $mulai_nabung_id = null;
            if (preg_match('/mulai_nabung\s+(\d+)/i', $row['keterangan'] ?? '', $matches)) {
                $mulai_nabung_id = intval($matches[1]);
            }
            
            if ($mulai_nabung_id > 0) {
                // Use mulai_nabung table directly for accurate jenis_tabungan
                $sql_detail = "SELECT jenis_tabungan FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1";
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('i', $mulai_nabung_id);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            if (!empty($row_detail['jenis_tabungan'])) {
                                $jenis_tabungan = $row_detail['jenis_tabungan'];
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            } else {
                // Fallback: Query from tabungan_masuk with closest timestamp match
                $sql_detail = "SELECT tm.id_jenis_tabungan, jt.nama_jenis 
                              FROM tabungan_masuk tm
                              LEFT JOIN jenis_tabungan jt ON jt.id = tm.id_jenis_tabungan
                              WHERE tm.id_pengguna = ?
                              ORDER BY ABS(UNIX_TIMESTAMP(tm.created_at) - UNIX_TIMESTAMP(?))
                              LIMIT 1";
                
                $stmt_detail = $connect->prepare($sql_detail);
                if ($stmt_detail) {
                    $stmt_detail->bind_param('is', $id_pengguna, $row['tanggal']);
                    if ($stmt_detail->execute()) {
                        $res = $stmt_detail->get_result();
                        if ($res && $res->num_rows > 0) {
                            $row_detail = $res->fetch_assoc();
                            $id_jenis_tabungan = intval($row_detail['id_jenis_tabungan']);
                            if (!empty($row_detail['nama_jenis'])) {
                                $jenis_tabungan = $row_detail['nama_jenis'];
                            }
                        }
                    }
                    $stmt_detail->close();
                }
            }
        }
        
        // Normalize status display: 'ditolak' -> 'rejected', 'pending'/'proses' for consistency
        $status_display = $row['status'];
        if (strtolower($status_display) === 'ditolak') {
            $status_display = 'rejected';
        } elseif (strtolower($status_display) === 'proses') {
            $status_display = 'pending';
        }
        
        error_log('[DEBUG] TX: id=' . $row['id_transaksi'] . ' jenis_tabungan=' . $jenis_tabungan . ' tanggal=' . $row['tanggal'] . ' timezone=UTC+7');
        
        // Ensure tanggal is in proper WIB format (Asia/Jakarta UTC+7)
        // MySQL SET time_zone = '+07:00' already converts TIMESTAMP columns to WIB,
        // but we also do explicit PHP conversion as safety net
        $tanggal_final = $row['tanggal'];
        
        // For pinjaman entries, transaksi.tanggal was inserted with PHP date() in UTC
        // so the TIMESTAMP value is wrong. Use pinjaman detail table's created_at (DATETIME = correct WIB)
        if ($jenis_trans == 'pinjaman_biasa' || $jenis_trans == 'pinjaman') {
            $pb_time_sql = "SELECT created_at FROM pinjaman_biasa WHERE id_pengguna = ? AND jumlah_pinjaman = ? ORDER BY ABS(UNIX_TIMESTAMP(created_at) - UNIX_TIMESTAMP(?)) LIMIT 1";
            $pb_time_stmt = $connect->prepare($pb_time_sql);
            if ($pb_time_stmt) {
                $pb_jumlah = (int)$row['jumlah'];
                $pb_time_stmt->bind_param('iis', $id_pengguna, $pb_jumlah, $row['tanggal']);
                if ($pb_time_stmt->execute()) {
                    $pb_time_res = $pb_time_stmt->get_result();
                    if ($pb_time_row = $pb_time_res->fetch_assoc()) {
                        if (!empty($pb_time_row['created_at'])) {
                            $tanggal_final = $pb_time_row['created_at'];
                        }
                    }
                }
                $pb_time_stmt->close();
            }
        } elseif ($jenis_trans == 'pinjaman_kredit') {
            $pk_time_sql = "SELECT created_at FROM pinjaman_kredit WHERE id_pengguna = ? AND harga = ? ORDER BY ABS(UNIX_TIMESTAMP(created_at) - UNIX_TIMESTAMP(?)) LIMIT 1";
            $pk_time_stmt = $connect->prepare($pk_time_sql);
            if ($pk_time_stmt) {
                $pk_jumlah = (int)$row['jumlah'];
                $pk_time_stmt->bind_param('iis', $id_pengguna, $pk_jumlah, $row['tanggal']);
                if ($pk_time_stmt->execute()) {
                    $pk_time_res = $pk_time_stmt->get_result();
                    if ($pk_time_row = $pk_time_res->fetch_assoc()) {
                        if (!empty($pk_time_row['created_at'])) {
                            $tanggal_final = $pk_time_row['created_at'];
                        }
                    }
                }
                $pk_time_stmt->close();
            }
        } elseif ($jenis_trans == 'transfer_keluar' || $jenis_trans == 'transfer_masuk') {
            // For transfer entries, transaksi.tanggal may only have date (no time).
            // Fetch the accurate created_at from t_transfer table which stores full datetime.
            $tf_time_sql = "SELECT created_at FROM t_transfer WHERE (id_pengirim = ? OR id_penerima = ?) AND DATE(tanggal) = DATE(?) ORDER BY ABS(UNIX_TIMESTAMP(created_at) - UNIX_TIMESTAMP(?)) LIMIT 1";
            $tf_time_stmt = $connect->prepare($tf_time_sql);
            if ($tf_time_stmt) {
                $tf_id_str = strval($id_pengguna);
                $tf_time_stmt->bind_param('ssss', $tf_id_str, $tf_id_str, $row['tanggal'], $row['tanggal']);
                if ($tf_time_stmt->execute()) {
                    $tf_time_res = $tf_time_stmt->get_result();
                    if ($tf_time_row = $tf_time_res->fetch_assoc()) {
                        if (!empty($tf_time_row['created_at'])) {
                            $tanggal_final = $tf_time_row['created_at'];
                        }
                    }
                }
                $tf_time_stmt->close();
            }
        }
        if (!empty($tanggal_final)) {
            try {
                // Parse the timestamp from MySQL (already in +07:00 session)
                $dt = new DateTime($tanggal_final, new DateTimeZone('Asia/Jakarta'));
                $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
                $tanggal_final = $dt->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Fallback: keep original value
                if (strlen($tanggal_final) === 10 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_final)) {
                    $tanggal_final = $tanggal_final . ' 00:00:00';
                }
            }
        } else {
            $tanggal_final = date('Y-m-d H:i:s'); // fallback to current WIB time
        }
        
        $data[] = [
            'id' => (int)$row['id_transaksi'],
            'id_transaksi' => (int)$row['id_transaksi'],
            'no_transaksi' => $row['no_transaksi'] ?? '',
            'id_pengguna' => (int)$row['id_pengguna'],
            'jenis_transaksi' => $row['jenis_transaksi'],
            'id_jenis_tabungan' => $id_jenis_tabungan,
            'jumlah' => (int)$row['jumlah'],
            'saldo_sebelum' => (int)$row['saldo_sebelum'],
            'saldo_sesudah' => (int)$row['saldo_sesudah'],
            'keterangan' => $row['keterangan'] ?? '',
            'created_at' => $tanggal_final,
            'status' => $status_display,
            'jenis_tabungan' => $jenis_tabungan,
            'jenis_pinjaman' => ($jenis_trans === 'pinjaman_biasa') ? 'biasa' : (($jenis_trans === 'pinjaman_kredit') ? 'kredit' : null)
        ];
    }
    $stmt_trans->close();

    // Track which pinjaman already have records in transaksi table (from STEP 1)
    // to avoid duplicates in STEP 2 and STEP 3
    $pinjaman_biasa_in_transaksi = [];
    $pinjaman_kredit_in_transaksi = [];
    foreach ($data as $d) {
        $jt = $d['jenis_transaksi'] ?? '';
        if ($jt === 'pinjaman_biasa') {
            $pinjaman_biasa_in_transaksi[] = $d['id_transaksi'];
        } elseif ($jt === 'pinjaman_kredit') {
            $pinjaman_kredit_in_transaksi[] = $d['id_transaksi'];
        }
    }

    // STEP 2: Fetch pinjaman biasa (approved/rejected) from pinjaman_biasa table
    // Only add if NOT already present in transaksi table (legacy data fallback)
    try {
        $sql_pinjaman_biasa = "SELECT id, id_pengguna, jumlah_pinjaman AS jumlah, tenor, tujuan_penggunaan, status, created_at FROM pinjaman_biasa WHERE id_pengguna = ? AND status IN ('approved', 'rejected') ORDER BY created_at DESC";
        $stmt_pb = $connect->prepare($sql_pinjaman_biasa);
        if ($stmt_pb) {
            $stmt_pb->bind_param('i', $id_pengguna);
            if ($stmt_pb->execute()) {
                $result_pb = $stmt_pb->get_result();
                while ($row_pb = $result_pb->fetch_assoc()) {
                    // Check if this pinjaman_biasa already has a record in transaksi table
                    // If so, skip it (already shown from STEP 1)
                    $pb_amount = (int)$row_pb['jumlah'];
                    $pb_created = $row_pb['created_at'] ?? '';
                    $already_in_transaksi = false;
                    
                    // Try to find matching transaksi record by jenis + pengguna + jumlah
                    $check_sql = "SELECT id_transaksi FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'pinjaman_biasa' AND jumlah = ? ORDER BY id_transaksi DESC LIMIT 1";
                    $check_stmt = $connect->prepare($check_sql);
                    $real_id_transaksi = null;
                    if ($check_stmt) {
                        $check_stmt->bind_param('id', $id_pengguna, $pb_amount);
                        if ($check_stmt->execute()) {
                            $check_result = $check_stmt->get_result();
                            if ($check_row = $check_result->fetch_assoc()) {
                                $real_id_transaksi = (int)$check_row['id_transaksi'];
                                // Check if already in our data array from STEP 1
                                if (in_array($real_id_transaksi, $pinjaman_biasa_in_transaksi)) {
                                    $already_in_transaksi = true;
                                }
                            }
                        }
                        $check_stmt->close();
                    }
                    
                    if ($already_in_transaksi) {
                        continue; // Skip - already included from STEP 1
                    }

                    $pb_status = strtolower($row_pb['status']);
                    $status_display_pb = ($pb_status === 'approved') ? 'approved' : 'rejected';
                    
                    $tanggal_pb = $row_pb['created_at'];
                    if (!empty($tanggal_pb)) {
                        try {
                            $dt_pb = new DateTime($tanggal_pb, new DateTimeZone('Asia/Jakarta'));
                            $dt_pb->setTimezone(new DateTimeZone('Asia/Jakarta'));
                            $tanggal_pb = $dt_pb->format('Y-m-d H:i:s');
                        } catch (Exception $e) {}
                    }
                    
                    $amountStr_pb = 'Rp ' . number_format((int)$row_pb['jumlah'], 0, ',', '.');
                    $tenor_pb = (int)($row_pb['tenor'] ?? 0);
                    $tenorStr_pb = $tenor_pb > 0 ? ' untuk tenor ' . $tenor_pb . ' bulan' : '';
                    
                    // Try to get catatan_admin if column exists
                    $catatan_pb = '';
                    if (isset($row_pb['catatan_admin'])) {
                        $catatan_pb = $row_pb['catatan_admin'] ?? '';
                    }
                    
                    if ($status_display_pb === 'approved') {
                        $keterangan_pb = 'Pengajuan Pinjaman Biasa Anda sebesar ' . $amountStr_pb . $tenorStr_pb . ' disetujui, silahkan cek saldo anda di halaman dashboard.';
                    } else {
                        $keterangan_pb = 'Pengajuan Pinjaman Biasa Anda sebesar ' . $amountStr_pb . $tenorStr_pb . ' ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
                        if (!empty($catatan_pb)) {
                            $keterangan_pb .= ' Alasan: ' . trim($catatan_pb);
                        }
                    }
                    
                    $data[] = [
                        'id' => $real_id_transaksi ? $real_id_transaksi : (int)$row_pb['id'],
                        'id_transaksi' => $real_id_transaksi ? (int)$real_id_transaksi : 'pinjaman_biasa_' . (int)$row_pb['id'],
                        'id_pengguna' => (int)$row_pb['id_pengguna'],
                        'jenis_transaksi' => 'pinjaman',
                        'id_jenis_tabungan' => null,
                        'jumlah' => (int)$row_pb['jumlah'],
                        'saldo_sebelum' => 0,
                        'saldo_sesudah' => 0,
                        'keterangan' => $keterangan_pb,
                        'created_at' => $tanggal_pb,
                        'status' => $status_display_pb,
                        'jenis_tabungan' => '',
                        'tenor' => $tenor_pb,
                        'tujuan_penggunaan' => $row_pb['tujuan_penggunaan'] ?? '',
                        'jenis_pinjaman' => 'biasa'
                    ];
                }
            }
            $stmt_pb->close();
        }
    } catch (Exception $e) {
        // Non-fatal: pinjaman_biasa query failure shouldn't break the entire response
        error_log('[get_riwayat_transaksi] Pinjaman biasa query error: ' . $e->getMessage());
    }

    // STEP 3: Fetch pinjaman kredit (approved/rejected) from pinjaman_kredit table
    try {
        $sql_pinjaman_kredit = "SELECT id, id_pengguna, harga AS jumlah, nama_barang, tenor, cicilan_per_bulan, total_bayar, dp, pokok, status, created_at FROM pinjaman_kredit WHERE id_pengguna = ? AND status IN ('approved', 'rejected') ORDER BY created_at DESC";
        $stmt_pk = $connect->prepare($sql_pinjaman_kredit);
        if ($stmt_pk) {
            $stmt_pk->bind_param('i', $id_pengguna);
            if ($stmt_pk->execute()) {
                $result_pk = $stmt_pk->get_result();
                while ($row_pk = $result_pk->fetch_assoc()) {
                    // Check if this pinjaman_kredit already has a record in transaksi table
                    $pk_amount = (int)$row_pk['jumlah'];
                    $already_in_transaksi_pk = false;
                    
                    $check_sql_pk = "SELECT id_transaksi FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'pinjaman_kredit' AND jumlah = ? ORDER BY id_transaksi DESC LIMIT 1";
                    $check_stmt_pk = $connect->prepare($check_sql_pk);
                    $real_id_transaksi_pk = null;
                    if ($check_stmt_pk) {
                        $check_stmt_pk->bind_param('id', $id_pengguna, $pk_amount);
                        if ($check_stmt_pk->execute()) {
                            $check_result_pk = $check_stmt_pk->get_result();
                            if ($check_row_pk = $check_result_pk->fetch_assoc()) {
                                $real_id_transaksi_pk = (int)$check_row_pk['id_transaksi'];
                                if (in_array($real_id_transaksi_pk, $pinjaman_kredit_in_transaksi)) {
                                    $already_in_transaksi_pk = true;
                                }
                            }
                        }
                        $check_stmt_pk->close();
                    }
                    
                    if ($already_in_transaksi_pk) {
                        continue; // Skip - already included from STEP 1
                    }

                    $pk_status = strtolower($row_pk['status']);
                    $status_display_pk = ($pk_status === 'approved') ? 'approved' : 'rejected';
                    
                    $tanggal_pk = $row_pk['created_at'];
                    if (!empty($tanggal_pk)) {
                        try {
                            $dt_pk = new DateTime($tanggal_pk, new DateTimeZone('Asia/Jakarta'));
                            $dt_pk->setTimezone(new DateTimeZone('Asia/Jakarta'));
                            $tanggal_pk = $dt_pk->format('Y-m-d H:i:s');
                        } catch (Exception $e) {}
                    }
                    
                    $amountStr_pk = 'Rp ' . number_format((int)$row_pk['jumlah'], 0, ',', '.');
                    $tenor_pk = (int)($row_pk['tenor'] ?? 0);
                    $tenorStr_pk = $tenor_pk > 0 ? ' untuk tenor ' . $tenor_pk . ' bulan' : '';
                    $namaBarang = $row_pk['nama_barang'] ?? '';
                    
                    // Try to get catatan_admin if column exists
                    $catatan_pk = '';
                    if (isset($row_pk['catatan_admin'])) {
                        $catatan_pk = $row_pk['catatan_admin'] ?? '';
                    }
                    
                    if ($status_display_pk === 'approved') {
                        $keterangan_pk = 'Pengajuan Pinjaman Kredit' . (!empty($namaBarang) ? ' (' . $namaBarang . ')' : '') . ' sebesar ' . $amountStr_pk . $tenorStr_pk . ' disetujui.';
                    } else {
                        $keterangan_pk = 'Pengajuan Pinjaman Kredit' . (!empty($namaBarang) ? ' (' . $namaBarang . ')' : '') . ' sebesar ' . $amountStr_pk . $tenorStr_pk . ' ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
                        if (!empty($catatan_pk)) {
                            $keterangan_pk .= ' Alasan: ' . trim($catatan_pk);
                        }
                    }
                    
                    $data[] = [
                        'id' => $real_id_transaksi_pk ? $real_id_transaksi_pk : (int)$row_pk['id'],
                        'id_transaksi' => $real_id_transaksi_pk ? (int)$real_id_transaksi_pk : 'pinjaman_kredit_' . (int)$row_pk['id'],
                        'id_pengguna' => (int)$row_pk['id_pengguna'],
                        'jenis_transaksi' => 'pinjaman',
                        'id_jenis_tabungan' => null,
                        'jumlah' => (int)$row_pk['jumlah'],
                        'saldo_sebelum' => 0,
                        'saldo_sesudah' => 0,
                        'keterangan' => $keterangan_pk,
                        'created_at' => $tanggal_pk,
                        'status' => $status_display_pk,
                        'jenis_tabungan' => '',
                        'tenor' => $tenor_pk,
                        'nama_barang' => $namaBarang,
                        'jenis_pinjaman' => 'kredit'
                    ];
                }
            }
            $stmt_pk->close();
        }
    } catch (Exception $e) {
        // Non-fatal: pinjaman_kredit query failure shouldn't break the entire response
        error_log('[get_riwayat_transaksi] Pinjaman kredit query error: ' . $e->getMessage());
    }

    // Sort all data by created_at DESC
    usort($data, function($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'total' => count($data),
            'timestamp' => date('c'),
            'timezone' => 'Asia/Jakarta (UTC+7)'
        ]
    ]);
    exit();

} catch (Exception $e) {
    @file_put_contents(
        __DIR__ . '/api_debug.log',
        date('c') . " [get_riwayat_transaksi] Error: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
    exit();
}
?>
