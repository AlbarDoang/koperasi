<?php
// API: Get riwayat tabungan untuk pengguna (via nomor_hp atau id_pengguna)
// Params (GET/POST): nomor_hp (REQUIRED) or id_pengguna, jenis_tabungan, periode (number of days)
// UPDATED: Use nomor_hp to fetch history across all tabungan types
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
include 'connection.php';

// Support both nomor_hp and id_tabungan for flexibility
$nomor_hp = isset($_REQUEST['nomor_hp']) ? trim($_REQUEST['nomor_hp']) : '';
$id_pengguna_param = isset($_REQUEST['id_pengguna']) ? intval($_REQUEST['id_pengguna']) : 0;
$id_tabungan = isset($_REQUEST['id_tabungan']) ? trim($_REQUEST['id_tabungan']) : '';
$jenis = isset($_REQUEST['jenis_tabungan']) ? trim($_REQUEST['jenis_tabungan']) : '';
$periode = isset($_REQUEST['periode']) ? trim($_REQUEST['periode']) : '30';

// Normalize input: try to resolve nomor_hp first, then fallback
if (empty($nomor_hp) && !empty($id_tabungan)) {
    // Try to resolve nomor_hp from mulai_nabung using id_tabungan
    $res = $connect->query("SELECT nomor_hp FROM mulai_nabung WHERE id_tabungan='{$id_tabungan}' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $r = $res->fetch_assoc();
        $nomor_hp = $r['nomor_hp'];
    }
}

if (empty($nomor_hp) && $id_pengguna_param > 0) {
    // Try to resolve nomor_hp from pengguna
    $res = $connect->query("SELECT no_hp FROM pengguna WHERE id={$id_pengguna_param} LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $r = $res->fetch_assoc();
        $nomor_hp = $r['no_hp'];
    }
}

if (empty($nomor_hp)) { 
    echo json_encode(["success"=>false,"message"=>"Parameter nomor_hp, id_tabungan, atau id_pengguna diperlukan"]); 
    exit(); 
}
if (empty($jenis)) { 
    echo json_encode(["success"=>false,"message"=>"Parameter jenis_tabungan wajib diisi"]); 
    exit(); 
}

if (empty($connect)) { 
    echo json_encode(["success"=>false,"message"=>"Internal server error"]); 
    exit(); 
}

