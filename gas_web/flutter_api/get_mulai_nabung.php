<?php
// Suppress PHP warnings/notices that would break JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

// API: get_mulai_nabung.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(200); exit(); }

require_once dirname(__DIR__) . '/config/database.php';
$connect = getConnection();
if (!$connect) {
	http_response_code(500);
	echo json_encode(['success' => false, 'message' => 'DB connection failed']);
	exit();
}

$id_raw = isset($_POST['id_mulai_nabung']) ? trim($_POST['id_mulai_nabung']) : (isset($_GET['id_mulai_nabung']) ? trim($_GET['id_mulai_nabung']) : '');
if (empty($id_raw)) {
	echo json_encode(['success' => false, 'message' => 'Parameter id_mulai_nabung required']);
	exit();
}
$id = intval($id_raw);

$stmt = $connect->prepare("SELECT id_mulai_nabung, id_tabungan, jumlah, status, created_at, updated_at FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1");
if (!$stmt) {
	echo json_encode(['success' => false, 'message' => 'Prepare failed']);
	exit();
}
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
	echo json_encode(['success' => false, 'message' => 'Execute failed']);
	exit();
}
$res = $stmt->get_result();
if ($res->num_rows == 0) {
	echo json_encode(['success' => false, 'message' => 'Not found']);
	exit();
}
$row = $res->fetch_assoc();
echo json_encode(['success' => true, 'data' => $row]);
exit();

