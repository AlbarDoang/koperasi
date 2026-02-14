<?php
/**
 * API: Get Detail Transaksi
 * Method: GET/POST
 * 
 * Parameters:
 * - id_transaksi (required): ID dari tabel transaksi
 *   OR
 * - pinjaman_biasa_id: ID dari tabel pinjaman_biasa (fallback lookup)
 * - pinjaman_kredit_id: ID dari tabel pinjaman_kredit (fallback lookup)
 * 
 * Logic:
 * 1. Query data dari tabel transaksi berdasarkan id_transaksi
 * 2. Ambil jenis_transaksi dan id_pengguna
 * 3. Conditional:
 *    - Jika jenis_transaksi == 'penarikan': query tabungan_keluar JOIN jenis_tabungan
 *    - Jika jenis_transaksi == 'setoran': query tabungan_masuk JOIN jenis_tabungan
 *    - Jika jenis_transaksi == 'pinjaman_biasa': query pinjaman_biasa
 *    - Jika jenis_transaksi == 'pinjaman_kredit': query pinjaman_kredit
 * 4. Return: jumlah, status, keterangan, created_at, jenis_pinjaman, tenor, etc.
 * 
 * Timezone: Asia/Jakarta (UTC+7)
 */

// Set timezone ke Indonesia (UTC+7)
date_default_timezone_set('Asia/Jakarta');

// Prevent PHP warnings/HTML from breaking JSON responses
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ob_start();

// Include database connection
include 'connection.php';

// Set MySQL timezone ke +07:00
if ($connect) {
    mysqli_query($connect, "SET time_zone = '+07:00'");
}

// Get id_transaksi from GET or POST
$id_transaksi = isset($_POST['id_transaksi']) ? trim($_POST['id_transaksi']) : '';
if (empty($id_transaksi)) {
    $id_transaksi = isset($_GET['id_transaksi']) ? trim($_GET['id_transaksi']) : '';
}

// Also accept pinjaman_biasa_id and pinjaman_kredit_id for direct lookup
$pinjaman_biasa_id = isset($_REQUEST['pinjaman_biasa_id']) ? intval($_REQUEST['pinjaman_biasa_id']) : 0;
$pinjaman_kredit_id = isset($_REQUEST['pinjaman_kredit_id']) ? intval($_REQUEST['pinjaman_kredit_id']) : 0;

// Handle id_transaksi that might be prefixed (e.g., "pinjaman_biasa_111")
$resolved_pinjaman_biasa_id = 0;
$resolved_pinjaman_kredit_id = 0;
if (!empty($id_transaksi) && !is_numeric($id_transaksi)) {
    if (preg_match('/^pinjaman_biasa_(\d+)$/', $id_transaksi, $m)) {
        $resolved_pinjaman_biasa_id = intval($m[1]);
        $id_transaksi = '0'; // Not a real transaksi ID
    } elseif (preg_match('/^pinjaman_kredit_(\d+)$/', $id_transaksi, $m)) {
        $resolved_pinjaman_kredit_id = intval($m[1]);
        $id_transaksi = '0'; // Not a real transaksi ID
    }
}

// Use explicit parameter if provided
if ($pinjaman_biasa_id > 0) $resolved_pinjaman_biasa_id = $pinjaman_biasa_id;
if ($pinjaman_kredit_id > 0) $resolved_pinjaman_kredit_id = $pinjaman_kredit_id;

$id_transaksi = intval($id_transaksi);

// Validate: at least one lookup ID must be provided
if ($id_transaksi <= 0 && $resolved_pinjaman_biasa_id <= 0 && $resolved_pinjaman_kredit_id <= 0) {
    http_response_code(400);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Parameter id_transaksi wajib diisi dan harus numerik'
    ]);
    exit();
}

if (empty($connect)) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

