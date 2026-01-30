<?php
// api/pinjaman_kredit/admin_event.php
// Small admin endpoint to record lifecycle events for pinjaman_kredit
// Events: barang_dibeli, barang_dikirim, cicilan_aktif, lunas

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['id_user'])) { echo json_encode(['status'=>false,'message'=>'Unauthorized']); exit; }
$id_admin = (int)$_SESSION['id_user'];
require_once __DIR__ . '/../../config/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { echo json_encode(['status'=>false,'message'=>'Method Not Allowed']); exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
event:
$event = isset($_POST['event']) ? trim($_POST['event']) : '';
$note = isset($_POST['note']) ? trim($_POST['note']) : null;

if ($id <= 0) { echo json_encode(['status'=>false,'message'=>'Invalid id']); exit; }
$allowedEvents = ['barang_dibeli','barang_dikirim','cicilan_aktif','lunas'];
if (!in_array($event, $allowedEvents, true)) { echo json_encode(['status'=>false,'message'=>'Invalid event']); exit; }

// Fetch current status
$r = mysqli_query($con, "SELECT status, id_pengguna FROM pinjaman_kredit WHERE id = $id LIMIT 1");
if (!$r) { echo json_encode(['status'=>false,'message'=>'DB error','error'=>mysqli_error($con)]); exit; }
$cur = mysqli_fetch_assoc($r); mysqli_free_result($r);
if (!$cur) { echo json_encode(['status'=>false,'message'=>'Not found']); exit; }
$current_status = $cur['status'];
$user_id = (int)$cur['id_pengguna'];

// Prevent applying events on finalted/invalid statuses
if (in_array($current_status, ['rejected','cancelled','lunas'], true)) {
    echo json_encode(['status'=>false,'message'=>'Tidak dapat menerapkan event pada status saat ini.']); exit;
}

// Map event to new status and note
$eventNote = '';
$newStatus = $current_status; // default keep
switch ($event) {
    case 'barang_dibeli':
        $newStatus = 'berjalan';
        $eventNote = 'Barang Dibeli';
        break;
    case 'barang_dikirim':
        $newStatus = 'berjalan';
        $eventNote = 'Barang Dikirim';
        break;
    case 'cicilan_aktif':
        $newStatus = 'berjalan';
        $eventNote = 'Cicilan Aktif';
        break;
    case 'lunas':
        $newStatus = 'lunas';
        $eventNote = 'Lunas';
        break;
}
if ($note && $note !== '') $eventNote .= ' — ' . $note;

$updFields = [];
$updFields[] = "status = '" . mysqli_real_escape_string($con, $newStatus) . "'";
$updQ = "UPDATE pinjaman_kredit SET " . implode(', ', $updFields) . " WHERE id = $id";
if (!mysqli_query($con, $updQ)) { echo json_encode(['status'=>false,'message'=>'DB update failed','error'=>mysqli_error($con)]); exit; }

// Insert log
$prev = mysqli_real_escape_string($con, $current_status);
$now = mysqli_real_escape_string($con, $newStatus);
$noteEsc = $eventNote ? "'" . mysqli_real_escape_string($con, $eventNote) . "'" : 'NULL';
$insLog = "INSERT INTO pinjaman_kredit_log (pinjaman_id, previous_status, new_status, changed_by, reason, note, created_at) VALUES ($id, '$prev', '$now', $id_admin, NULL, $noteEsc, NOW())";
if (!mysqli_query($con, $insLog)) { echo json_encode(['status'=>false,'message'=>'DB insert log failed','error'=>mysqli_error($con)]); exit; }

// Notify user (non-blocking)
if (file_exists(__DIR__ . '/../../flutter_api/notif_helper.php')) {
    @include_once __DIR__ . '/../../flutter_api/notif_helper.php';
    if (function_exists('safe_create_notification')) {
        $msg = $eventNote ? $eventNote : ('Status aplikasi Anda: ' . ucfirst($event));
        @safe_create_notification($con, $user_id, 'pinjaman_kredit', $msg, $msg, json_encode(['application_id' => $id, 'event' => $event]));
    }
}

echo json_encode(['status'=>true,'message'=>'Event applied','new_status'=>$newStatus]);
