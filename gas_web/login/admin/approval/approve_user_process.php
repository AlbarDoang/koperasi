<?php
header('Content-Type: application/json');
include '../../koneksi/config.php';
require_once __DIR__ . '/../../../otp_helper.php';
require_once __DIR__ . '/../../../message_templates.php';

// Include centralized Fonnte configuration
$config_path = __DIR__ . '/../../../config/fonnte_constants.php';
if (file_exists($config_path) && !defined('FONNTE_TOKEN')) {
    require_once $config_path;
}

$logFile = __DIR__ . '/approval_log.txt';

// Capture fatal errors and ensure at least a debug line is written to approval_debug.log
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err) {
        @file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " SHUTDOWN: " . var_export($err, true) . "\n", FILE_APPEND);
        // Do NOT automatically emit a JSON error if the script already produced output.
        // Some warnings (eg. failed notify call) may populate error_get_last() but are non-fatal
        // and the script may have already returned a valid JSON response.
        // Only emit a JSON body when no headers have been sent AND no output buffer exists.
        $hasOutput = false;
        if (function_exists('ob_get_length') && ob_get_length() !== false && ob_get_length() > 0) $hasOutput = true;
        if (!headers_sent() && !$hasOutput) {
            header('Content-Type: application/json');
            echo json_encode(['success'=>false,'message'=>'Internal server error']);
        }
    }
});
// read raw input and attempt to parse JSON. Also log for debugging
$raw = file_get_contents('php://input');
@file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " RAW_INPUT: " . substr($raw,0,2000) . "\n", FILE_APPEND);
$body = json_decode($raw, true);
// Fallback: if JSON parse failed, try parse as form-urlencoded
if (!$body || !is_array($body)) {
    parse_str($raw, $parsed);
    if (!empty($parsed)) {
        $body = $parsed;
        @file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " RAW_INPUT parsed as form: " . json_encode($parsed) . "\n", FILE_APPEND);
    }
}
if (!$body || !is_array($body)) {
    @file_put_contents(__DIR__ . '/approval_debug.log', date('c') . " INVALID REQUEST BODY or empty\n", FILE_APPEND);
    echo json_encode(['success'=>false,'message'=>'Invalid request']); exit(); }
$id = isset($body['id']) ? intval($body['id']) : 0;
$action = isset($body['action']) ? $body['action'] : '';
$reason = isset($body['reason']) ? trim($body['reason']) : null;
if ($id<=0) { echo json_encode(['success'=>false,'message'=>'Invalid id']); exit(); }

// fetch user's phone first
$sel = $con->prepare('SELECT no_hp, nama_lengkap FROM pengguna WHERE id = ? LIMIT 1');
if (!$sel) { echo json_encode(['success'=>false,'message'=>'DB error: ' . $con->error]); exit(); }
$sel->bind_param('i', $id);
$sel->execute();
$res = $sel->get_result();
if ($res->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'User not found']); exit(); }
$u = $res->fetch_assoc();
$no_hp = $u['no_hp'];
$nama = $u['nama_lengkap'] ?? '';
$sel->close();

// Fonnte/Fonte token - use centralized config
// NOTE: This should be the WA Admin token and is safe to use for admin-originated messages
$fonnte_token = FONNTE_TOKEN;

