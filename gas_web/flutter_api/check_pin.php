<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/database.php';
$con = getConnection();
if (!$con) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'DB error']); exit(); }

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
$id = null;
$no_hp = null;
if (is_array($body)) {
    $id = isset($body['id']) ? intval($body['id']) : null;
    $no_hp = isset($body['no_hp']) ? trim($body['no_hp']) : null;
}
// fallback to form
if (empty($id) && !empty($_POST['id'])) $id = intval($_POST['id']);
if (empty($no_hp) && !empty($_POST['no_hp'])) $no_hp = trim($_POST['no_hp']);

if (empty($id) && empty($no_hp)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'id or no_hp required']);
    exit();
}

// Prepare query
if (!empty($id)) {
    $stmt = $con->prepare('SELECT * FROM pengguna WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
} else {
    require_once __DIR__ . '/helpers.php';
    $no_hp_local = sanitizePhone($no_hp);
    $no_hp_int = phone_to_international62($no_hp);
    $stmt = $con->prepare('SELECT * FROM pengguna WHERE no_hp = ? OR no_hp = ? LIMIT 1');
    $stmt->bind_param('ss', $no_hp_local, $no_hp_int);
}
if (!$stmt->execute()) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Query failed']); exit(); }
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) { http_response_code(404); echo json_encode(['success'=>false,'message'=>'User not found']); exit(); }
$user = $res->fetch_assoc();
$stmt->close();

$needs_set_pin = empty($user['pin']);

// Check tabungan presence if table exists
$has_tabungan = true;
$check_tabungan = $con->query("SHOW TABLES LIKE 'tabungan'");
if ($check_tabungan && $check_tabungan->num_rows > 0) {
    $has_tabungan = false;
    $whereClause = '';
    if (!empty($user['id_anggota'])) $whereClause = "id_anggota='" . $con->real_escape_string($user['id_anggota']) . "'";
    else if (!empty($user['id'])) {
        $check_col = $con->query("SHOW COLUMNS FROM tabungan LIKE 'id_pengguna'");
        if ($check_col && $check_col->num_rows > 0) $whereClause = "id_pengguna='" . $con->real_escape_string($user['id']) . "'";
    }
    if (empty($whereClause) && !empty($user['id_tabungan'])) $whereClause = "id_tabungan='" . $con->real_escape_string($user['id_tabungan']) . "'";
    if (!empty($whereClause)) {
        $res_e = $con->query("SELECT 1 FROM tabungan WHERE " . $whereClause . " LIMIT 1");
        if ($res_e && $res_e->num_rows > 0) $has_tabungan = true;
    }
}

if (!$has_tabungan) $needs_set_pin = true; // per requirement: missing tabungan forces setpin
$next_page = $needs_set_pin ? 'setpin' : 'dashboard';

echo json_encode(['success'=>true, 'needs_set_pin' => $needs_set_pin, 'next_page' => $next_page]);
exit();
