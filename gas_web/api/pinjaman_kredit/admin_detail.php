<?php
// api/pinjaman_kredit/admin_detail.php
// Fetch detail of a pinjaman_kredit for admin view (includes user info and history)

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status'=>false,'message'=>'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status'=>false,'message'=>'Invalid id']); exit;
}

$q = "SELECT pk.*, p.nama_lengkap, p.no_hp, p.nik, p.foto_profil FROM pinjaman_kredit pk LEFT JOIN pengguna p ON p.id = pk.id_pengguna WHERE pk.id = $id LIMIT 1";
$r = mysqli_query($con, $q);
if (!$r) { echo json_encode(['status'=>false,'message'=>'DB error','error'=>mysqli_error($con)]); exit; }
$row = mysqli_fetch_assoc($r);
mysqli_free_result($r);
if (!$row) { echo json_encode(['status'=>false,'message'=>'Not found']); exit; }

// fetch logs
$logs = [];
$lr = mysqli_query($con, "SELECT previous_status, new_status, changed_by, reason, note, created_at FROM pinjaman_kredit_log WHERE pinjaman_id = $id ORDER BY created_at ASC");
if ($lr) {
    while ($l = mysqli_fetch_assoc($lr)) $logs[] = $l;
    mysqli_free_result($lr);
}

echo json_encode(['status'=>true,'row'=>$row,'history'=>$logs]);
