<?php
// api/pinjaman_kredit/admin_action.php
// Perform admin actions: approve/reject/cancel (no edit/delete). Records to pinjaman_kredit_log and updates approved_by/approved_at as needed

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

session_start();
// Log raw request and session for debugging
@file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " REQ: " . json_encode($_POST) . " SESSION: " . json_encode($_SESSION) . "\n", FILE_APPEND | LOCK_EX);
if (!isset($_SESSION['id_user'])) {
    @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: Unauthorized (no session)\n", FILE_APPEND | LOCK_EX);
    echo json_encode(['status'=>false,'message'=>'Unauthorized']); exit;
}
$id_admin = (int)$_SESSION['id_user'];
require_once __DIR__ . '/../../config/db.php';

// Only POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: Method Not Allowed\n", FILE_APPEND | LOCK_EX); echo json_encode(['status'=>false,'message'=>'Method Not Allowed']); exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
action:
$action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
$note = isset($_POST['note']) ? trim($_POST['note']) : null;

if ($id <= 0) { @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: Invalid id - " . json_encode(['id'=>$id]) . "\n", FILE_APPEND | LOCK_EX); echo json_encode(['status'=>false,'message'=>'Invalid id']); exit; }
$allowed = ['approve','reject','cancel'];
if (!in_array($action, $allowed, true)) { @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: Invalid action - " . json_encode(['action'=>$action]) . "\n", FILE_APPEND | LOCK_EX); echo json_encode(['status'=>false,'message'=>'Invalid action']); exit; }

// Fetch current row
$r = mysqli_query($con, "SELECT status FROM pinjaman_kredit WHERE id = $id LIMIT 1");
if (!$r) { @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: DB error select - " . mysqli_error($con) . "\n", FILE_APPEND | LOCK_EX); echo json_encode(['status'=>false,'message'=>'DB error','error'=>mysqli_error($con)]); exit; }
$cur = mysqli_fetch_assoc($r); mysqli_free_result($r);
if (!$cur) { @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: Not found id={$id}\n", FILE_APPEND | LOCK_EX); echo json_encode(['status'=>false,'message'=>'Not found']); exit; }
$current_status = $cur['status'];
if ($current_status !== 'pending' && $action === 'approve') {
    @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: Invalid approve - current_status={$current_status} id={$id}\n", FILE_APPEND | LOCK_EX);
    echo json_encode(['status'=>false,'message'=>'Hanya pengajuan dengan status pending dapat disetujui']); exit;
}

// Prevent any modification to previously approved/rejected applications
if ($current_status !== 'pending' && $action !== 'cancel') {
    @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: Invalid modification - current_status={$current_status} action={$action} id={$id}\n", FILE_APPEND | LOCK_EX);
    echo json_encode(['status'=>false,'message'=>'Tidak diperkenankan mengubah pengajuan yang sudah diproses.']); exit;
}

// Map action to new status
$newStatus = null;
if ($action === 'approve') $newStatus = 'approved';
if ($action === 'reject') $newStatus = 'rejected';
if ($action === 'cancel') $newStatus = 'cancelled';

// Inspect available columns in pinjaman_kredit and pinjaman_kredit_log to be robust
$tblCols = [];
$crcols = mysqli_query($con, "SHOW COLUMNS FROM pinjaman_kredit");
if ($crcols) {
    while ($cc = mysqli_fetch_assoc($crcols)) $tblCols[] = $cc['Field'];
    mysqli_free_result($crcols);
}
$logCols = [];
$cl = @mysqli_query($con, "SHOW COLUMNS FROM pinjaman_kredit_log");
if ($cl) {
    while ($lc = mysqli_fetch_assoc($cl)) $logCols[] = $lc['Field'];
    mysqli_free_result($cl);
}

// Update row: status, approved_by/approved_at, catatan_admin
$updateFields = [];
$updateFields[] = "status = '" . mysqli_real_escape_string($con, $newStatus) . "'";
if ($newStatus === 'approved') {
    if (in_array('approved_by', $tblCols, true)) { $updateFields[] = "approved_by = $id_admin"; }
    if (in_array('approved_at', $tblCols, true)) { $updateFields[] = "approved_at = NOW()"; }
}
if ($note !== null && $note !== '') {
    if (in_array('catatan_admin', $tblCols, true)) {
        $updateFields[] = "catatan_admin = '" . mysqli_real_escape_string($con, $note) . "'";
    } elseif (in_array('keterangan', $tblCols, true)) {
        $updateFields[] = "keterangan = CONCAT(IFNULL(keterangan,''), '\n[Catatan admin] ', '" . mysqli_real_escape_string($con, $note) . "')";
    }
}
// If rejecting, prefer to store the explicit reason into 'keterangan' if that column exists, otherwise fallback to catatan_admin
if ($action === 'reject' && $reason !== null && $reason !== '') {
    if (in_array('keterangan', $tblCols, true)) {
        $updateFields[] = "keterangan = '" . mysqli_real_escape_string($con, $reason) . "'";
    } elseif (in_array('catatan_admin', $tblCols, true)) {
        $updateFields[] = "catatan_admin = CONCAT(IFNULL(catatan_admin,''), '\n[Alasan penolakan] ', '" . mysqli_real_escape_string($con, $reason) . "')";
    }
}
$updQ = "UPDATE pinjaman_kredit SET " . implode(', ', $updateFields) . " WHERE id = $id";
@file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " UPDQ: " . $updQ . "\n", FILE_APPEND | LOCK_EX);
@file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " TABLE_COLS: " . json_encode($tblCols) . "\n", FILE_APPEND | LOCK_EX);
try {
    $resUpd = mysqli_query($con, $updQ);
    if ($resUpd === false) {
        @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: DB update failed id={$id} err=" . mysqli_error($con) . "\n", FILE_APPEND | LOCK_EX);
        echo json_encode(['status'=>false,'message'=>'DB update failed','error'=>mysqli_error($con)]);
        exit;
    }
} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: DB update exception id={$id} ex=" . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    echo json_encode(['status'=>false,'message'=>'DB update exception','error'=>$e->getMessage()]);
    exit;
}

// Insert log (only include columns that exist in the log table)
$prev = mysqli_real_escape_string($con, $current_status);
$now = mysqli_real_escape_string($con, $newStatus);
$reasonEsc = $reason ? "'" . mysqli_real_escape_string($con, $reason) . "'" : 'NULL';
$noteEsc = $note ? "'" . mysqli_real_escape_string($con, $note) . "'" : 'NULL';

$logColsToIns = [];
$logVals = [];
if (in_array('pinjaman_id', $logCols, true)) { $logColsToIns[] = 'pinjaman_id'; $logVals[] = $id; }
if (in_array('previous_status', $logCols, true)) { $logColsToIns[] = 'previous_status'; $logVals[] = "'" . $prev . "'"; }
if (in_array('new_status', $logCols, true)) { $logColsToIns[] = 'new_status'; $logVals[] = "'" . $now . "'"; }
if (in_array('changed_by', $logCols, true)) { $logColsToIns[] = 'changed_by'; $logVals[] = $id_admin; }
if (in_array('reason', $logCols, true)) { $logColsToIns[] = 'reason'; $logVals[] = ($reason ? "'" . mysqli_real_escape_string($con,$reason) . "'" : 'NULL'); }
if (in_array('note', $logCols, true)) { $logColsToIns[] = 'note'; $logVals[] = ($note ? "'" . mysqli_real_escape_string($con,$note) . "'" : 'NULL'); }
if (in_array('created_at', $logCols, true)) { $logColsToIns[] = 'created_at'; $logVals[] = 'NOW()'; }

if (!empty($logColsToIns)) {
    $insLog = "INSERT INTO pinjaman_kredit_log (" . implode(', ', $logColsToIns) . ") VALUES (" . implode(', ', $logVals) . ")";
    if (!mysqli_query($con, $insLog)) { @file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: DB insert log failed id={$id} err=" . mysqli_error($con) . "\n", FILE_APPEND | LOCK_EX); echo json_encode(['status'=>false,'message'=>'DB insert log failed','error'=>mysqli_error($con)]); exit; }
}

// Return success JSON for all actions (approve/reject/cancel) — respond early so client isn't blocked by notification logic
@file_put_contents(__DIR__ . '/admin_action_debug.log', date('c') . " RESP: success id={$id} new_status={$newStatus} action={$action} admin={$id_admin}\n", FILE_APPEND | LOCK_EX);
// Return a human-friendly message in Indonesian corresponding to the action/result
$humanMsg = 'Action applied';
if ($newStatus === 'approved') {
    $humanMsg = 'Pengajuan disetujui';
} elseif ($newStatus === 'rejected') {
    // Include provided reason when available to help the admin and user
    $humanMsg = 'Pengajuan ditolak' . ($reason && trim($reason) !== '' ? ': ' . $reason : '');
} elseif ($newStatus === 'cancelled') {
    $humanMsg = 'Pengajuan dibatalkan';
}
echo json_encode(['status'=>true,'message'=>$humanMsg,'new_status'=>$newStatus]);
// attempt post-success side-effects (notifications, transaksi), but do not block or fail the response
if ($newStatus === 'approved') {
    try {
        require_once __DIR__ . '/../../flutter_api/notif_helper.php';
        require_once __DIR__ . '/../../flutter_api/transaksi_helper.php';
        // Get application details to craft a message
        $r2 = mysqli_query($con, "SELECT id_pengguna, pokok, total_bayar, nama_barang FROM pinjaman_kredit WHERE id = $id LIMIT 1");
        if ($r2 && ($app = mysqli_fetch_assoc($r2))) {
            $userId = intval($app['id_pengguna']);
            $amount = floatval($app['pokok']);
            $title = 'Pengajuan pinjaman disetujui';
            $msg = 'Pengajuan pinjaman kredit untuk "' . ($app['nama_barang'] ?? '') . '" sebesar ' . number_format($amount,0,',','.') . ' telah disetujui.';
            try { @safe_create_notification($con, $userId, 'pinjaman_kredit', $title, $msg, json_encode(['application_id' => $id, 'amount' => $amount])); } catch (Throwable $_t) { @file_put_contents(__DIR__ . '/admin_action_err.log', date('c') . " NOTIF_ERR id={$id} err=" . $_t->getMessage() . "\n", FILE_APPEND); }
            // Insert into transaksi table (jenis_transaksi=pinjaman_kredit_approved)
            $txPayload = [
                'id_tabungan' => $userId,
                'jenis_transaksi' => 'pinjaman_kredit_approved',
                'jumlah' => $amount,
                'keterangan' => $msg,
                'tanggal' => date('Y-m-d H:i:s')
            ];
            try { record_transaction($con, $txPayload); } catch (Throwable $_t) { @file_put_contents(__DIR__ . '/admin_action_err.log', date('c') . " TRANSACTION_ERR id={$id} err=" . $_t->getMessage() . "\n", FILE_APPEND); }
        }
    } catch (Throwable $_e) {
        @file_put_contents(__DIR__ . '/admin_action_err.log', date('c') . " PINJAMAN_APPROVE_NOTIF_ERR id={$id} err=" . $_e->getMessage() . "\n", FILE_APPEND);
    }
} elseif ($newStatus === 'rejected') {
    // Notify user about rejection
    try {
        require_once __DIR__ . '/../../flutter_api/notif_helper.php';
        $r2 = mysqli_query($con, "SELECT id_pengguna, pokok, nama_barang FROM pinjaman_kredit WHERE id = $id LIMIT 1");
        if ($r2 && ($app = mysqli_fetch_assoc($r2))) {
            $userId = intval($app['id_pengguna']);
            $amount = floatval($app['pokok'] ?? 0);
            $title = 'Pengajuan pinjaman ditolak';
            $reasonText = ($reason && trim($reason) !== '') ? $reason : 'Tidak ada alasan';
            $msg = ($app['nama_barang'] ? ('Pengajuan pinjaman Anda untuk "' . $app['nama_barang'] . '" sebesar ' . ($amount ? number_format($amount,0,',','.') . ' ' : '') . 'telah ditolak oleh admin. ') : 'Pengajuan pinjaman Anda telah ditolak oleh admin. ') . 'Alasan: ' . $reasonText;
            try { @safe_create_notification($con, $userId, 'pinjaman_kredit', $title, $msg, json_encode(['application_id' => $id, 'amount' => $amount])); } catch (Throwable $_t) { @file_put_contents(__DIR__ . '/admin_action_err.log', date('c') . " NOTIF_REJECT_ERR id={$id} err=" . $_t->getMessage() . "\n", FILE_APPEND); }
        }
    } catch (Throwable $_e) {
        @file_put_contents(__DIR__ . '/admin_action_err.log', date('c') . " PINJAMAN_REJECT_NOTIF_ERR id={$id} err=" . $_e->getMessage() . "\n", FILE_APPEND);
    }
}
exit;