<?php
// api/pinjaman_kredit/admin_list.php
// Returns list of pinjaman_kredit, optionally filtered by status; intended for admin DataTable

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

$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$where = '';
$params = [];
if ($status !== '') {
    $allowed = ['pending','approved','rejected','cancelled','berjalan','lunas'];
    if (!in_array($status, $allowed, true)) {
        echo json_encode(['status'=>false,'message'=>'Invalid status']); exit;
    }
    $where = "WHERE status = '" . mysqli_real_escape_string($con, $status) . "'";
}

$q = "SELECT id, id_pengguna, nama_barang, harga, dp, pokok, tenor, cicilan_per_bulan, total_bayar, status, created_at FROM pinjaman_kredit $where ORDER BY created_at DESC LIMIT 200";
$r = mysqli_query($con, $q);
if (!$r) {
    echo json_encode(['status'=>false,'message'=>'DB error','error'=>mysqli_error($con)]); exit;
}
$rows = [];
while ($row = mysqli_fetch_assoc($r)) {
    $rows[] = $row;
}
mysqli_free_result($r);

echo json_encode(['status'=>true,'rows'=>$rows]);
