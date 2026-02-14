<?php 
/**
 * API: Approve/Reject Penarikan (Admin Only)
 * Untuk admin menyetujui atau menolak penarikan
 * 
 * DEBUGGING MODE: Shows real errors instead of "Internal server error"
 */

// Set timezone ke Indonesia (UTC+7) - PENTING agar date() menghasilkan waktu WIB
date_default_timezone_set('Asia/Jakarta');

// ENABLE DEBUGGING - Show real errors before loading connection.php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Set flag to tell connection.php we want debug output
$GLOBALS['FLUTTER_API_DEBUG_MODE'] = true;

include 'connection.php';

// Include ledger helper functions (for create_withdrawal_transaction_record)
require_once __DIR__ . '/../login/function/ledger_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required_fields = ['no_keluar', 'action', 'approved_by'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            echo json_encode(array(
                "success" => false,
                "message" => "Field $field wajib diisi"
            ));
            exit();
        }
    }
    
    $no_keluar = $connect->real_escape_string($_POST['no_keluar']);
    $action = $_POST['action']; // 'approve' atau 'reject'
    $approved_by = intval($_POST['approved_by']); // id admin
    $catatan = isset($_POST['catatan']) ? $connect->real_escape_string($_POST['catatan']) : '';
    
    // Validasi action
    if (!in_array($action, ['approve', 'reject'])) {
        echo json_encode(array(
            "success" => false,
            "message" => "Action harus 'approve' atau 'reject'"
        ));
        exit();
    }
    
    // Strict modern flow: operate only on `tabungan_keluar` per final schema
    $penarikan = null;
    $matches = null;
    $tkid = null;
    if (preg_match('/^TK-\d{14}-(\d+)$/', $no_keluar, $matches)) {
        $tkid = intval($matches[1]);
    } elseif (ctype_digit($no_keluar)) {
        $tkid = intval($no_keluar);
    }

    if ($tkid === null) {
        echo json_encode(array(
            "success" => false,
            "message" => "Identifier tidak valid"
        ));
        exit();
    }

    // Ensure tabungan_keluar exists
    $rchk = $connect->query("SHOW TABLES LIKE 'tabungan_keluar'");
    if (!($rchk && $rchk->num_rows > 0)) {
        echo json_encode(array("success" => false, "message" => "Server misconfigured: tabungan_keluar not found"));
        exit();
    }

    // Select pending row by id
    $stmtTk = $connect->prepare("SELECT id, id_pengguna, id_jenis_tabungan, jumlah, keterangan, status, created_at FROM tabungan_keluar WHERE id = ? AND status = 'pending' LIMIT 1");
    if (!$stmtTk) {
        echo json_encode(array("success" => false, "message" => "DB prepare failed"));
        exit();
    }
    $stmtTk->bind_param('i', $tkid);
    $stmtTk->execute(); $r = $stmtTk->get_result();
    if (!($r && $r->num_rows > 0)) {
        echo json_encode(array(
            "success" => false,
            "message" => "Data penarikan tidak ditemukan atau sudah diproses"
        ));
        exit();
    }
    $rowTk = $r->fetch_assoc();
    $penarikan = $rowTk;
    $id_tabungan = $penarikan['id_pengguna'];
    $jumlah = floatval($penarikan['jumlah']);
    $id_jenis_tabungan = intval($penarikan['id_jenis_tabungan']);
    $nama = null;
    $keterangan = $penarikan['keterangan'];
    $tanggal = $penarikan['created_at'];
    $stmtTk->close();
    
    // Get id_pengguna from id_tabungan (nis, no_hp or id). Be tolerant with different schema variants.
    $id_tabungan_esc = $connect->real_escape_string($id_tabungan);
    // Build SELECT columns for pengguna dynamically based on actual schema
    $pengCols = [];
    $rcolsp = $connect->query("SHOW COLUMNS FROM pengguna");
    if ($rcolsp) {
        while ($f = $rcolsp->fetch_assoc()) {
            $pengCols[] = $f['Field'];
        }
    }
    $selParts = [];
    if (in_array('id_pengguna', $pengCols)) $selParts[] = 'id_pengguna AS id_pengguna';
    $selParts[] = 'id AS id';
    if (in_array('saldo', $pengCols)) $selParts[] = 'saldo';
    if (in_array('nis', $pengCols)) $selParts[] = 'nis';
    if (in_array('no_hp', $pengCols)) $selParts[] = 'no_hp';
    if (in_array('nama', $pengCols)) $selParts[] = 'nama';
    if (in_array('status', $pengCols) || in_array('status_akun', $pengCols)) {
        $selParts[] = "COALESCE(" . (in_array('status', $pengCols) ? 'status' : "''") . ", " . (in_array('status_akun', $pengCols) ? 'status_akun' : "''") . ", '') as status_val";
    } else {
        $selParts[] = "'' as status_val";
    }
    $selCols = implode(', ', $selParts);

    // Try several candidate lookup columns if they exist in this schema
    $result_siswa = false;
    $candidates = ['nis', 'no_hp', 'id_tabungan', 'nis_siswa']; // common variants
    foreach ($candidates as $cand) {
        $rc = $connect->query("SHOW COLUMNS FROM pengguna LIKE '" . $connect->real_escape_string($cand) . "'");
        if ($rc && $rc->num_rows > 0) {
            $sql_siswa = "SELECT $selCols FROM pengguna WHERE " . $cand . "='" . $id_tabungan_esc . "' LIMIT 1";
            $result_siswa = $connect->query($sql_siswa);
            if ($result_siswa && $result_siswa->num_rows > 0) break;
        }
    }

    // finally try matching by numeric id
    if ((!$result_siswa) || $result_siswa->num_rows == 0) {
        $sql_siswa = "SELECT $selCols FROM pengguna WHERE id='" . intval($id_tabungan_esc) . "' LIMIT 1";
        $result_siswa = $connect->query($sql_siswa);
    }

    if (!$result_siswa || $result_siswa->num_rows == 0) {
        echo json_encode(array(
            "success" => false,
            "message" => "Data anggota tidak ditemukan"
        ));
        exit();
    }

    $siswa = $result_siswa->fetch_assoc();
    $id_pengguna = !empty($siswa['id_pengguna']) ? $siswa['id_pengguna'] : $siswa['id'];
    $saldo_current = floatval($siswa['saldo']);
    $status_val = strtolower($siswa['status_val']);
    // Accept several variants of active/approved
    $ok_status = (strpos($status_val, 'aktif') !== false) || (strpos($status_val, 'verifik') !== false) || trim($status_val) === '1' || $status_val === 'active' || $status_val === '';
    if (!$ok_status) {
        echo json_encode(array(
            "success" => false,
            "message" => "Data anggota tidak ditemukan atau tidak aktif"
        ));
        exit();
    }
    
    // Start transaction
    $connect->begin_transaction();
    
    try {
        // Re-lock the withdrawal row to prevent double-processing (SELECT ... FOR UPDATE)
        $stmtLock = $connect->prepare("SELECT status FROM tabungan_keluar WHERE id = ? FOR UPDATE");
        if (!$stmtLock) throw new Exception('DB prepare failed for lock: ' . $connect->error);
        $stmtLock->bind_param('i', $penarikan['id']);
        $stmtLock->execute(); $rlock = $stmtLock->get_result();
        if (!($rlock && $rlock->num_rows > 0)) throw new Exception('Data penarikan tidak ditemukan saat lock');
        $rlrow = $rlock->fetch_assoc();
        if (strtolower($rlrow['status']) !== 'pending') throw new Exception('Data penarikan tidak ditemukan atau sudah diproses');
        $stmtLock->close();
        // ADMIN: approve => debit tabungan_masuk.jumlah and mark tabungan_keluar APPROVED. All in a transaction.
        if ($action == 'approve') {
            // 1) Use centralized withdrawal helper to atomically deduct from saved balance
            require_once __DIR__ . '/../login/function/ledger_helpers.php';
            
            $new_saldo_per_jenis = withdrawal_deduct_saved_balance($connect, $id_tabungan, $id_jenis_tabungan, $jumlah);
            if ($new_saldo_per_jenis === false) {
                throw new Exception('Saldo tabungan tidak mencukupi atau data berubah');
            }

            // 2) Mark tabungan_keluar as approved (only if still pending - prevents double approve)
            $stmtApprove = $connect->prepare("UPDATE tabungan_keluar SET status = 'approved', updated_at = NOW() WHERE id = ? AND status = 'pending'");
            if (!$stmtApprove) throw new Exception('DB prepare failed for approve: ' . $connect->error);
            $stmtApprove->bind_param('i', $penarikan['id']);
            $stmtApprove->execute();
            $aff = $stmtApprove->affected_rows;
            $stmtApprove->close();
            
            if ($aff <= 0) {
                throw new Exception('Data penarikan sudah diproses atau tidak ditemukan');
            }

            // 3) Credit wallet (saldo_bebas = pengguna.saldo)
            $creditAmt = $jumlah;
            $creditNote = "Approval of withdrawal from {$id_jenis_tabungan}";
            $new_peng_saldo = withdrawal_credit_wallet($connect, $id_tabungan, $creditAmt, $creditNote);
            if ($new_peng_saldo === false) {
                throw new Exception('Gagal mengkredit saldo pengguna');
            }

            // 4) Create transaction record for audit/history
            $txId = create_withdrawal_transaction_record($connect, $id_tabungan, $id_jenis_tabungan, $jumlah, $penarikan['id'], 'Withdrawal approved');
            if ($txId === false) {
                @file_put_contents(__DIR__ . '/saldo_audit.log', date('c') . " APPROVE_PENARIKAN_TX_CREATION_FAILED user={$id_tabungan} tab_keluar_id={$penarikan['id']} but saldo already updated\n", FILE_APPEND);
                // Continue anyway - saldo was updated, transaction record is optional
            }
            $message = "Penarikan berhasil disetujui";
            $new_saldo = $new_saldo_per_jenis;

        } else { // reject
            // REJECTION: Update tabungan_keluar status to 'rejected' ENUM value + save rejection reason
            // Step 1: Validate required variables before proceeding
            if (empty($penarikan['id'])) {
                throw new Exception('Validation failed: penarikan id kosong');
            }
            if (empty($id_tabungan) || empty($id_jenis_tabungan)) {
                throw new Exception('Validation failed: id_tabungan atau id_jenis_tabungan kosong');
            }
            
            // Step 2: Prepare UPDATE statement with explicit error check
            $sql_reject = "UPDATE tabungan_keluar SET status = 'rejected', rejected_reason = ?, updated_at = NOW() WHERE id = ? AND status = 'pending'";
            $stmtReject = $connect->prepare($sql_reject);
            if ($stmtReject === false) {
                throw new Exception('Prepare UPDATE failed: ' . $connect->error);
            }
            
            // Step 3: Bind parameters with explicit error check
            if ($stmtReject->bind_param('si', $catatan, $penarikan['id']) === false) {
                $stmtReject->close();
                throw new Exception('Bind parameter failed: ' . $stmtReject->error);
            }
            
            // Step 4: Execute UPDATE with explicit error check
            if ($stmtReject->execute() === false) {
                $error_detail = $stmtReject->error;
                $stmtReject->close();
                throw new Exception('Execute UPDATE failed: ' . $error_detail);
            }
            
            $affected_rows = $stmtReject->affected_rows;
            $stmtReject->close();
            
            // Step 5: Verify update was successful (must affect exactly 1 row)
            if ($affected_rows <= 0) {
                throw new Exception('Rejection update failed: no rows affected. Status mungkin bukan pending atau record tidak ditemukan.');
            }
            
            // Step 6: Get current balance for rejected withdrawal (unchanged for rejection)
            $sql_balance = "SELECT COALESCE(SUM(jumlah),0) AS total_saldo FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?";
            $stmtBalance = $connect->prepare($sql_balance);
            if ($stmtBalance === false) {
                throw new Exception('Prepare SELECT balance failed: ' . $connect->error);
            }
            
            if ($stmtBalance->bind_param('ii', $id_tabungan, $id_jenis_tabungan) === false) {
                $stmtBalance->close();
                throw new Exception('Bind parameter for balance query failed: ' . $stmtBalance->error);
            }
            
            if ($stmtBalance->execute() === false) {
                $error_detail = $stmtBalance->error;
                $stmtBalance->close();
                throw new Exception('Execute SELECT balance failed: ' . $error_detail);
            }
            
            $resultBalance = $stmtBalance->get_result();
            if ($resultBalance === false) {
                $stmtBalance->close();
                throw new Exception('Get result for balance query failed: ' . $stmtBalance->error);
            }
            
            $rowBalance = $resultBalance->fetch_assoc();
            if ($rowBalance === false) {
                $stmtBalance->close();
                throw new Exception('Fetch balance result failed: ' . $stmtBalance->error);
            }
            
            $new_saldo = floatval($rowBalance['total_saldo'] ?? 0);
            $stmtBalance->close();

            // Step 7: Create transaction record for audit trail (optional, don't fail if this fails)
            try {
                $rejectionNote = "Withdrawal rejected: " . ($catatan ?: 'Admin decision');
                $txId = create_withdrawal_transaction_record($connect, $id_tabungan, $id_jenis_tabungan, $jumlah, $penarikan['id'], $rejectionNote);
                if ($txId === false) {
                    error_log('[approve_penarikan REJECT] Transaction record creation failed for id=' . $penarikan['id'] . ', but rejection already committed');
                }
            } catch (Exception $txErr) {
                error_log('[approve_penarikan REJECT] Transaction record error: ' . $txErr->getMessage());
                // Don't throw - rejection is already committed, just log
            }

            $message = "Pencairan Ditolak";
            $new_peng_saldo = $saldo_current;
        }
        
        // Commit transaction
        $connect->commit();
        
        // NOW AFTER TRANSACTION IS COMMITTED, create notifications
        // This prevents notifications from being rolled back if transaction fails
        error_log("[approve_penarikan] NOTIFICATION_PHASE_STARTED action={$action} user={$id_tabungan}");
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [approve_penarikan] NOTIFICATION_PHASE_STARTED action={$action} user={$id_tabungan}\n", FILE_APPEND);
        
        try {
            require_once __DIR__ . '/notif_helper.php';
            
            // Get jenis name for notification
            $jenis_label = null;
            $sj = $connect->prepare("SELECT nama_jenis FROM jenis_tabungan WHERE id = ? LIMIT 1");
            if ($sj) {
                $sj->bind_param('i', $id_jenis_tabungan);
                $sj->execute();
                $rj = $sj->get_result();
                if ($rj && $rj->num_rows > 0) {
                    $jrow = $rj->fetch_assoc();
                    $jenis_label = $jrow['nama_jenis'] ?? null;
                }
                $sj->close();
            }
            if ($jenis_label === null) $jenis_label = 'Tabungan';
            
            if ($action == 'approve') {
                error_log("[approve_penarikan] CREATING_APPROVAL_NOTIF user={$id_tabungan} jenis={$jenis_label} jumlah={$jumlah}");
                if (function_exists('create_withdrawal_approved_notification')) {
                    $nid = create_withdrawal_approved_notification($connect, $id_tabungan, $jenis_label, $jumlah, $new_peng_saldo, $penarikan['id']);
                    error_log("[approve_penarikan] APPROVAL_NOTIF_RESULT user={$id_tabungan} nid={$nid}");
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [approve_penarikan] APPROVAL_NOTIF user={$id_tabungan} nid={$nid}\n", FILE_APPEND);
                } else {
                    error_log("[approve_penarikan] create_withdrawal_approved_notification NOT FOUND!");
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [approve_penarikan] ERROR: create_withdrawal_approved_notification function not found\n", FILE_APPEND);
                }
            } else {
                error_log("[approve_penarikan] CREATING_REJECTION_NOTIF user={$id_tabungan} jenis={$jenis_label} jumlah={$jumlah}");
                if (function_exists('create_withdrawal_rejected_notification')) {
                    $nid = create_withdrawal_rejected_notification($connect, $id_tabungan, $jenis_label, $jumlah, $catatan ?: 'Tidak ada keterangan', $penarikan['id']);
                    error_log("[approve_penarikan] REJECTION_NOTIF_RESULT user={$id_tabungan} nid={$nid}");
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [approve_penarikan] REJECTION_NOTIF user={$id_tabungan} nid={$nid}\n", FILE_APPEND);
                } else {
                    error_log("[approve_penarikan] create_withdrawal_rejected_notification NOT FOUND!");
                    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [approve_penarikan] ERROR: create_withdrawal_rejected_notification function not found\n", FILE_APPEND);
                }
            }
        } catch (Exception $notifErr) {
            error_log("[approve_penarikan] NOTIFICATION_ERROR: " . $notifErr->getMessage());
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [approve_penarikan] NOTIF_ERROR user={$id_tabungan} err=" . $notifErr->getMessage() . "\n", FILE_APPEND);
        }
        
        // Signal to connection.php that we're outputting JSON
        $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
        
        echo json_encode(array(
            "success" => true,
            "message" => $message,
            "data" => array(
                "no_keluar" => $no_keluar,
                "nama" => $nama,
                "jumlah" => $jumlah,
                "saldo_baru" => $new_saldo,
                "saldo_dashboard" => $new_peng_saldo,
                "status" => $action == 'approve' ? 'approved' : 'rejected'
            )
        ));
        
    } catch (Exception $e) {
        $connect->rollback();
        
        // Multiple logging for debugging
        $errorMsg = $e->getMessage();
        $errorCode = $e->getCode();
        $errorFile = $e->getFile();
        $errorLine = $e->getLine();
        $errorTrace = $e->getTraceAsString();
        
        // PHP error log
        error_log("[approve_penarikan ERROR] action={$action} user={$id_tabungan} code={$errorCode} msg={$errorMsg} file={$errorFile} line={$errorLine}");
        error_log("[approve_penarikan TRACE] " . str_replace("\n", " | ", $errorTrace));
        
        // Audit log file
        @file_put_contents(__DIR__ . '/saldo_audit.log', date('c') . " APPROVE_PENARIKAN_FAILED action={$action} user={$id_tabungan} code={$errorCode} err={$errorMsg} file={$errorFile}:{$errorLine}\n", FILE_APPEND);
        
        // Signal to connection.php that we're outputting JSON (prevents fallback)
        $GLOBALS['FLUTTER_API_JSON_OUTPUT'] = true;
        
        // API response - Include file and line for debugging
        echo json_encode(array(
            "success" => false,
            "message" => "Gagal memproses {$action}: " . $errorMsg,
            "error_code" => $errorCode,
            "error_file" => $errorFile,
            "error_line" => $errorLine,
            "error_trace" => substr($errorTrace, 0, 500)  // Limited trace to avoid huge response
        ));
    }
    
} else {
    echo json_encode(array(
        "success" => false,
        "message" => "Method not allowed. Use POST"
    ));
}

