<?php
// api/pinjaman_kredit/status.php
// Returns a user's pinjaman_kredit applications and their status history

declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

function emit($code, $payload) { http_response_code($code); echo json_encode($payload); exit; }

$id_pengguna = isset($_GET['id_pengguna']) ? (int)$_GET['id_pengguna'] : 0;
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_pengguna <= 0) emit(400, ['status'=>false,'message'=>'id_pengguna tidak valid']);

// Fetch applications
$where = "WHERE id_pengguna = $id_pengguna";
if ($application_id > 0) $where .= " AND id = $application_id";

// Dynamically detect which columns exist to avoid column not found errors
$colsRes = @mysqli_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pinjaman_kredit' ORDER BY ORDINAL_POSITION");
$availableCols = [];
if ($colsRes) {
    while ($cr = mysqli_fetch_assoc($colsRes)) {
        $availableCols[] = $cr['COLUMN_NAME'];
    }
}

// Build select list with only columns that exist
$baseSelect = ['id', 'id_pengguna', 'nama_barang', 'harga', 'dp', 'pokok', 'tenor', 'cicilan_per_bulan', 'total_bayar', 'status', 'created_at'];
$optionalSelect = ['foto_barang', 'link_bukti_harga', 'catatan_admin', 'approved_at', 'approved_by', 'updated_at'];
$selectCols = $baseSelect;
foreach ($optionalSelect as $ocol) {
    if (in_array($ocol, $availableCols, true)) {
        $selectCols[] = $ocol;
    }
}

$q = "SELECT " . implode(', ', $selectCols) . " FROM pinjaman_kredit $where ORDER BY created_at DESC";
$r = mysqli_query($con, $q);
if (!$r) emit(500, ['status'=>false,'message'=>'DB error','error'=>mysqli_error($con)]);
$rows = [];
while ($row = mysqli_fetch_assoc($r)) {
    $id = (int)$row['id'];
    // Fetch status history
    $logs = [];
    $lr = mysqli_query($con, "SELECT previous_status, new_status, changed_by, reason, note, created_at FROM pinjaman_kredit_log WHERE pinjaman_id = $id ORDER BY created_at ASC");
    if ($lr) {
        while ($l = mysqli_fetch_assoc($lr)) $logs[] = $l;
        mysqli_free_result($lr);
    }
    $row['history'] = $logs;
    $rows[] = $row;
}
mysqli_free_result($r);

emit(200, ['status'=>true,'applications'=>$rows]);
