<?php
// ============================================================================
// FILE: delete_verifikasi_pengguna.php
// FUNGSI: Menghapus data verifikasi pengguna beserta file foto KTP dan selfie
//         dari folder foto_verifikasi
// ============================================================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

// Tangani preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================================
// 1. VALIDASI REQUEST METHOD
// ============================================================================
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Hanya POST/D+ELETE method yang diperbolehkan."
    ]);
    exit();
}

// ============================================================================
// 2. KONEKSI DATABASE KE "tabungan"
// ============================================================================
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "tabungan";

$koneksi = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($koneksi->connect_error) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Koneksi database gagal: " . $koneksi->connect_error
    ]);
    exit();
}

$koneksi->set_charset("utf8mb4");

// ============================================================================
// 3. VALIDASI ID VERIFIKASI ATAU ID PENGGUNA
// ============================================================================
$id_verifikasi = null;
$id_pengguna = null;

// Coba dari POST/JSON data
$input = json_decode(file_get_contents('php://input'), true);

if (!empty($input['id_verifikasi'])) {
    $id_verifikasi = intval($input['id_verifikasi']);
} elseif (!empty($input['id_pengguna'])) {
    $id_pengguna = intval($input['id_pengguna']);
} elseif (!empty($_POST['id_verifikasi'])) {
    $id_verifikasi = intval($_POST['id_verifikasi']);
} elseif (!empty($_POST['id_pengguna'])) {
    $id_pengguna = intval($_POST['id_pengguna']);
}

if (empty($id_verifikasi) && empty($id_pengguna)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "ID verifikasi atau ID pengguna tidak ditemukan."
    ]);
    exit();
}

// ============================================================================
// 4. AMBIL DATA VERIFIKASI (UNTUK MENDAPATKAN NAMA FILE)
// ============================================================================
$query = $koneksi->prepare("
    SELECT id, id_pengguna, foto_ktp, foto_selfie 
    FROM verifikasi_pengguna 
    WHERE " . ($id_verifikasi ? "id = ?" : "id_pengguna = ?")
);

if (!$query) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Prepare statement gagal: " . $koneksi->error
    ]);
    exit();
}

$param_id = $id_verifikasi ?? $id_pengguna;
$query->bind_param("i", $param_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Data verifikasi tidak ditemukan."
    ]);
    exit();
}

$verifikasi_data = $result->fetch_assoc();
$foto_ktp = $verifikasi_data['foto_ktp'];
$foto_selfie = $verifikasi_data['foto_selfie'];
$id_verifikasi_actual = $verifikasi_data['id'];
$id_pengguna_actual = $verifikasi_data['id_pengguna'];

$query->close();

// ============================================================================
// 5. HAPUS FILE FOTO DARI STORAGE (support absolute paths and legacy filenames)
// ============================================================================
require_once __DIR__ . '/storage_config.php';
$files_deleted = [];
$files_failed = [];

$maybe_paths = [];
// If stored value looks like absolute path, try it directly
if (!empty($foto_ktp)) {
    if (strpos($foto_ktp, DIRECTORY_SEPARATOR) !== false || preg_match('/^[A-Za-z]:\\\\/', $foto_ktp)) {
        $maybe_paths[] = $foto_ktp;
    } else {
        // legacy filename - check old public folder and new storage (including per-user subdirs)
        $maybe_paths[] = __DIR__ . '/foto_verifikasi/' . basename($foto_ktp);
        $maybe_paths[] = KYC_STORAGE_KTP . basename($foto_ktp);
        $maybe_paths[] = KYC_STORAGE_KTP . $id_pengguna_actual . DIRECTORY_SEPARATOR . basename($foto_ktp);
        $maybe_paths[] = KYC_STORAGE_SELFIE . basename($foto_ktp);
        $maybe_paths[] = KYC_STORAGE_SELFIE . $id_pengguna_actual . DIRECTORY_SEPARATOR . basename($foto_ktp);
    }
}
// Selfie
if (!empty($foto_selfie)) {
    if (strpos($foto_selfie, DIRECTORY_SEPARATOR) !== false || preg_match('/^[A-Za-z]:\\\\/', $foto_selfie)) {
        $maybe_paths[] = $foto_selfie;
    } else {
        $maybe_paths[] = __DIR__ . '/foto_verifikasi/' . basename($foto_selfie);
        $maybe_paths[] = KYC_STORAGE_SELFIE . basename($foto_selfie);
        $maybe_paths[] = KYC_STORAGE_SELFIE . $id_pengguna_actual . DIRECTORY_SEPARATOR . basename($foto_selfie);
        $maybe_paths[] = KYC_STORAGE_KTP . basename($foto_selfie);
        $maybe_paths[] = KYC_STORAGE_KTP . $id_pengguna_actual . DIRECTORY_SEPARATOR . basename($foto_selfie);
    }
}

// Unique and attempt deletes
$maybe_paths = array_unique($maybe_paths);
foreach ($maybe_paths as $p) {
    if (!$p) continue;
    $rp = realpath($p);
    if (!$rp) continue;
    // Safety: only delete if file inside allowed KYC storage or legacy public dir
    $allowedLegacy = realpath(__DIR__ . '/foto_verifikasi');
    if (kyc_path_is_inside_base($rp) || ($allowedLegacy && strpos($rp, $allowedLegacy) === 0)) {
        if (file_exists($rp)) {
            if (@unlink($rp)) $files_deleted[] = basename($rp); else $files_failed[] = basename($rp);
        }
    } else {
        $files_failed[] = basename($p); // not allowed to delete outside storage (return only filename to client)
    }
}

// ============================================================================
// 6. HAPUS RECORD DARI DATABASE
// ============================================================================
$delete_query = $koneksi->prepare("
    DELETE FROM verifikasi_pengguna 
    WHERE id = ?
");

if (!$delete_query) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Prepare statement gagal: " . $koneksi->error,
        "files_deleted" => $files_deleted,
        "files_failed" => $files_failed
    ]);
    exit();
}

$delete_query->bind_param("i", $id_verifikasi_actual);

if (!$delete_query->execute()) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal menghapus dari database: " . $delete_query->error,
        "files_deleted" => $files_deleted,
        "files_failed" => $files_failed
    ]);
    $delete_query->close();
    exit();
}

$delete_query->close();

// ============================================================================
// 7. RETURN SUCCESS RESPONSE
// ============================================================================
http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Verifikasi pengguna berhasil dihapus beserta file fotonya.",
    "data" => [
        "id_verifikasi" => $id_verifikasi_actual,
        "id_pengguna" => $id_pengguna_actual,
        "files_deleted" => $files_deleted,
        "files_failed" => $files_failed,
        "deleted_at" => date('Y-m-d H:i:s')
    ]
]);

$koneksi->close();
