<?php
session_start();
header('Content-Type: application/json');

include "../../koneksi/config.php";
require_once __DIR__ . '/../../approval_helpers.php';

if (!isset($_SESSION['id_user'])) {
    echo json_encode(["success" => false, "status" => false, "message" => "Unauthorized"]);
    exit();
}

$id_user = $_SESSION['id_user'];
$id_pending = isset($_POST['id_pending']) ? trim($_POST['id_pending']) : '';
$action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
$reason = isset($_POST['reason']) ? $_POST['reason'] : null;

// Accept approve, reject, delete, or note actions
if ($id_pending === '' || ($action !== 'approve' && $action !== 'reject' && $action !== 'delete' && $action !== 'note')) {
    echo json_encode(["success" => false, "status" => false, "message" => "Permintaan tidak valid."]);
    exit();
}

$requestedTable = isset($_POST['table']) ? preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['table'])) : null;
$schemaResult = $requestedTable ? approval_get_schema_for($con, $requestedTable) : approval_get_schema($con);
if (isset($schemaResult['error'])) {
    echo json_encode(["success" => false, "status" => false, "message" => $schemaResult['message'] ?? 'Skema pending transaksi tidak ditemukan.']);
    exit();
}

$schema = $schemaResult['schema'];
$pendingRow = approval_fetch_pending_row($con, $schema, $id_pending);

if (!$pendingRow) {
    echo json_encode(["success" => false, "status" => false, "message" => "Transaksi tidak ditemukan."]);
    exit();
}

if ($pendingRow['status'] !== 'pending') {
    echo json_encode(["success" => false, "status" => false, "message" => "Transaksi sudah diproses sebelumnya."]);
    exit();
}

// helper: update an admin-note style column when available
function _update_admin_note_for($con, $schema, $id, $note) {
    $table = $schema['table'] ?? null;
    if (!$table) return false;

    $candidates = ['catatan_admin','keterangan','catatan_approval','alasan'];
    foreach ($candidates as $col) {
        $q = $con->query(sprintf("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'", $con->real_escape_string($table), $con->real_escape_string($col)));
        if ($q && ($r = $q->fetch_assoc()) && (int)$r['cnt'] > 0) {
            $sql = sprintf("UPDATE `%s` SET `%s` = '%s' WHERE `id` = '%s'", $con->real_escape_string($table), $con->real_escape_string($col), $con->real_escape_string($note), $con->real_escape_string($id));
            return (bool)$con->query($sql);
        }
    }
    return false;
}

// Handle delete action server-side: mark as 'deleted' via approval_update_status
if ($action === 'delete') {
    $ok = approval_update_status($con, $schema, $id_pending, 'deleted', $id_user, 'Dihapus oleh admin');
    if ($ok) {
        echo json_encode(["success" => true, "status" => true, "message" => "Transaksi berhasil dihapus."]);
    } else {
        echo json_encode(["success" => false, "status" => false, "message" => "Gagal menghapus transaksi."]);
    }
    exit();
}

// Handle saving just an admin note without changing status
if ($action === 'note') {
    $note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
    if ($note === '') {
        echo json_encode(["success" => false, "status" => false, "message" => "Catatan kosong."]);
        exit();
    }
    $ok = _update_admin_note_for($con, $schema, $id_pending, $note);
    if ($ok) {
        echo json_encode(["success" => true, "status" => true, "message" => "Catatan admin berhasil disimpan."]);
    } else {
        echo json_encode(["success" => false, "status" => false, "message" => "Gagal menyimpan catatan (kolom tidak ditemukan atau DB error)."]);
    }
    exit();
}

// If a generic note is provided during approve/reject, attempt to save it to an admin note column
$noteParam = isset($_POST['note']) ? trim((string)$_POST['note']) : null;
if ($noteParam !== null && $noteParam !== '') {
    @file_put_contents(__DIR__ . '/debug.log', date('c') . " saving note for id $id_pending\n", FILE_APPEND | LOCK_EX);
    @call_user_func(function() use ($con, $schema, $id_pending, $noteParam) {
        try { _update_admin_note_for($con, $schema, $id_pending, $noteParam); } catch(Throwable $e) { /* non-fatal */ }
    });
}

$result = approval_apply_action($con, $schema, $pendingRow, $action, $id_user, $reason);
// Normalize response to include 'status' boolean for front-end compatibility
if (is_array($result)) {
    if (!array_key_exists('status', $result)) {
        $result['status'] = isset($result['success']) ? (bool)$result['success'] : true;
    }
}
echo json_encode($result);
?>
