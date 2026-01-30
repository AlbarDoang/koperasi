<?php
// Admin: update non-sensitive pengguna fields via API
// POST: id, nama_lengkap, no_hp, alamat_domisili, tanggal_lahir, status_akun
require_once 'connection.php';
header('Content-Type: application/json; charset=utf-8');

$id_raw = isset($_POST['id']) ? trim($_POST['id']) : null;
$nama = isset($_POST['nama_lengkap']) ? trim($_POST['nama_lengkap']) : null;
$no_hp = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : null;
$alamat = isset($_POST['alamat_domisili']) ? trim($_POST['alamat_domisili']) : null;
$tgl = isset($_POST['tanggal_lahir']) ? trim($_POST['tanggal_lahir']) : null;

// Important: status changes are NOT allowed via this endpoint. Approve/reject flows must be used.
if (isset($_POST['status_akun'])) {
    echo json_encode(['success' => false, 'message' => 'status_akun tidak boleh diubah melalui endpoint ini. Gunakan proses approve/reject.']);
    exit();
}

if (empty($id_raw) || !is_numeric($id_raw)) {
    echo json_encode(['success' => false, 'message' => 'id wajib dan harus numerik']);
    exit();
}
$id = intval($id_raw);

// basic validation
if (empty($nama) || empty($no_hp)) {
    echo json_encode(['success' => false, 'message' => 'Nama dan Nomor HP wajib diisi']);
    exit();
}

// Normalize phone number to local 08 format and validate
require_once __DIR__ . '/helpers.php';
$no_hp_norm = sanitizePhone($no_hp);
if (empty($no_hp_norm)) {
    echo json_encode(['success' => false, 'message' => 'Format nomor HP tidak valid']);
    exit();
}
$no_hp = $no_hp_norm;

// Check unique phone (other than this user)
$chk = $connect->prepare('SELECT id FROM pengguna WHERE no_hp = ? AND id != ? LIMIT 1');
if ($chk) {
    $chk->bind_param('si', $no_hp, $id);
    $chk->execute();
    $reschk = $chk->get_result();
    if ($reschk && $reschk->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Nomor HP sudah digunakan oleh pengguna lain']);
        $chk->close();
        exit();
    }
    $chk->close();
}

// prepare update - ONLY allowed profile fields (do NOT modify status_akun/approved_at/is_active)
$sql = "UPDATE pengguna SET nama_lengkap = ?, no_hp = ?, alamat_domisili = ?, tanggal_lahir = ?, updated_at = NOW() WHERE id = ? LIMIT 1";
$stmt = $connect->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare gagal: ' . $connect->error]);
    exit();
}
$stmt->bind_param('ssssi', $nama, $no_hp, $alamat, $tgl, $id);
$res = $stmt->execute();
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'Execute gagal: ' . $stmt->error]);
    $stmt->close();
    exit();
}
$stmt->close();

// return updated profile via get_profil to ensure consistency
$ch = curl_init();
$host = (isset($_SERVER['HTTP_HOST'])? $_SERVER['HTTP_HOST'] : 'localhost');
$url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $host . '/gas/gas_web/flutter_api/get_profil.php?id_pengguna=' . urlencode($id);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
$body = curl_exec($ch);
curl_close($ch);
$profile = null;
if ($body) {
    $json = @json_decode($body, true);
    if (is_array($json) && isset($json['status']) && $json['status'] === true) {
        $profile = $json['data'];
    }
}

echo json_encode(['success' => true, 'updated' => $profile]);
exit();