if ($action === 'approve'){
    // Check whether status_verifikasi column exists
    $has_col = $con->query("SHOW COLUMNS FROM pengguna LIKE 'status_verifikasi'");
    if ($has_col && $has_col->num_rows > 0) {
        $sql = "UPDATE pengguna SET status_akun='approved', status_verifikasi='ACTIVE' WHERE id = ?";
    } else {
        $sql = "UPDATE pengguna SET status_akun='approved' WHERE id = ?";
    }
    $stmt = $con->prepare($sql);
    $stmt->bind_param('i', $id);
    if ($stmt->execute()){
        // Clear any previous rejection reason if the column exists
        $hasAlasanCol = $con->query("SHOW COLUMNS FROM pengguna LIKE 'alasan_penolakan'");
        if ($hasAlasanCol && $hasAlasanCol->num_rows > 0) {
            try {
                $upd = $con->prepare("UPDATE pengguna SET alasan_penolakan = NULL, updated_at = NOW() WHERE id = ? LIMIT 1");
                if ($upd) { $upd->bind_param('i', $id); $upd->execute(); $upd->close(); }
            } catch (Throwable $e) {
                @file_put_contents($logFile, date('c') . " CLEAR_ALASAN_ERR id={$id} err=" . substr($e->getMessage(),0,400) . "\n", FILE_APPEND);
            }
        }

        // send WhatsApp notification to user (best-effort; do not fail approval if WA fails)
        // ========================================================================
        // Akun Disetujui - Pesan template profesional
        // ========================================================================
        $message = getMessageAccountApproved($nama, 'Tabungan');
        try {
            $wa = sendWhatsAppMessage($no_hp, $message, $fonnte_token);
        } catch (Throwable $e) {
            $wa = ['success' => false, 'message' => $e->getMessage()];
            @file_put_contents($logFile, date('c') . " APPROVE WA_ERROR id={$id} no_hp={$no_hp} err=" . substr($e->getMessage(),0,400) . "\n", FILE_APPEND);
        }
        @file_put_contents($logFile, date('c') . " APPROVE id={$id} no_hp={$no_hp} wa_success=" . (!empty($wa['success']) ? '1' : '0') . " message=" . substr($wa['message'] ?? '',0,200) . "\n", FILE_APPEND);

        // Notify WebSocket broadcaster (best-effort, non-blocking)
        try {
            $cq = $con->query("SELECT COUNT(*) AS total FROM pengguna WHERE LOWER(status_akun) = 'approved'");
            $total = 0;
            if ($cq && $row = $cq->fetch_assoc()) { $total = intval($row['total']); }
            $notifyUrl = 'http://192.168.43.151:6001/notify';
            $payload = json_encode(['event' => 'user-approved', 'totalMembers' => $total]);
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $payload,
                    'timeout' => 2
                ]
            ];
            $context = stream_context_create($opts);
            $resp = @file_get_contents($notifyUrl, false, $context);
            @file_put_contents($logFile, date('c') . " NOTIFY payload={$payload} resp=" . substr(($resp ?? ''),0,200) . "\n", FILE_APPEND);
        } catch (Throwable $e) {
            @file_put_contents($logFile, date('c') . " NOTIFY ERR id={$id} err=" . substr($e->getMessage(),0,400) . "\n", FILE_APPEND);
        }

        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
    }
    exit();
} elseif ($action === 'reject'){
    $has_col = $con->query("SHOW COLUMNS FROM pengguna LIKE 'status_verifikasi'");
    if ($has_col && $has_col->num_rows > 0) {
        $sql = "UPDATE pengguna SET status_akun='rejected', status_verifikasi='REJECTED' WHERE id = ?";
    } else {
        $sql = "UPDATE pengguna SET status_akun='rejected' WHERE id = ?";
    }
    $stmt = $con->prepare($sql);
    $stmt->bind_param('i', $id);
    if ($stmt->execute()){
        // optionally save reason to table verifikasi_pengguna_rejects if exists; also try to persist to pengguna.rejection_reason if column exists
            $safeReason = $con->real_escape_string($reason ?: '');
        if (!empty($safeReason)) {
            // try insert but don't fail if table missing
            try {
                $con->query("INSERT INTO verifikasi_pengguna_rejects (id_pengguna, alasan, created_at) VALUES ({$id}, '{$safeReason}', NOW())");
            } catch (Throwable $e) {
                @file_put_contents($logFile, date('c') . " REJECT INSERT ERR id={$id} err=" . substr($e->getMessage(),0,400) . "\n", FILE_APPEND);
            }
            // try to persist into pengguna.rejection_reason if column exists (backwards compatibility)
            $hasRej = $con->query("SHOW COLUMNS FROM pengguna LIKE 'rejection_reason'");
            if ($hasRej && $hasRej->num_rows > 0) {
                $updrej = $con->prepare("UPDATE pengguna SET rejection_reason = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
                if ($updrej) {
                    $updrej->bind_param('si', $reason, $id);
                    $updrej->execute();
                    $updrej->close();
                }
            }
            // *NEW*: persist into pengguna.alasan_penolakan if this column exists (requested)
            $hasAlasan = $con->query("SHOW COLUMNS FROM pengguna LIKE 'alasan_penolakan'");
            if ($hasAlasan && $hasAlasan->num_rows > 0) {
                $updA = $con->prepare("UPDATE pengguna SET alasan_penolakan = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
                if ($updA) {
                    $updA->bind_param('si', $reason, $id);
                    $updA->execute();
                    $updA->close();
                }
            }
        }

        // send WhatsApp notification to user (best-effort)
        // ========================================================================
        // Akun Ditolak - Pesan template profesional
        // ========================================================================
        $message = getMessageAccountRejected($nama, $reason, 'Tabungan');
        try {
            $wa = sendWhatsAppMessage($no_hp, $message, $fonnte_token);
        } catch (Throwable $e) {
            $wa = ['success' => false, 'message' => $e->getMessage()];
            @file_put_contents($logFile, date('c') . " REJECT WA_ERROR id={$id} no_hp={$no_hp} err=" . substr($e->getMessage(),0,400) . "\n", FILE_APPEND);
        }
        @file_put_contents($logFile, date('c') . " REJECT id={$id} no_hp={$no_hp} reason=" . substr($reason ?? '',0,200) . " wa_success=" . (!empty($wa['success']) ? '1' : '0') . " message=" . substr($wa['message'] ?? '',0,200) . "\n", FILE_APPEND);

        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false,'message'=>$stmt->error]);
    }
    exit();
} else {
    echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
?>
