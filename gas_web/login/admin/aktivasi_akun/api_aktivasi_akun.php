<?php
header('Content-Type: application/json');
require_once __DIR__.'/config/database.php';

$data = json_decode(file_get_contents('php://input'), true);
$no_hp = $data['no_hp'] ?? '';
$kode_otp = $data['kode_otp'] ?? '';

$sql = "SELECT * FROM otp_codes WHERE no_wa=? AND kode_otp=? AND status='belum' LIMIT 1";
$stmt = $con->prepare($sql);
$stmt->bind_param('ss', $no_hp, $kode_otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Kode OTP salah"]);
    exit;
}

// Update status
$update = $con->prepare("UPDATE otp_codes SET status='sudah' WHERE no_wa=? AND kode_otp=?");
$update->bind_param('ss', $no_hp, $kode_otp);
$update->execute();

// Update akun user
$updateUser = $con->prepare("UPDATE pengguna SET status_akun='approved' WHERE no_wa=? OR no_hp=? LIMIT 1");
$updateUser->bind_param('ss', $no_hp, $no_hp);
$updateUser->execute();

echo json_encode(["success" => true, "message" => "Aktivasi akun berhasil"]);