try {
    $data = [];
    
    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] FETCH START: nomor_hp={$nomor_hp}, jenis={$jenis}, periode={$periode}\n", FILE_APPEND);
    
    // =========================================================================
    // RESOLVE ID_PENGGUNA dari nomor_hp (reliable path)
    // =========================================================================
    $id_pengguna = null;
    $stmt_resolve_user = $connect->prepare("SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1");
    if ($stmt_resolve_user) {
        $stmt_resolve_user->bind_param('s', $nomor_hp);
        if (!$stmt_resolve_user->execute()) {
            @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] Error resolving id_pengguna from nomor_hp: ".$stmt_resolve_user->error."\n", FILE_APPEND);
        } else {
            $res_user = $stmt_resolve_user->get_result();
            if ($res_user && $res_user->num_rows > 0) {
                $row_user = $res_user->fetch_assoc();
                $id_pengguna = intval($row_user['id']);
                @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] Resolved id_pengguna={$id_pengguna} from nomor_hp={$nomor_hp}\n", FILE_APPEND);
            } else {
                @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] WARNING: No pengguna row found for nomor_hp={$nomor_hp}\n", FILE_APPEND);
            }
        }
        $stmt_resolve_user->close();
    }
    
    // =========================================================================
    // RESOLVE ID_JENIS_TABUNGAN dari nama jenis_tabungan (tolerant matching)
    // =========================================================================
    // Penting: id_jenis_tabungan digunakan untuk filter pada tabungan_keluar
    $id_jenis_tabungan = null;
    
    // Step 1: Try exact match
    $stmt_resolve_jenis = $connect->prepare("SELECT id FROM jenis_tabungan WHERE nama_jenis = ? LIMIT 1");
    if ($stmt_resolve_jenis) {
        $stmt_resolve_jenis->bind_param('s', $jenis);
        if (!$stmt_resolve_jenis->execute()) {
            @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] Error executing exact jenis match: ".$stmt_resolve_jenis->error."\n", FILE_APPEND);
        } else {
            $res_jenis = $stmt_resolve_jenis->get_result();
            if ($res_jenis && $res_jenis->num_rows > 0) {
                $row_jenis = $res_jenis->fetch_assoc();
                $id_jenis_tabungan = intval($row_jenis['id']);
                @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] Resolved id_jenis_tabungan={$id_jenis_tabungan} from exact match nama_jenis={$jenis}\n", FILE_APPEND);
            }
        }
        $stmt_resolve_jenis->close();
    }
    
    // Step 2: Try case-insensitive match if exact failed
    if ($id_jenis_tabungan === null || $id_jenis_tabungan <= 0) {
        $stmt_resolve_jenis = $connect->prepare("SELECT id FROM jenis_tabungan WHERE LOWER(TRIM(nama_jenis)) = LOWER(TRIM(?)) LIMIT 1");
        if ($stmt_resolve_jenis) {
            $stmt_resolve_jenis->bind_param('s', $jenis);
            if (!$stmt_resolve_jenis->execute()) {
                @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] Error executing case-insensitive jenis match: ".$stmt_resolve_jenis->error."\n", FILE_APPEND);
            } else {
                $res_jenis = $stmt_resolve_jenis->get_result();
                if ($res_jenis && $res_jenis->num_rows > 0) {
                    $row_jenis = $res_jenis->fetch_assoc();
                    $id_jenis_tabungan = intval($row_jenis['id']);
                    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] Resolved id_jenis_tabungan={$id_jenis_tabungan} from case-insensitive match nama_jenis={$jenis}\n", FILE_APPEND);
                }
            }
            $stmt_resolve_jenis->close();
        }
    }
    
    // Step 3: Fallback to first available jenis if still unresolved
    if ($id_jenis_tabungan === null || $id_jenis_tabungan <= 0) {
        $stmt_fallback = $connect->prepare("SELECT id FROM jenis_tabungan LIMIT 1");
        if ($stmt_fallback) {
            if ($stmt_fallback->execute()) {
                $res_fallback = $stmt_fallback->get_result();
                if ($res_fallback && $res_fallback->num_rows > 0) {
                    $row_fallback = $res_fallback->fetch_assoc();
                    $id_jenis_tabungan = intval($row_fallback['id']);
                    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] WARNING: Using fallback id_jenis_tabungan={$id_jenis_tabungan} (no match for jenis={$jenis})\n", FILE_APPEND);
                }
            }
            $stmt_fallback->close();
        }
    }
    
    // =========================================================================
    // STEP 1: Fetch from mulai_nabung (include pending + approved) - via nomor_hp
    // =========================================================================
    try {
        // include common pending variants so submissions always appear in history (pending or berhasil)
        $status_in_clause = "('berhasil','menunggu_admin','menunggu_penyerahan','pending')";
        if (strtolower($periode) === 'all') {
            $sql_mulai = "SELECT DATE_FORMAT(tanggal, '%Y-%m-%d') AS tanggal, jenis_tabungan, jumlah, status FROM mulai_nabung 
                          WHERE nomor_hp = ? AND jenis_tabungan = ? AND status IN $status_in_clause 
                          ORDER BY tanggal DESC";
            if (!($stmt_mulai = $connect->prepare($sql_mulai))) throw new Exception('Gagal prepare mulai_nabung: '.$connect->error);
            $stmt_mulai->bind_param('ss', $nomor_hp, $jenis);
        } else {
            $days = intval($periode);
            if ($days <= 0) $days = 30;
            $sql_mulai = "SELECT DATE_FORMAT(tanggal, '%Y-%m-%d') AS tanggal, jenis_tabungan, jumlah, status FROM mulai_nabung 
                          WHERE nomor_hp = ? AND jenis_tabungan = ? AND status IN $status_in_clause 
                          AND tanggal >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 
                          ORDER BY tanggal DESC";
            if (!($stmt_mulai = $connect->prepare($sql_mulai))) throw new Exception('Gagal prepare mulai_nabung: '.$connect->error);
            $stmt_mulai->bind_param('ssi', $nomor_hp, $jenis, $days);
        }
        if (!$stmt_mulai->execute()) throw new Exception('Gagal execute mulai_nabung: '.$stmt_mulai->error);
        $res_mulai = $stmt_mulai->get_result();
        $count_mulai = 0;
        while ($r = $res_mulai->fetch_assoc()) {
            $data[] = [
                'tanggal' => $r['tanggal'] ?? '',
                'jenis_tabungan' => $r['jenis_tabungan'] ?? '',
                'jumlah' => intval($r['jumlah']),
                'status' => $r['status'] ?? 'unknown',
                'tipe' => 'masuk',
                'sumber' => 'mulai_nabung'
            ];
            $count_mulai++;
        }
        $stmt_mulai->close();
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] STEP 1 (mulai_nabung): Fetched {$count_mulai} rows. Total data now: ".count($data)."\n", FILE_APPEND);
    } catch (Exception $e) {
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] STEP 1 ERROR (mulai_nabung): ". $e->getMessage()."\n", FILE_APPEND);
        // Non-fatal: continue to next step
    }
    
    // =========================================================================
    // STEP 2: Fetch from tabungan_masuk (setor manual admin)
    // =========================================================================
    // Catatan: Menggunakan id_pengguna yang sudah di-resolve di atas
    try {
        if ($id_pengguna === null || $id_pengguna <= 0) {
            throw new Exception('id_pengguna tidak valid, skip STEP 2 (tabungan_masuk)');
        }
        
        if (strtolower($periode) === 'all') {
            $sql_masuk = "SELECT 
                          DATE_FORMAT(created_at, '%Y-%m-%d') AS tanggal,
                          ? AS jenis_tabungan,
                          jumlah
                          FROM tabungan_masuk
                          WHERE id_pengguna = ?
                          ORDER BY created_at DESC";
            if (!($stmt_masuk = $connect->prepare($sql_masuk))) throw new Exception('Gagal prepare tabungan_masuk: '.$connect->error);
            $stmt_masuk->bind_param('si', $jenis, $id_pengguna);
        } else {
            $days = intval($periode);
            if ($days <= 0) $days = 30;
            $sql_masuk = "SELECT 
                          DATE_FORMAT(created_at, '%Y-%m-%d') AS tanggal,
                          ? AS jenis_tabungan,
                          jumlah
                          FROM tabungan_masuk
                          WHERE id_pengguna = ?
                          AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                          ORDER BY created_at DESC";
            if (!($stmt_masuk = $connect->prepare($sql_masuk))) throw new Exception('Gagal prepare tabungan_masuk: '.$connect->error);
            $stmt_masuk->bind_param('sii', $jenis, $id_pengguna, $days);
        }
        if (!$stmt_masuk->execute()) throw new Exception('Gagal execute tabungan_masuk: '.$stmt_masuk->error);
        $res_masuk = $stmt_masuk->get_result();
        $count_masuk = 0;
        while ($r = $res_masuk->fetch_assoc()) {
            $data[] = [
                'tanggal' => $r['tanggal'] ?? '',
                'jenis_tabungan' => $r['jenis_tabungan'] ?? '',
                'jumlah' => intval($r['jumlah']),
                'tipe' => 'masuk',
                'sumber' => 'tabungan_masuk'
            ];
            $count_masuk++;
        }
        $stmt_masuk->close();
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] STEP 2 (tabungan_masuk): Fetched {$count_masuk} rows. Total data now: ".count($data)."\n", FILE_APPEND);
    } catch (Exception $e) {
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] STEP 2 ERROR (tabungan_masuk): ". $e->getMessage()."\n", FILE_APPEND);
        // Non-fatal: continue to next step
    }
    
    // =========================================================================
    // STEP 3: Fetch from tabungan_keluar (withdrawal requests)
    // =========================================================================
    // PENTING: Hanya tampilkan status 'approved' atau 'rejected'
    // Jangan tampilkan 'pending' di riwayat transaksi
    // Filter: WHERE id_pengguna = ? AND id_jenis_tabungan = ? AND status IN ('approved','rejected')
    try {
        if ($id_pengguna === null || $id_pengguna <= 0) {
            throw new Exception('id_pengguna tidak valid, skip STEP 3 (tabungan_keluar)');
        }
        
        if ($id_jenis_tabungan === null || $id_jenis_tabungan <= 0) {
            throw new Exception('id_jenis_tabungan tidak valid, skip STEP 3 (tabungan_keluar)');
        }
        
        if (strtolower($periode) === 'all') {
            // Query dengan filter: id_pengguna, id_jenis_tabungan, dan hanya approved/rejected
            $sql_keluar = "SELECT 
                           DATE_FORMAT(created_at, '%Y-%m-%d') AS tanggal,
                           ? AS jenis_tabungan,
                           jumlah,
                           status,
                           rejected_reason
                           FROM tabungan_keluar
                           WHERE id_pengguna = ?
                           AND id_jenis_tabungan = ?
                           AND status IN ('approved', 'rejected')
                           ORDER BY created_at DESC";
            if (!($stmt_keluar = $connect->prepare($sql_keluar))) throw new Exception('Gagal prepare tabungan_keluar: '.$connect->error);
            $stmt_keluar->bind_param('sii', $jenis, $id_pengguna, $id_jenis_tabungan);
        } else {
            $days = intval($periode);
            if ($days <= 0) $days = 30;
            // Query dengan filter: id_pengguna, id_jenis_tabungan, status, dan periode
            $sql_keluar = "SELECT 
                           DATE_FORMAT(created_at, '%Y-%m-%d') AS tanggal,
                           ? AS jenis_tabungan,
                           jumlah,
                           status,
                           rejected_reason
                           FROM tabungan_keluar
                           WHERE id_pengguna = ?
                           AND id_jenis_tabungan = ?
                           AND status IN ('approved', 'rejected')
                           AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                           ORDER BY created_at DESC";
            if (!($stmt_keluar = $connect->prepare($sql_keluar))) throw new Exception('Gagal prepare tabungan_keluar: '.$connect->error);
            $stmt_keluar->bind_param('siii', $jenis, $id_pengguna, $id_jenis_tabungan, $days);
        }
        if (!$stmt_keluar->execute()) throw new Exception('Gagal execute tabungan_keluar: '.$stmt_keluar->error);
        $res_keluar = $stmt_keluar->get_result();
        $count_keluar = 0;
        while ($r = $res_keluar->fetch_assoc()) {
            // Build response array dengan field yang diperlukan
            $item = [
                'tanggal' => $r['tanggal'] ?? '',
                'jenis_tabungan' => $r['jenis_tabungan'] ?? '',
                'jumlah' => intval($r['jumlah']),
                'status' => $r['status'] ?? 'unknown',
                'tipe' => 'keluar',
                'sumber' => 'tabungan_keluar'
            ];
            
            // Tambahkan rejected_reason hanya jika status = 'rejected' dan ada nilai
            if ($r['status'] === 'rejected' && !empty($r['rejected_reason'])) {
                $item['rejected_reason'] = $r['rejected_reason'];
            }
            
            $data[] = $item;
            $count_keluar++;
        }
        $stmt_keluar->close();
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] STEP 3 (tabungan_keluar): Fetched {$count_keluar} rows (approved+rejected only). Total data now: ".count($data)."\n", FILE_APPEND);
    } catch (Exception $e) {
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] STEP 3 ERROR (tabungan_keluar): ". $e->getMessage()."\n", FILE_APPEND);
        // Non-fatal: continue to final step
    }
    
    // =========================================================================
    // STEP 4: Sort combined data by tanggal DESC
    // =========================================================================
    // Gabung semua data (mulai_nabung + tabungan_masuk + tabungan_keluar) dan sort
    if (count($data) > 1) {
        usort($data, function($a, $b) {
            $dateA = strtotime($a['tanggal'] ?? '');
            $dateB = strtotime($b['tanggal'] ?? '');
            return $dateB - $dateA; // Descending order (newest first)
        });
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] STEP 4: Sorted ".count($data)." combined records by tanggal DESC\n", FILE_APPEND);
    }
    
    // =========================================================================
    // RESPONSE
    // =========================================================================
    echo json_encode(["success"=>true, "data"=>$data, "meta" => ["total" => count($data)]]);
    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] SUCCESS: Returned ".count($data)." records\n", FILE_APPEND);
    exit();
} catch (Exception $e) {
    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_riwayat_tabungan] FATAL ERROR: ". $e->getMessage()."\n", FILE_APPEND);
    echo json_encode(["success"=>false,"message"=>"Internal server error"]);
    exit();
}
