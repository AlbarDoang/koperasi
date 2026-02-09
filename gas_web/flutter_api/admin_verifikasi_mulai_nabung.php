<?php
// Suppress PHP warnings/notices that would break JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] INVOKE_TOP\n", FILE_APPEND);
/**
 * API: Admin Verifikasi Mulai Nabung (Top-up Tunai)
 * Method: POST
 * Params: id_mulai_nabung, action ('setuju' or 'tolak')
 * 
 * Behavior:
 * - If action == 'setuju': set status='berhasil' and add jumlah to user's saldo
 * - If action == 'tolak': set status='ditolak' and do not change saldo
 * 
 * Uses transaction to ensure consistency.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/api_bootstrap.php';

// Early instrumentation: mark start and ensure PHP warnings become exceptions so they will be logged
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] START\n", FILE_APPEND);
if (PHP_SAPI !== 'cli') {
    set_error_handler(function($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
}
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err) {
        @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " SHUTDOWN: " . var_export($err, true) . "\n", FILE_APPEND);
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] SHUTDOWN: " . var_export($err, true) . "\n", FILE_APPEND);
    }
});

// Debug: log incoming requests to help trace empty/malformed responses
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] CALL POST=" . json_encode($_POST) . "\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] AFTER_BOOTSTRAP\n", FILE_APPEND);

if (empty($connect)) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] ERROR missing DB connection\n", FILE_APPEND);
    sendJsonResponse(false, 'Internal server error');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed');
}

// Use helper getPostData() from helpers.php which supports both form and raw JSON inputs.

$id_raw = getPostData('id_mulai_nabung');
$action = getPostData('action');

if (empty($id_raw) || empty($action)) {
    sendJsonResponse(false, 'Parameter tidak lengkap');
}

$id = intval($id_raw);
$action = strtolower($action);
if (!in_array($action, ['setuju', 'tolak'])) {
    sendJsonResponse(false, 'Parameter action tidak valid');
}

// Begin transaction
$connect->begin_transaction();
try {
    // Deteksi kolom id_tabungan di tabel pengguna
    $has_id_tabungan = false;
    $col_check = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'id_tabungan'");
    if ($col_check && $col_check->num_rows > 0) {
        $has_id_tabungan = true;
    }
    
    // Get data from mulai_nabung and lock the row to prevent race conditions
    if (!($stmt = $connect->prepare("SELECT id_tabungan, nomor_hp, nama_pengguna, jumlah, status, jenis_tabungan FROM mulai_nabung WHERE id_mulai_nabung = ? FOR UPDATE"))) {
        throw new Exception('Gagal menyiapkan query: ' . $connect->error);
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        throw new Exception('Gagal eksekusi query: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res->num_rows == 0) {
        $stmt->close();
        throw new Exception('Data permintaan tidak ditemukan');
    }

    $row = $res->fetch_assoc();
    $id_tabungan = $row['id_tabungan'];
    $nomor_hp = $row['nomor_hp'] ?? null;
    $nama_pengguna = $row['nama_pengguna'] ?? null;
    $jumlah = floatval($row['jumlah']);
    $current_status = $row['status'];
    $jenis_tabungan = !empty($row['jenis_tabungan']) ? $row['jenis_tabungan'] : 'Tabungan Reguler';
    $stmt->close();

    // Log for debugging
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] EXTRACTED: id={$id} jenis_tabungan=({$jenis_tabungan}) jumlah={$jumlah} status={$action}\n", FILE_APPEND);
    if (empty($jenis_tabungan)) {
        @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " WARNING: jenis_tabungan is EMPTY for id={$id}, fallback to Tabungan Reguler\n", FILE_APPEND);
    }

    // Use shared resolver from notif_helper.php so logic is centralized
    require_once __DIR__ . '/notif_helper.php';
    // Note: the helper function is resolve_pengguna_id_from_mulai(



    if ($action == 'setuju') {
        // Only credit saldo if the current status indicates it hasn't been credited yet.
        // Older/other endpoints may use Indonesian status strings such as 'menunggu_admin'
        // or 'menunggu_penyerahan' while older code used 'pending'. Normalize and
        // accept those variants to avoid skipping the tabungan_masuk insert on replay.
        $cur_status_norm = strtolower(trim((string)$current_status));
        $pending_variants = ['pending', 'menunggu_admin', 'menunggu_penyerahan', 'menunggu'];
        $should_credit = in_array($cur_status_norm, $pending_variants, true);

        // Update mulai_nabung status to 'berhasil' (do this regardless so the record reflects admin intent)
        if (!($u1 = $connect->prepare("UPDATE mulai_nabung SET status = ?, updated_at = NOW() WHERE id_mulai_nabung = ?"))) {
            throw new Exception('Gagal prepare update status: ' . $connect->error);
        }
        $new_status = 'berhasil';
        $u1->bind_param('si', $new_status, $id);
        if (!$u1->execute()) {
            throw new Exception('Gagal update status: ' . $u1->error);
        }
        $u1->close();

        // Resolve pengguna id robustly (id_tabungan might be a tabungan id, numeric pengguna.id, or missing)
        // Use centralized resolver
        $user_id = resolve_pengguna_id_from_mulai($connect, $id_tabungan, $nomor_hp, $nama_pengguna);
        if ($user_id === null) {
            throw new Exception('Pengguna tidak ditemukan dengan id_tabungan: ' . $id_tabungan);
        }
        // Fetch current saldo
        $sq = $connect->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
        $sq->bind_param('i', $user_id);
        $sq->execute();
        $rsq = $sq->get_result();
        $sqrow = $rsq->fetch_assoc();
        $current_saldo = floatval($sqrow['saldo']);
        $sq->close();

        include_once __DIR__ . '/../login/function/ledger_helpers.php';
        // Use approval timestamp for notification created_at so it reflects admin action time
        $approved_at = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');        // --- Begin: Validate jenis_tabungan and ensure it maps to a real jenis_tabungan id ---
        $validated_jenis = $jenis_tabungan; // default fallback
        if (has_table($connect, 'tabungan_masuk')) {
            if (empty($jenis_tabungan) || (string)$jenis_tabungan === '0') {
                throw new Exception('Jenis tabungan tidak valid atau kosong');
            }
            if (ctype_digit((string)$jenis_tabungan)) {
                $id_jenis = intval($jenis_tabungan);
                $chk = $connect->prepare("SELECT id FROM jenis_tabungan WHERE id = ? LIMIT 1");
                $chk->bind_param('i', $id_jenis);
                $chk->execute();
                $cres = $chk->get_result();
                $chk->close();
                if (!$cres || $cres->num_rows == 0) {
                    throw new Exception('Jenis tabungan tidak ditemukan (id: ' . $jenis_tabungan . ')');
                }
                $validated_jenis = $id_jenis;
            } else {
                // Attempt a friendly name lookup, but be defensive: some deployments may have different
                // column names in jenis_tabungan. If the query fails, fallback to a default id and
                // continue, logging the mismatch rather than aborting the whole transaction.
                $name = $connect->real_escape_string($jenis_tabungan);
                $norm = preg_replace('/\\btabungan\\b/i', '', $name);
                $norm = trim($norm);
                $n1 = $connect->real_escape_string($norm);
                try {
                    $q = "SELECT id FROM jenis_tabungan WHERE (nama_jenis = '$name' OR nama_jenis = '$n1' OR nama_jenis LIKE '%$n1%') LIMIT 1";
                    $jr = $connect->query($q);
                    if (!($jr && $jr->num_rows > 0)) {
                        // fallback: log and use default validated_jenis (string name not resolvable)
                        @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Could not resolve jenis_tabungan name '{$jenis_tabungan}' using query: {$q}\n", FILE_APPEND);
                    } else {
                        $rrow = $jr->fetch_assoc();
                        $validated_jenis = intval($rrow['id']);
                    }
                } catch (Exception $e) {
                    @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Exception resolving jenis_tabungan name '{$jenis_tabungan}': " . $e->getMessage() . "\n", FILE_APPEND);
                    // leave $validated_jenis as-is (fallback)
                }
            }
        }
        // --- End validation ---

        // Process approval. If we should credit, perform per-jenis insert or legacy wallet flow,
        // then update pengguna.saldo (if tabungan_masuk branch is used). If we should NOT credit,
        // skip inserts and credits to avoid double-credits.
        $ok = false;
        $saldo_terbaru = $current_saldo;
        if ($should_credit) {
            // Check if tabungan_masuk table exists
            $table_exists = @$connect->query("DESCRIBE tabungan_masuk");
            if ($table_exists) {
                $created = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
                $keterangan = 'Topup approve (mulai_nabung ' . $id . ')';
                $stmt_ins = $connect->prepare("INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt_ins) {
                    throw new Exception('Gagal prepare insert tabungan_masuk: ' . $connect->error);
                }
                $stmt_ins->bind_param('iissss', $user_id, $validated_jenis, $jumlah, $keterangan, $created, $created);
                if (!$stmt_ins->execute()) {
                    $stmt_ins->close();
                    throw new Exception('Gagal insert ke tabungan_masuk: ' . $stmt_ins->error);
                }
                $stmt_ins->close();
                $ins_id = $connect->insert_id;
                @file_put_contents(__DIR__ . '/admin_verifikasi.log', date('c') . " INSERT_TABUNGAN_MASUK user={$user_id} jenis={$validated_jenis} amt={$jumlah} mulai_nabung={$id} insert_id={$ins_id}\n", FILE_APPEND);
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] INSERT_TABUNGAN_MASUK insert_id=" . $ins_id . " user={$user_id} jenis={$validated_jenis} amt={$jumlah} mulai_nabung={$id}\n", FILE_APPEND);

                // Also update pengguna.saldo to make pengguna.saldo the single-source-of-truth
                if (!($upd = $connect->prepare("UPDATE pengguna SET saldo = saldo + ? WHERE id = ?"))) {
                    throw new Exception('Gagal prepare update pengguna.saldo: ' . $connect->error);
                }
                $upd->bind_param('di', $jumlah, $user_id);
                if (!$upd->execute()) {
                    throw new Exception('Gagal update pengguna.saldo: ' . $upd->error);
                }
                $upd->close();

                // Fetch new saldo
                $sq = $connect->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
                $sq->bind_param('i', $user_id);
                $sq->execute();
                $rsq = $sq->get_result();
                $sqrow = $rsq->fetch_assoc();
                $saldo_terbaru = floatval($sqrow['saldo']);
                $sq->close();

                // ALSO CREATE/UPDATE TRANSACTION RECORD so get_riwayat_transaksi.php shows the entry
                $trans_table_exists = @$connect->query("DESCRIBE transaksi");
                if ($trans_table_exists) {
                    // First, try to find and UPDATE the existing pending transaction for this mulai_nabung
                    // Use same pattern as buat_mulai_nabung.php for consistency: "Mulai nabung tunai (mulai_nabung {ID})"
                    $search_pattern = '%mulai_nabung ' . $id . '%';
                    $keterangan_trans = 'Setoran Tabungan Disetujui (mulai_nabung ' . $id . ')';  // Include ID for API parsing in get_riwayat_transaksi.php
                    $existing_trans_query = $connect->prepare("SELECT id_transaksi FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? ORDER BY tanggal DESC LIMIT 1");
                    if ($existing_trans_query) {
                        $existing_trans_query->bind_param('is', $user_id, $search_pattern);
                        $existing_trans_query->execute();
                        $existing_res = $existing_trans_query->get_result();
                        
                        if ($existing_res && $existing_res->num_rows > 0) {
                            // UPDATE existing pending transaction to approved
                            $existing_row = $existing_res->fetch_assoc();
                            $existing_trans_id = $existing_row['id_transaksi'];
                            $update_trans_stmt = $connect->prepare("UPDATE transaksi SET status = ?, keterangan = ?, saldo_sesudah = ? WHERE id_transaksi = ?");
                            if ($update_trans_stmt) {
                                $status_trans = 'approved';
                                $update_trans_stmt->bind_param('ssii', $status_trans, $keterangan_trans, $saldo_terbaru, $existing_trans_id);
                                if ($update_trans_stmt->execute()) {
                                    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_UPDATED (from pending to approved) id=".$existing_trans_id." user={$user_id} jenis=setoran amt={$jumlah}\n", FILE_APPEND);
                                } else {
                                    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_UPDATE_ERROR: ".$update_trans_stmt->error."\n", FILE_APPEND);
                                }
                                $update_trans_stmt->close();
                                // Cleanup other pending duplicates for the same mulai_nabung
                                $cleanup_stmt = $connect->prepare("DELETE FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? AND id_transaksi != ?");
                                if ($cleanup_stmt) {
                                    $cleanup_stmt->bind_param('isi', $user_id, $search_pattern, $existing_trans_id);
                                    $cleanup_stmt->execute();
                                    $cleanup_stmt->close();
                                }
                            }
                        } else {
                            // No pending transaction found, create new one with status=approved
                            $trans_stmt = $connect->prepare("INSERT INTO transaksi (id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
                            if ($trans_stmt) {
                                $jenis_trans = 'setoran';
                                $status_trans = 'approved';
                                $trans_stmt->bind_param('isddsss', $user_id, $jenis_trans, $jumlah, $current_saldo, $saldo_terbaru, $keterangan_trans, $status_trans);
                                if ($trans_stmt->execute()) {
                                    $new_trans_id = $connect->insert_id;
                                    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_CREATED insert_id={$new_trans_id} user={$user_id} jenis={$jenis_trans} amt={$jumlah}\n", FILE_APPEND);
                                    // Cleanup other pending duplicates for the same mulai_nabung
                                    $cleanup_stmt = $connect->prepare("DELETE FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? AND id_transaksi != ?");
                                    if ($cleanup_stmt) {
                                        $cleanup_stmt->bind_param('isi', $user_id, $search_pattern, $new_trans_id);
                                        $cleanup_stmt->execute();
                                        $cleanup_stmt->close();
                                    }
                                } else {
                                    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_INSERT_ERROR: ".$trans_stmt->error."\n", FILE_APPEND);
                                }
                                $trans_stmt->close();
                            }
                        }
                        $existing_trans_query->close();
                    }
                }

                // Create approval notification using centralized helper (normalizes type to 'tabungan')
                try {
                    require_once __DIR__ . '/notif_helper.php';
                    // Use approval time to set notification timestamp and include amount/jenis in message/data
                    $created_ts = $approved_at;
                    $nid = create_mulai_nabung_notification($connect, $user_id, $id, isset($ins_id) ? $ins_id : null, $created_ts, 'berhasil', $jumlah, $jenis_tabungan);
                    if ($nid !== false) {
                        $notif_id = $nid;
                        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] NOTIF_HELPER_CREATED_APPROVE id={$notif_id} user={$user_id} mulai_id={$id} created_ts={$created_ts}\n", FILE_APPEND);
                    } else {
                        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] NOTIF_HELPER_SKIPPED_APPROVE user={$user_id} mulai_id={$id} created_ts={$created_ts}\n", FILE_APPEND);
                    }
                } catch (Exception $e) {
                    @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Exception creating approve notification: " . $e->getMessage() . "\n", FILE_APPEND);
                }

                $ok = true;
            } else {
                // Legacy fallback: if tabungan_masuk does not exist, preserve existing behavior (wallet credit + move)
                $ok = wallet_credit($connect, intval($user_id), $jumlah, 'Topup approve (mulai_nabung ' . $id . ')');
                if (!$ok) {
                    throw new Exception('Gagal mengkredit wallet untuk user ' . $user_id);
                }
                // Move from wallet -> tabungan
                $ok2 = move_wallet_to_tabungan($connect, intval($user_id), $jumlah, $validated_jenis, 'Auto move topup (mulai_nabung ' . $id . ')');
                if (!$ok2) {
                    throw new Exception('Gagal memindahkan dana ke tabungan untuk user ' . $user_id);
                }

                // Fetch new saldo after wallet_credit
                $sq = $connect->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
                $sq->bind_param('i', $user_id);
                $sq->execute();
                $rsq = $sq->get_result();
                $sqrow = $rsq->fetch_assoc();
                $saldo_terbaru = floatval($sqrow['saldo']);
                $sq->close();

                $ok = $ok2;

                // For legacy flow also add the formal "Mulai Nabung" notification (complementary)
                if ($ok) {
                    try {
                        require_once __DIR__ . '/notif_helper.php';
                        $created_ts = isset($created) ? $created : (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
                        $nid = create_mulai_nabung_notification($connect, $user_id, $id, isset($ins_id) ? $ins_id : null, $created_ts, 'berhasil', $jumlah, $jenis_tabungan);
                        if ($nid !== false) {
                            $notif_id = $nid;
                            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] NOTIF_HELPER_CREATED_LEGACY id={$notif_id} user={$user_id} mulai_id={$id} created_ts={$created_ts}\n", FILE_APPEND);
                        } else {
                            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] NOTIF_HELPER_SKIPPED_LEGACY user={$user_id} mulai_id={$id} created_ts={$created_ts}\n", FILE_APPEND);
                        }
                    } catch (Exception $e) {
                        @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Exception creating legacy notification: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
            }
            if (!$ok) {
                throw new Exception('Gagal memproses topup untuk user ' . $user_id);
            }
        } else {
            // Not crediting (status might have already been 'berhasil' or was not 'pending'):
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] SKIP_CREDIT id={$id} current_status={$current_status} user={$user_id}\n", FILE_APPEND);
            // Read the latest saldo to include in response
            $sq = $connect->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
            $sq->bind_param('i', $user_id);
            $sq->execute();
            $rsq = $sq->get_result();
            $sqrow = $rsq->fetch_assoc();
            $saldo_terbaru = floatval($sqrow['saldo']);
            $sq->close();
        }

        // Ensure a notification is created for approval regardless of whether we performed a credit.
        try {
            if (isset($user_id)) {
                require_once __DIR__ . '/notif_helper.php';
                $created_ts = $approved_at;
                $nid = create_mulai_nabung_notification($connect, $user_id, $id, isset($ins_id) ? $ins_id : null, $created_ts, 'berhasil', $jumlah, $jenis_tabungan);
                if ($nid !== false) {
                    $notif_id = $nid;
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] NOTIF_HELPER_CREATED id={$notif_id} user={$user_id} mulai_id={$id} created_ts={$created_ts}\n", FILE_APPEND);
                } else {
                    @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " create_mulai_nabung_notification returned false for user={$user_id} mulai_id={$id} created_ts={$created_ts}\n", FILE_APPEND);
                }
            }
        } catch (Exception $e) {
            @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Exception creating post-approval notification: " . $e->getMessage() . "\n", FILE_APPEND);
        }

    } else { // tolak
        if (!($u = $connect->prepare("UPDATE mulai_nabung SET status = ?, updated_at = NOW() WHERE id_mulai_nabung = ?"))) {
            throw new Exception('Gagal prepare update status reject: ' . $connect->error);
        }
        $new_status = 'ditolak';
        $u->bind_param('si', $new_status, $id);
        if (!$u->execute()) {
            throw new Exception('Gagal update status reject: ' . $u->error);
        }
        $u->close();

        // UPDATE/CREATE transaction record to show rejection status
        try {
            // Resolve user for transaction record - use notif_helper function
            require_once __DIR__ . '/notif_helper.php';
            $reject_user_id = resolve_pengguna_id_from_mulai($connect, $id_tabungan, $nomor_hp ?? null, $nama_pengguna ?? null);
            
            if ($reject_user_id !== null) {
                // Get current saldo
                $sq_reject = $connect->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
                if ($sq_reject) {
                    $sq_reject->bind_param('i', $reject_user_id);
                    $sq_reject->execute();
                    $rsq_reject = $sq_reject->get_result();
                    $sqrow_reject = $rsq_reject->fetch_assoc();
                    $saldo_reject = floatval($sqrow_reject['saldo']);
                    $sq_reject->close();
                    
                    // Check if transaksi table exists
                    $table_check = @$connect->query("DESCRIBE transaksi");
                    if ($table_check) {
                        // First, try to find and UPDATE the existing pending transaction for this mulai_nabung
                        $keterangan_trans_reject = 'Setoran Tabungan Ditolak (mulai_nabung ' . $id . ')';  // Include ID for API parsing in get_riwayat_transaksi.php
                        // Use 'tanggal' column for ordering; 'created_at' does not exist on transaksi
                        $existing_trans_query = $connect->prepare("SELECT id_transaksi FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? ORDER BY tanggal DESC LIMIT 1");
                        $search_pattern = '%mulai_nabung ' . $id . '%';
                        if ($existing_trans_query) {
                            $existing_trans_query->bind_param('is', $reject_user_id, $search_pattern);
                            $existing_trans_query->execute();
                            $existing_res = $existing_trans_query->get_result();
                            
                            if ($existing_res && $existing_res->num_rows > 0) {
                                // UPDATE existing pending transaction to rejected
                                $existing_row = $existing_res->fetch_assoc();
                                $existing_trans_id = $existing_row['id_transaksi'];
                                $update_trans_stmt = $connect->prepare("UPDATE transaksi SET status = ?, keterangan = ? WHERE id_transaksi = ?");
                                if ($update_trans_stmt) {
                                    $status_trans_reject = 'rejected';
                                    $update_trans_stmt->bind_param('ssi', $status_trans_reject, $keterangan_trans_reject, $existing_trans_id);
                                    if ($update_trans_stmt->execute()) {
                                        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_UPDATED (from pending to rejected) id=".$existing_trans_id." user={$reject_user_id}\n", FILE_APPEND);
                                    } else {
                                        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_UPDATE_ERROR (reject): ".$update_trans_stmt->error."\n", FILE_APPEND);
                                    }
                                    $update_trans_stmt->close();
                                    // Cleanup other pending duplicates for the same mulai_nabung (reject)
                                    $cleanup_stmt = $connect->prepare("DELETE FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? AND id_transaksi != ?");
                                    if ($cleanup_stmt) {
                                        $cleanup_stmt->bind_param('isi', $reject_user_id, $search_pattern, $existing_trans_id);
                                        $cleanup_stmt->execute();
                                        $cleanup_stmt->close();
                                    }
                                }
                            } else {
                                // No pending transaction found, create new one with status=rejected
                                $trans_reject_stmt = $connect->prepare("INSERT INTO transaksi (id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
                                if ($trans_reject_stmt) {
                                    $jenis_trans_reject = 'setoran';
                                    $status_trans_reject = 'rejected';
                                    $trans_reject_stmt->bind_param('isddsss', $reject_user_id, $jenis_trans_reject, $jumlah, $saldo_reject, $saldo_reject, $keterangan_trans_reject, $status_trans_reject);
                                    if ($trans_reject_stmt->execute()) {
                                        $new_reject_id = $connect->insert_id;
                                        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_REJECT_CREATED insert_id={$new_reject_id} user={$reject_user_id} status=rejected\n", FILE_APPEND);
                                        // Cleanup other pending duplicates for the same mulai_nabung (reject)
                                        $cleanup_stmt = $connect->prepare("DELETE FROM transaksi WHERE id_pengguna = ? AND jenis_transaksi = 'setoran' AND status = 'pending' AND keterangan LIKE ? AND id_transaksi != ?");
                                        if ($cleanup_stmt) {
                                            $cleanup_stmt->bind_param('isi', $reject_user_id, $search_pattern, $new_reject_id);
                                            $cleanup_stmt->execute();
                                            $cleanup_stmt->close();
                                        }
                                    } else {
                                        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] TRANSAKSI_REJECT_INSERT_ERROR: ".$trans_reject_stmt->error."\n", FILE_APPEND);
                                    }
                                    $trans_reject_stmt->close();
                                }
                            }
                            $existing_trans_query->close();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            @file_put_contents(__DIR__.'/api_debug.log', date('c')." [admin_verifikasi_mulai_nabung] Exception creating reject transaction: ".$e->getMessage()."\n", FILE_APPEND);
            // Non-fatal: continue with notification
        }

        // Create rejection notification
        try {
            // Resolve user via helper function
            require_once __DIR__ . '/notif_helper.php';
            $notif_user_id = resolve_pengguna_id_from_mulai($connect, $id_tabungan, $nomor_hp ?? null, $nama_pengguna ?? null);

            if ($notif_user_id !== null) {
                $created_ts_reject = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
                $nid = create_mulai_nabung_notification($connect, $notif_user_id, $id, null, $created_ts_reject, 'ditolak', $jumlah, $jenis_tabungan ?? null);
                if ($nid !== false) {
                    $notif_id = $nid;
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] NOTIF_HELPER_CREATED_REJECT id={$notif_id} user={$notif_user_id} mulai_id={$id} created_ts={$created_ts_reject}\n", FILE_APPEND);
                } else {
                    @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " create_mulai_nabung_notification returned false (reject) user={$notif_user_id} mulai_id={$id} created_ts={$created_ts_reject}\n", FILE_APPEND);
                }
            } else {
                @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Could not resolve pengguna for reject mulai_id={$id} id_tabungan={$id_tabungan}\n", FILE_APPEND);
            }
        } catch (Exception $e) {
            @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Exception creating reject notification: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    // Commit transaction
    $connect->commit();
    $ret = ["success" => true, "message" => "Verifikasi berhasil"];
    if (isset($ins_id)) $ret['tabungan_masuk_id'] = intval($ins_id);
    if (isset($notif_id)) $ret['notifikasi_id'] = intval($notif_id);
    if (isset($saldo_terbaru)) $ret['saldo_terbaru'] = (int) round($saldo_terbaru);
    $out = json_encode($ret);
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] OK " . $out . "\n", FILE_APPEND);
    echo $out;
    exit();

} catch (Exception $e) {
    // Rollback and log extended details to help capture the root cause
    if (isset($connect) && $connect) {
        $connect->rollback();
    }
    $err_details = array(
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'input' => isset($_POST) ? $_POST : null,
        'id' => isset($id) ? $id : null,
        'action' => isset($action) ? $action : null,
    );
    @file_put_contents(__DIR__ . '/admin_verifikasi_error.log', date('c') . " Error: " . var_export($err_details, true) . "\n", FILE_APPEND);

    $out = json_encode(["success" => false, "message" => $e->getMessage()]);
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [admin_verifikasi_mulai_nabung] ERR " . $out . "\n", FILE_APPEND);
    echo $out;
    exit();
}