try {
    $trans_row = null;
    $jenis_transaksi = '';
    $id_pengguna = 0;
    
    // STEP 1: Try to find in transaksi table first
    if ($id_transaksi > 0) {
        $sql_trans = "SELECT id_transaksi, no_transaksi, id_pengguna, jenis_transaksi, jumlah, tanggal, status, keterangan 
                      FROM transaksi 
                      WHERE id_transaksi = ? 
                      LIMIT 1";
        
        $stmt_trans = $connect->prepare($sql_trans);
        if (!$stmt_trans) {
            throw new Exception('Prepare statement failed: ' . $connect->error);
        }
        
        $stmt_trans->bind_param('i', $id_transaksi);
        if (!$stmt_trans->execute()) {
            throw new Exception('Query failed: ' . $stmt_trans->error);
        }
        
        $result_trans = $stmt_trans->get_result();
        if ($result_trans && $result_trans->num_rows > 0) {
            $trans_row = $result_trans->fetch_assoc();
            $jenis_transaksi = strtolower($trans_row['jenis_transaksi']);
            $id_pengguna = intval($trans_row['id_pengguna']);
        }
        $stmt_trans->close();
    }
    
    // STEP 1B: If not found in transaksi, try direct pinjaman lookup
    // Also try using the id_transaksi as pinjaman_biasa.id (when notification passes pinjaman table ID)
    $lookup_pb_id = $resolved_pinjaman_biasa_id > 0 ? $resolved_pinjaman_biasa_id : ($trans_row === null && $id_transaksi > 0 ? $id_transaksi : 0);
    $lookup_pk_id = $resolved_pinjaman_kredit_id > 0 ? $resolved_pinjaman_kredit_id : 0;
    
    if ($trans_row === null && $lookup_pb_id > 0) {
        // Lookup pinjaman_biasa directly
        $sql_pb = "SELECT id, id_pengguna, jumlah_pinjaman AS jumlah, tenor, tujuan_penggunaan, status, created_at
                   FROM pinjaman_biasa WHERE id = ? LIMIT 1";
        $stmt_pb = $connect->prepare($sql_pb);
        if ($stmt_pb) {
            $stmt_pb->bind_param('i', $lookup_pb_id);
            if ($stmt_pb->execute()) {
                $res_pb = $stmt_pb->get_result();
                if ($res_pb && $res_pb->num_rows > 0) {
                    $row_pb = $res_pb->fetch_assoc();
                    
                    // Try to find matching transaksi record for correct id_transaksi
                    $real_id_transaksi = null;
                    $check_sql = "SELECT id_transaksi, tanggal, keterangan FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi IN ('pinjaman_biasa', 'pinjaman') AND jumlah = ? ORDER BY id_transaksi DESC LIMIT 1";
                    $check_stmt = $connect->prepare($check_sql);
                    if ($check_stmt) {
                        $pb_jumlah = (int)$row_pb['jumlah'];
                        $check_stmt->bind_param('ii', $row_pb['id_pengguna'], $pb_jumlah);
                        if ($check_stmt->execute()) {
                            $check_res = $check_stmt->get_result();
                            if ($check_row = $check_res->fetch_assoc()) {
                                $real_id_transaksi = (int)$check_row['id_transaksi'];
                            }
                        }
                        $check_stmt->close();
                    }
                    
                    $pb_status = strtolower($row_pb['status']);
                    $status_display = ($pb_status === 'approved') ? 'approved' : (($pb_status === 'rejected') ? 'rejected' : 'pending');
                    
                    $amountStr = 'Rp ' . number_format((int)$row_pb['jumlah'], 0, ',', '.');
                    $tenor_val = (int)($row_pb['tenor'] ?? 0);
                    $tenorStr = $tenor_val > 0 ? ' untuk tenor ' . $tenor_val . ' bulan' : '';
                    $catatan = '';
                    
                    if ($status_display === 'approved') {
                        $keterangan = 'Pengajuan Pinjaman Biasa Anda sebesar ' . $amountStr . $tenorStr . ' disetujui, silahkan cek saldo anda di halaman dashboard.';
                    } elseif ($status_display === 'rejected') {
                        $keterangan = 'Pengajuan Pinjaman Biasa Anda sebesar ' . $amountStr . $tenorStr . ' ditolak, silahkan hubungi admin untuk informasi lebih lanjut.';
                        if (!empty($catatan)) {
                            $keterangan .= ' Alasan: ' . trim($catatan);
                        }
                    } else {
                        $keterangan = 'Pengajuan Pinjaman Biasa sebesar ' . $amountStr . $tenorStr . ' sedang menunggu persetujuan admin.';
                    }
                    
                    // Format created_at to WIB
                    $created_at_pb = $row_pb['created_at'];
                    if (!empty($created_at_pb)) {
                        try {
                            $dt = new DateTime($created_at_pb, new DateTimeZone('Asia/Jakarta'));
                            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
                            $created_at_pb = $dt->format('Y-m-d H:i:s');
                        } catch (Exception $e) {}
                    }
                    
                    $response_data = [
                        'id_transaksi' => $real_id_transaksi ?? $lookup_pb_id,
                        'no_transaksi' => '',
                        'id_pengguna' => (int) $row_pb['id_pengguna'],
                        'jenis_transaksi' => 'pinjaman_biasa',
                        'tanggal' => $created_at_pb,
                        'status' => $status_display,
                        'keterangan' => $keterangan,
                        'jumlah' => (int) $row_pb['jumlah'],
                        'detail_status' => $status_display,
                        'detail_keterangan' => $keterangan,
                        'detail_created_at' => $created_at_pb,
                        'jenis_tabungan' => '',
                        'jenis_pinjaman' => 'biasa',
                        'tenor' => $tenor_val,
                        'tujuan_penggunaan' => $row_pb['tujuan_penggunaan'] ?? '',
                    ];
                    // Fetch no_transaksi if real transaksi row exists
                    if ($real_id_transaksi) {
                        $nt_stmt = $connect->prepare("SELECT no_transaksi FROM transaksi WHERE id_transaksi = ? LIMIT 1");
                        if ($nt_stmt) {
                            $nt_stmt->bind_param('i', $real_id_transaksi);
                            if ($nt_stmt->execute()) {
                                $nt_res = $nt_stmt->get_result();
                                if ($nt_row = $nt_res->fetch_assoc()) {
                                    $response_data['no_transaksi'] = $nt_row['no_transaksi'] ?? '';
                                }
                            }
                            $nt_stmt->close();
                        }
                    }
                    
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Detail transaksi pinjaman biasa berhasil diambil',
                        'data' => $response_data
                    ]);
                    exit();
                }
            }
            $stmt_pb->close();
        }
    }
    
    // Also try pinjaman_kredit if pinjaman_biasa didn't find anything
    if ($trans_row === null && $lookup_pk_id <= 0 && $id_transaksi > 0) {
        $lookup_pk_id = $id_transaksi;  // Try using id_transaksi as pinjaman_kredit.id
    }
    
    if ($trans_row === null && $lookup_pk_id > 0) {
        // Lookup pinjaman_kredit directly
        $sql_pk = "SELECT id, id_pengguna, harga AS jumlah, nama_barang, tenor, cicilan_per_bulan, total_bayar, dp, pokok, status, created_at
                   FROM pinjaman_kredit WHERE id = ? LIMIT 1";
        $stmt_pk = $connect->prepare($sql_pk);
        if ($stmt_pk) {
            $stmt_pk->bind_param('i', $lookup_pk_id);
            if ($stmt_pk->execute()) {
                $res_pk = $stmt_pk->get_result();
                if ($res_pk && $res_pk->num_rows > 0) {
                    $row_pk = $res_pk->fetch_assoc();
                    
                    // Try to find matching transaksi record
                    $real_id_transaksi = null;
                    $check_sql = "SELECT id_transaksi FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi IN ('pinjaman_kredit', 'pinjaman') AND jumlah = ? ORDER BY id_transaksi DESC LIMIT 1";
                    $check_stmt = $connect->prepare($check_sql);
                    if ($check_stmt) {
                        $pk_jumlah = (int)$row_pk['jumlah'];
                        $check_stmt->bind_param('ii', $row_pk['id_pengguna'], $pk_jumlah);
                        if ($check_stmt->execute()) {
                            $check_res = $check_stmt->get_result();
                            if ($check_row = $check_res->fetch_assoc()) {
                                $real_id_transaksi = (int)$check_row['id_transaksi'];
                            }
                        }
                        $check_stmt->close();
                    }
                    
                    $pk_status = strtolower($row_pk['status']);
                    $status_display = ($pk_status === 'approved') ? 'approved' : (($pk_status === 'rejected') ? 'rejected' : 'pending');
                    
                    $amountStr = 'Rp ' . number_format((int)$row_pk['jumlah'], 0, ',', '.');
                    $tenor_val = (int)($row_pk['tenor'] ?? 0);
                    $nama_barang = $row_pk['nama_barang'] ?? '';
                    $catatan = '';
                    
                    if ($status_display === 'approved') {
                        $keterangan = 'Pengajuan Pinjaman Kredit untuk ' . $nama_barang . ' sebesar ' . $amountStr . ' disetujui.';
                    } elseif ($status_display === 'rejected') {
                        $keterangan = 'Pengajuan Pinjaman Kredit untuk ' . $nama_barang . ' sebesar ' . $amountStr . ' ditolak.';
                        if (!empty($catatan)) {
                            $keterangan .= ' Alasan: ' . trim($catatan);
                        }
                    } else {
                        $keterangan = 'Pengajuan Pinjaman Kredit untuk ' . $nama_barang . ' sebesar ' . $amountStr . ' sedang menunggu persetujuan admin.';
                    }
                    
                    $created_at_pk = $row_pk['created_at'];
                    if (!empty($created_at_pk)) {
                        try {
                            $dt = new DateTime($created_at_pk, new DateTimeZone('Asia/Jakarta'));
                            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
                            $created_at_pk = $dt->format('Y-m-d H:i:s');
                        } catch (Exception $e) {}
                    }
                    
                    $response_data = [
                        'id_transaksi' => $real_id_transaksi ?? $lookup_pk_id,
                        'no_transaksi' => '',
                        'id_pengguna' => (int) $row_pk['id_pengguna'],
                        'jenis_transaksi' => 'pinjaman_kredit',
                        'tanggal' => $created_at_pk,
                        'status' => $status_display,
                        'keterangan' => $keterangan,
                        'jumlah' => (int) $row_pk['jumlah'],
                        'detail_status' => $status_display,
                        'detail_keterangan' => $keterangan,
                        'detail_created_at' => $created_at_pk,
                        'jenis_tabungan' => '',
                        'jenis_pinjaman' => 'kredit',
                        'tenor' => $tenor_val,
                        'nama_barang' => $nama_barang,
                        'cicilan_per_bulan' => (int)($row_pk['cicilan_per_bulan'] ?? 0),
                        'total_bayar' => (int)($row_pk['total_bayar'] ?? 0),
                        'dp' => (int)($row_pk['dp'] ?? 0),
                    ];
                    // Fetch no_transaksi if real transaksi row exists
                    if ($real_id_transaksi) {
                        $nt_stmt = $connect->prepare("SELECT no_transaksi FROM transaksi WHERE id_transaksi = ? LIMIT 1");
                        if ($nt_stmt) {
                            $nt_stmt->bind_param('i', $real_id_transaksi);
                            if ($nt_stmt->execute()) {
                                $nt_res = $nt_stmt->get_result();
                                if ($nt_row = $nt_res->fetch_assoc()) {
                                    $response_data['no_transaksi'] = $nt_row['no_transaksi'] ?? '';
                                }
                            }
                            $nt_stmt->close();
                        }
                    }
                    
                    ob_end_clean();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Detail transaksi pinjaman kredit berhasil diambil',
                        'data' => $response_data
                    ]);
                    exit();
                }
            }
            $stmt_pk->close();
        }
    }
    
    // If still not found
    if ($trans_row === null) {
        ob_end_clean();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaksi tidak ditemukan'
        ]);
        exit();
    }
    
    // STEP 2: Based on jenis_transaksi, query detail dari tabel terkait
    $detail_data = null;
    
    if ($jenis_transaksi == 'penarikan') {
        // Extract tabungan_keluar_id from keterangan for precise lookup
        $tab_keluar_id = null;
        $trans_keterangan = $trans_row['keterangan'] ?? '';
        if (preg_match('/tabungan_keluar_id=(\d+)/i', $trans_keterangan, $matches_tk)) {
            $tab_keluar_id = intval($matches_tk[1]);
        }
        // Also try TK- format
        if ($tab_keluar_id === null && preg_match('/TK-(\d+)/i', $trans_keterangan, $matches_tk2)) {
            $tab_keluar_id = intval($matches_tk2[1]);
        }

        // Method 1: Query by exact tabungan_keluar.id (most accurate)
        if ($tab_keluar_id > 0) {
            $sql_detail = "SELECT 
                                tk.id,
                                tk.id_pengguna,
                                tk.id_jenis_tabungan,
                                tk.jumlah,
                                tk.status,
                                tk.keterangan,
                                tk.created_at,
                                jt.nama_jenis
                           FROM tabungan_keluar tk
                           LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                           WHERE tk.id = ?
                           LIMIT 1";
            $stmt_detail = $connect->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param('i', $tab_keluar_id);
                if ($stmt_detail->execute()) {
                    $result_detail = $stmt_detail->get_result();
                    if ($result_detail && $result_detail->num_rows > 0) {
                        $detail_data = $result_detail->fetch_assoc();
                    }
                }
                $stmt_detail->close();
            }
        }

        // Method 2 Fallback: match by id_pengguna + jumlah + closest timestamp
        if ($detail_data === null) {
            $trans_jumlah = (int)$trans_row['jumlah'];
            $sql_detail = "SELECT 
                                tk.id,
                                tk.id_pengguna,
                                tk.id_jenis_tabungan,
                                tk.jumlah,
                                tk.status,
                                tk.keterangan,
                                tk.created_at,
                                jt.nama_jenis
                           FROM tabungan_keluar tk
                           LEFT JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                           WHERE tk.id_pengguna = ? AND tk.jumlah = ?
                           ORDER BY ABS(UNIX_TIMESTAMP(tk.created_at) - UNIX_TIMESTAMP(?))
                           LIMIT 1";
            $stmt_detail = $connect->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param('iis', $id_pengguna, $trans_jumlah, $trans_row['tanggal']);
                if ($stmt_detail->execute()) {
                    $result_detail = $stmt_detail->get_result();
                    if ($result_detail && $result_detail->num_rows > 0) {
                        $detail_data = $result_detail->fetch_assoc();
                    }
                }
                $stmt_detail->close();
            }
        }
        
    } elseif ($jenis_transaksi == 'setoran') {
        // Match setoran by id_pengguna + jumlah + closest timestamp (more accurate than just latest)
        $trans_jumlah = (int)$trans_row['jumlah'];
        $sql_detail = "SELECT 
                            tm.id,
                            tm.id_pengguna,
                            tm.id_jenis_tabungan,
                            tm.jumlah,
                            tm.keterangan,
                            tm.created_at,
                            jt.nama_jenis
                       FROM tabungan_masuk tm
                       LEFT JOIN jenis_tabungan jt ON jt.id = tm.id_jenis_tabungan
                       WHERE tm.id_pengguna = ? AND tm.jumlah = ?
                       ORDER BY ABS(UNIX_TIMESTAMP(tm.created_at) - UNIX_TIMESTAMP(?))
                       LIMIT 1";
        
        $stmt_detail = $connect->prepare($sql_detail);
        if (!$stmt_detail) {
            throw new Exception('Prepare detail query failed: ' . $connect->error);
        }
        
        $stmt_detail->bind_param('iis', $id_pengguna, $trans_jumlah, $trans_row['tanggal']);
        if (!$stmt_detail->execute()) {
            throw new Exception('Query detail failed: ' . $stmt_detail->error);
        }
        
        $result_detail = $stmt_detail->get_result();
        if ($result_detail && $result_detail->num_rows > 0) {
            $detail_data = $result_detail->fetch_assoc();
        }
        $stmt_detail->close();
        
    } elseif ($jenis_transaksi == 'transfer_keluar' || $jenis_transaksi == 'transfer_masuk') {
        // Look up t_transfer for transfer details (nama_penerima, nama_pengirim, phone)
        $trans_jumlah = (int)$trans_row['jumlah'];
        $has_t_transfer = $connect->query("SHOW TABLES LIKE 't_transfer'");
        if ($has_t_transfer && $has_t_transfer->num_rows > 0) {
            // Get sender/receiver identifiers for matching
            $sql_ids = "SELECT id, no_hp FROM pengguna WHERE id = $id_pengguna LIMIT 1";
            $ids_result = $connect->query($sql_ids);
            $user_no_hp = '';
            if ($ids_result && $ids_row = $ids_result->fetch_assoc()) {
                $user_no_hp = $connect->real_escape_string($ids_row['no_hp'] ?? '');
            }

            if ($jenis_transaksi == 'transfer_keluar') {
                $sender_where = "(tt.id_pengirim = '$id_pengguna' OR tt.id_pengirim = '$user_no_hp')";
            } else {
                $sender_where = "(tt.id_penerima = '$id_pengguna' OR tt.id_penerima = '$user_no_hp')";
            }

            $escaped_tanggal = $connect->real_escape_string($trans_row['tanggal']);
            $sql_detail = "SELECT tt.nama_penerima, tt.id_penerima, tt.nama_pengirim, tt.id_pengirim, tt.keterangan as catatan
                           FROM t_transfer tt
                           WHERE $sender_where AND tt.jumlah = $trans_jumlah
                           ORDER BY ABS(UNIX_TIMESTAMP(tt.tanggal) - UNIX_TIMESTAMP('$escaped_tanggal'))
                           LIMIT 1";
            $result_detail_tf = $connect->query($sql_detail);
            if ($result_detail_tf && $result_detail_tf->num_rows > 0) {
                $detail_data = $result_detail_tf->fetch_assoc();
                $detail_data['_is_transfer'] = true;
                $detail_data['_direction'] = ($jenis_transaksi == 'transfer_keluar') ? 'keluar' : 'masuk';

                // Get counterparty phone number
                if ($jenis_transaksi == 'transfer_keluar') {
                    $penerima_id = $connect->real_escape_string($detail_data['id_penerima']);
                    $sql_phone = "SELECT no_hp FROM pengguna WHERE id = '$penerima_id' OR no_hp = '$penerima_id' LIMIT 1";
                } else {
                    $pengirim_id = $connect->real_escape_string($detail_data['id_pengirim']);
                    $sql_phone = "SELECT no_hp FROM pengguna WHERE id = '$pengirim_id' OR no_hp = '$pengirim_id' LIMIT 1";
                }
                $phone_result = $connect->query($sql_phone);
                if ($phone_result && $phone_row = $phone_result->fetch_assoc()) {
                    $detail_data['_counterparty_phone'] = $phone_row['no_hp'];
                }
            }
        }

    } elseif ($jenis_transaksi == 'pinjaman_biasa' || ($jenis_transaksi == 'pinjaman' && $resolved_pinjaman_biasa_id <= 0)) {
        // Query from pinjaman_biasa - match by id_pengguna + jumlah
        $trans_jumlah = (int)$trans_row['jumlah'];
        $sql_detail = "SELECT id, id_pengguna, jumlah_pinjaman AS jumlah, tenor, tujuan_penggunaan, status, created_at
                       FROM pinjaman_biasa 
                       WHERE id_pengguna = ? AND jumlah_pinjaman = ?
                       ORDER BY ABS(UNIX_TIMESTAMP(created_at) - UNIX_TIMESTAMP(?))
                       LIMIT 1";
        
        $stmt_detail = $connect->prepare($sql_detail);
        if ($stmt_detail) {
            $stmt_detail->bind_param('iis', $id_pengguna, $trans_jumlah, $trans_row['tanggal']);
            if ($stmt_detail->execute()) {
                $result_detail = $stmt_detail->get_result();
                if ($result_detail && $result_detail->num_rows > 0) {
                    $detail_data = $result_detail->fetch_assoc();
                    $detail_data['_is_pinjaman'] = true;
                    $detail_data['_jenis_pinjaman'] = 'biasa';
                }
            }
            $stmt_detail->close();
        }
        
    } elseif ($jenis_transaksi == 'pinjaman_kredit') {
        // Query from pinjaman_kredit - match by id_pengguna + jumlah
        $trans_jumlah = (int)$trans_row['jumlah'];
        $sql_detail = "SELECT id, id_pengguna, harga AS jumlah, nama_barang, tenor, cicilan_per_bulan, total_bayar, dp, pokok, status, created_at
                       FROM pinjaman_kredit 
                       WHERE id_pengguna = ? AND harga = ?
                       ORDER BY ABS(UNIX_TIMESTAMP(created_at) - UNIX_TIMESTAMP(?))
                       LIMIT 1";
        
        $stmt_detail = $connect->prepare($sql_detail);
        if ($stmt_detail) {
            $stmt_detail->bind_param('iis', $id_pengguna, $trans_jumlah, $trans_row['tanggal']);
            if ($stmt_detail->execute()) {
                $result_detail = $stmt_detail->get_result();
                if ($result_detail && $result_detail->num_rows > 0) {
                    $detail_data = $result_detail->fetch_assoc();
                    $detail_data['_is_pinjaman'] = true;
                    $detail_data['_jenis_pinjaman'] = 'kredit';
                }
            }
            $stmt_detail->close();
        }
    }
    
    // STEP 3: Format dan return response
    $response_data = [
        'id_transaksi' => (int) $trans_row['id_transaksi'],
        'no_transaksi' => $trans_row['no_transaksi'] ?? '',
        'id_pengguna' => (int) $trans_row['id_pengguna'],
        'jenis_transaksi' => $trans_row['jenis_transaksi'],
        'tanggal' => $trans_row['tanggal'],
        'status' => $trans_row['status'],
        'keterangan' => $trans_row['keterangan'] ?? '',
    ];
    
    // Add detail data jika ditemukan
    if ($detail_data !== null) {
        if (isset($detail_data['_is_transfer']) && $detail_data['_is_transfer']) {
            // Transfer detail (transfer_keluar / transfer_masuk)
            $response_data['jumlah'] = (int) $trans_row['jumlah'];
            $response_data['detail_status'] = $trans_row['status'];
            $response_data['detail_created_at'] = $trans_row['tanggal'];

            $amountStr = 'Rp ' . number_format((int)$trans_row['jumlah'], 0, ',', '.');
            $catatan = trim($detail_data['catatan'] ?? '');
            $counterparty_phone = $detail_data['_counterparty_phone'] ?? '';

            if ($detail_data['_direction'] === 'keluar') {
                $nama = $detail_data['nama_penerima'] ?? '';
                $recipientLabel = !empty($nama) ? $nama . (!empty($counterparty_phone) ? " ($counterparty_phone)" : '') : $counterparty_phone;
                $keterangan = "Kirim Uang $amountStr ke $recipientLabel Berhasil.";
            } else {
                $nama = $detail_data['nama_pengirim'] ?? '';
                $senderLabel = !empty($nama) ? "($nama)" : (!empty($counterparty_phone) ? "($counterparty_phone)" : '');
                $keterangan = "Terima Uang $amountStr dari $senderLabel, ";
            }

            if (!empty($catatan) && strtolower($catatan) !== 'transfer') {
                $keterangan .= " Catatan: $catatan";
            }

            $response_data['keterangan'] = $keterangan;
            $response_data['detail_keterangan'] = $keterangan;
            $response_data['nama_penerima'] = $detail_data['nama_penerima'] ?? '';
            $response_data['nama_pengirim'] = $detail_data['nama_pengirim'] ?? '';
            $response_data['jenis_tabungan'] = '';
        } else if (isset($detail_data['_is_pinjaman']) && $detail_data['_is_pinjaman']) {
            // Pinjaman detail
            $response_data['jumlah'] = (int) $detail_data['jumlah'];
            $response_data['detail_status'] = $detail_data['status'];
            $response_data['detail_keterangan'] = $trans_row['keterangan'] ?? '';
            
            // Use created_at from pinjaman detail table (DATETIME = correct WIB)
            // NOT transaksi.tanggal (TIMESTAMP inserted with UTC by PHP date())
            $pinjaman_time = $detail_data['created_at'];
            if (!empty($pinjaman_time)) {
                $response_data['detail_created_at'] = $pinjaman_time;
                $response_data['tanggal'] = $pinjaman_time;
            } else {
                $response_data['detail_created_at'] = $trans_row['tanggal'];
            }
            
            $response_data['jenis_tabungan'] = '';
            $response_data['jenis_pinjaman'] = $detail_data['_jenis_pinjaman'];
            $response_data['tenor'] = (int)($detail_data['tenor'] ?? 0);
            $response_data['tujuan_penggunaan'] = $detail_data['tujuan_penggunaan'] ?? '';
            
            if (isset($detail_data['nama_barang'])) {
                $response_data['nama_barang'] = $detail_data['nama_barang'];
            }
        } else {
            // Tabungan detail (setoran/penarikan)
            $response_data['jumlah'] = (int) $detail_data['jumlah'];
            $response_data['detail_status'] = $detail_data['status'] ?? $trans_row['status'];
            $response_data['detail_keterangan'] = $detail_data['keterangan'] ?? $trans_row['keterangan'] ?? '';
            $response_data['detail_created_at'] = $detail_data['created_at'];
            $response_data['id_jenis_tabungan'] = (int) ($detail_data['id_jenis_tabungan'] ?? 0);
            $response_data['jenis_tabungan'] = $detail_data['nama_jenis'] ?? 'Tabungan Reguler';
        }
    } else {
        // Fallback: use data from transaksi table
        $response_data['jumlah'] = (int) $trans_row['jumlah'];
        $response_data['detail_status'] = $trans_row['status'];
        $response_data['detail_keterangan'] = $trans_row['keterangan'];
        $response_data['detail_created_at'] = $trans_row['tanggal'];
        
        // For pinjaman types without detail, still set correct jenis_pinjaman
        // Also try to get correct time from pinjaman detail table
        $jt = strtolower($trans_row['jenis_transaksi']);
        if ($jt === 'pinjaman_biasa' || $jt === 'pinjaman') {
            $response_data['jenis_tabungan'] = '';
            $response_data['jenis_pinjaman'] = 'biasa';
            // Try to get correct WIB time from pinjaman_biasa.created_at
            $fb_sql = "SELECT created_at FROM pinjaman_biasa WHERE id_pengguna = ? ORDER BY id DESC LIMIT 1";
            $fb_stmt = $connect->prepare($fb_sql);
            if ($fb_stmt) {
                $fb_stmt->bind_param('i', $trans_row['id_pengguna']);
                if ($fb_stmt->execute()) {
                    $fb_res = $fb_stmt->get_result();
                    if ($fb_row = $fb_res->fetch_assoc()) {
                        if (!empty($fb_row['created_at'])) {
                            $response_data['detail_created_at'] = $fb_row['created_at'];
                            $response_data['tanggal'] = $fb_row['created_at'];
                        }
                    }
                }
                $fb_stmt->close();
            }
        } elseif ($jt === 'pinjaman_kredit') {
            $response_data['jenis_tabungan'] = '';
            $response_data['jenis_pinjaman'] = 'kredit';
            // Try to get correct WIB time from pinjaman_kredit.created_at
            $fb_sql = "SELECT created_at FROM pinjaman_kredit WHERE id_pengguna = ? ORDER BY id DESC LIMIT 1";
            $fb_stmt = $connect->prepare($fb_sql);
            if ($fb_stmt) {
                $fb_stmt->bind_param('i', $trans_row['id_pengguna']);
                if ($fb_stmt->execute()) {
                    $fb_res = $fb_stmt->get_result();
                    if ($fb_row = $fb_res->fetch_assoc()) {
                        if (!empty($fb_row['created_at'])) {
                            $response_data['detail_created_at'] = $fb_row['created_at'];
                            $response_data['tanggal'] = $fb_row['created_at'];
                        }
                    }
                }
                $fb_stmt->close();
            }
        } else {
            $response_data['jenis_tabungan'] = 'Tabungan Reguler';
        }
    }
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Detail transaksi berhasil diambil',
        'data' => $response_data
    ]);
    exit();
    
} catch (Exception $e) {
    @file_put_contents(
        __DIR__ . '/api_debug.log',
        date('c') . " [get_detail_transaksi] Error: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
    exit();
}
?>
