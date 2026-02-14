<?php
// Koneksi ke database
$host = "localhost";
$user = "root";
$pass = ""; // ganti sesuai password MySQL kamu
$dbname = "tabungan"; // ganti sesuai nama database kamu

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    exit('Database connection failed');
}

// Ambil ID user dari parameter GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    http_response_code(400);
    exit('Missing id');
}

// Query nama file dari database
$sql = "SELECT foto_profil FROM pengguna WHERE id = $id LIMIT 1";
$res = $conn->query($sql);
if (!$res || $res->num_rows == 0) {
    http_response_code(404);
    exit('User or file not found');
}
$row = $res->fetch_assoc();
$nama_file = $row['foto_profil'];
if (!$nama_file) {
    http_response_code(404);
    exit('No profile photo');
}

// Path file
$folder = "C:/laragon/www/gas/gas_storage/foto_profil/$id/";
$filepath = $folder . $nama_file;

if (!file_exists($filepath)) {
    http_response_code(404);
    exit('File not found');
}

// Deteksi tipe file (jpeg/png)
$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
if ($ext == 'jpg' || $ext == 'jpeg') {
    header('Content-Type: image/jpeg');
} elseif ($ext == 'png') {
    header('Content-Type: image/png');
} else {
    http_response_code(415);
    exit('Unsupported file type');
}

header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
?>