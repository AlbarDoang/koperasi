<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/connection.php';

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$q = '';
if (is_array($body) && !empty($body['phone'])) {
    $q = trim($body['phone']);
} elseif (!empty($_POST['phone'])) {
    $q = trim($_POST['phone']);
} elseif (!empty($_GET['phone'])) {
    $q = trim($_GET['phone']);
} elseif (!empty($_POST['query'])) {
    $q = trim($_POST['query']);
}

if ($q === '') {
    echo json_encode(['success' => false, 'message' => 'Query kosong']);
    exit();
}

// Normalize phone to digits only
$clean = preg_replace('/[^0-9]/', '', $q);
$variants = array_unique(array_filter([
    $q,
    $clean,
    (substr($clean,0,1) === '0') ? '62' . substr($clean,1) : null,
    (substr($clean,0,2) === '62') ? substr($clean,1) : null,
]));

// helper to check column existence
$column_exists = function($col) use ($connect) {
    $r = $connect->query("SHOW COLUMNS FROM pengguna LIKE '" . $connect->real_escape_string($col) . "'");
    return ($r && $r->num_rows > 0);
};

// select fragments
if ($column_exists('nama')) {
    $name_select = 'nama as nama';
} else if ($column_exists('nama_lengkap')) {
    $name_select = 'nama_lengkap as nama';
} else {
    $name_select = "'' as nama";
}

if ($column_exists('status')) {
    $status_select = 'status as status_akun';
} else if ($column_exists('status_akun')) {
    $status_select = 'status_akun as status_akun';
} else {
    $status_select = "'' as status_akun";
}

$a = $connect->real_escape_string($variants[0] ?? $q);
$b = $connect->real_escape_string($variants[1] ?? $q);
$c = $connect->real_escape_string($q);
$d = $connect->real_escape_string($variants[3] ?? $q);

// Build WHERE clause only with columns that exist
$parts = ["no_hp = '$a'", "no_hp = '$b'", "id = '$c'"];
// check for 'nis' existence
$r = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'nis'");
if ($r && $r->num_rows > 0) $parts[] = "nis = '$d'";
$where = '(' . implode(' OR ', $parts) . ')';
$status_checks = [];
if ($column_exists('status')) $status_checks[] = "status='aktif'";
if ($column_exists('status_akun')) $status_checks[] = "LOWER(status_akun) = 'approved'";
$status_sql = count($status_checks) ? ' (' . implode(' OR ', $status_checks) . ') AND ' : '';

$sql = "SELECT id, no_hp, {$name_select}, saldo, {$status_select} FROM pengguna WHERE {$status_sql} $where LIMIT 1";
$res = $connect->query($sql);
if ($res === false) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode(['success' => true, 'user' => [
        'id' => $row['id'],
        'id_pengguna' => $row['id'],
        'nis' => $row['nis'] ?? '',
        'nama' => $row['nama'],
        'no_hp' => $row['no_hp'],
        'saldo' => floatval($row['saldo'] ?? 0),
        'status_akun' => $row['status_akun'] ?? '',
    ]]);
} else {
    echo json_encode(['success' => false, 'message' => 'Pengguna tidak ditemukan']);
}

exit();

