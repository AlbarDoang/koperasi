<?php
/**
 * ============================================================================
 * API: Update Foto Profil Pengguna
 * ============================================================================
 * 
 * File: flutter_api/update_foto_profil.php
 * Tujuan: Menerima upload foto profil dari aplikasi Flutter Mobile
 * Method: POST
 * Content-Type: multipart/form-data
 * 
 * Parameters:
 *   - id_pengguna: ID pengguna di tabel pengguna (required)
 *   - foto_profil: File gambar (required, max 10MB, format: JPG/JPEG/PNG)
 * 
 * Response Success (200):
 * {
 *   "status": true,
 *   "message": "Foto profil berhasil diperbarui",
 *   "foto_profil": "https://domain.com/uploads/foto_profil/123_1702000000.jpg"
 * }
 * 
 * Response Error (400/422):
 * {
 *   "status": false,
 *   "message": "Deskripsi error"
 * }
 * 
 * ============================================================================
 */

// ============================================================================
// 1. KONFIGURASI DAN SETUP AWAL
// ============================================================================

// Sertakan konfigurasi database terpusat
require_once dirname(__DIR__) . '/config/database.php';
// Sertakan konfigurasi storage (PROFILE_STORAGE constants)
require_once __DIR__ . '/storage_config.php';

// Pastikan PHP mengizinkan upload file hingga 10MB
@ini_set('upload_max_filesize', '12M');
@ini_set('post_max_size', '14M');
@ini_set('max_execution_time', '120');

// Set header untuk JSON response
header('Content-Type: application/json; charset=utf-8');

// Validasi method request harus POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    if (ob_get_length()) @ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'Method tidak diizinkan. Gunakan POST.'
    ]);
    exit();
}

// ============================================================================
// 2. VALIDASI INPUT PARAMETER
// ============================================================================

// Ambil ID pengguna dari POST
$id_pengguna = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : '';

// Validasi ID pengguna tidak boleh kosong
if (empty($id_pengguna)) {
    http_response_code(422);
    if (ob_get_length()) @ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'ID pengguna tidak ditemukan'
    ]);
    exit();
}

// Validasi file upload ada
if (!isset($_FILES['foto_profil']) || empty($_FILES['foto_profil']['name'])) {
    http_response_code(422);
    if (ob_get_length()) @ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'File foto tidak ditemukan'
    ]);
    exit();
}

// ============================================================================
// 3. VALIDASI FILE UPLOAD
// ============================================================================

// Ambil informasi file
$file_tmp = $_FILES['foto_profil']['tmp_name'];
$file_name = $_FILES['foto_profil']['name'];
$file_size = $_FILES['foto_profil']['size'];
$file_error = $_FILES['foto_profil']['error'];

// Validasi error upload
if ($file_error !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'message' => 'Gagal upload file. Error code: ' . $file_error
    ]);
    exit();
}

// Validasi ukuran file maksimal 10MB (10485760 bytes)
$max_size = 10 * 1024 * 1024; // 10MB
if ($file_size > $max_size) {
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'message' => 'Ukuran file terlalu besar. Maksimal 10MB'
    ]);
    exit();
}

// Ambil extension file
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Validasi tipe file hanya JPG, JPEG, PNG
$allowed_extensions = ['jpg', 'jpeg', 'png'];
if (!in_array($file_ext, $allowed_extensions)) {
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'message' => 'Format file tidak didukung. Hanya JPG, JPEG, PNG'
    ]);
    exit();
}

// Validasi MIME type menggunakan magic bytes untuk keamanan lebih
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
if ($finfo) {
    $mime_type = @finfo_file($finfo, $file_tmp);
    @finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/pjpeg', 'image/jpg', 'image/png', 'image/x-png'];
    if (!in_array($mime_type, $allowed_mimes)) {
        @file_put_contents(dirname(__DIR__) . '/flutter_api/api_debug.log', date('c') . " [update_foto_profil] Invalid MIME: file_name={$file_name} tmp={$file_tmp} detected={$mime_type} size={$file_size} error={$file_error}\n", FILE_APPEND);
        http_response_code(422);
        echo json_encode([
            'status' => false,
            'message' => 'Tipe MIME file tidak valid. Hanya JPEG dan PNG'
        ]);
        exit();
    }
}

// Validasi bisa dibaca sebagai image
$image_info = @getimagesize($file_tmp);
if ($image_info === false) {
    $head = '';
    if (is_readable($file_tmp)) {
        $fh = @fopen($file_tmp, 'rb');
        if ($fh) {
            $head = bin2hex(@fread($fh, 16));
            @fclose($fh);
        }
    }
    @file_put_contents(dirname(__DIR__) . '/flutter_api/api_debug.log', date('c') . " [update_foto_profil] getimagesize failed: file_name={$file_name} tmp={$file_tmp} size={$file_size} error={$file_error} head={$head}\n", FILE_APPEND);
    http_response_code(422);
    if (ob_get_length()) @ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'File bukan gambar yang valid'
    ]);
    exit();
}

// ============================================================================
// 4. PERSIAPAN FOLDER UPLOAD (PROFIL EKSTERNAL)
// ============================================================================

// Use profile storage defined in storage_config.php (outside docroot)
// We store files per-user inside PROFILE_STORAGE_PHOTO/<user_id>/
$upload_dir = PROFILE_STORAGE_PHOTO . $id_pengguna . DIRECTORY_SEPARATOR;
if (!profile_ensure_dir(PROFILE_STORAGE_BASE)) {
    http_response_code(500);    if (ob_get_length()) @ob_end_clean();    echo json_encode([
        'status' => false,
        'message' => 'Folder profile storage tidak dapat dibuat atau tidak writable (base)'
    ]);
    exit();
}
// Ensure per-user dir exists and is writable
if (!profile_ensure_dir($upload_dir)) {
    http_response_code(500);
    if (ob_get_length()) @ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'Folder profile storage pengguna tidak dapat dibuat atau tidak writable'
    ]);
    exit();
}
if (!is_writable($upload_dir)) {
    http_response_code(500);
    if (ob_get_length()) @ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'Folder profile storage tidak dapat ditulis (user folder)'
    ]);
    exit();
}

// ============================================================================
// 5. GENERATE NAMA FILE UNIK DAN AMAN (SECURE RANDOM)
// ============================================================================
// Determine actual MIME type via finfo
$finfo = @finfo_open(FILEINFO_MIME_TYPE);
$mime_type = $finfo ? @finfo_file($finfo, $file_tmp) : mime_content_type($file_tmp);
if ($finfo) @finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/pjpeg', 'image/jpg', 'image/png', 'image/x-png'];
if (!in_array($mime_type, $allowed_mimes)) {
    @file_put_contents(dirname(__DIR__) . '/flutter_api/api_debug.log', date('c') . " [update_foto_profil] invalid_mime_after_finfo: file_name={$file_name} tmp={$file_tmp} detected={$mime_type} size={$file_size} error={$file_error}\n", FILE_APPEND);
    http_response_code(422);    if (ob_get_length()) @ob_end_clean();    echo json_encode([
        'status' => false,
        'message' => 'Tipe file tidak didukung. Hanya JPEG dan PNG'
    ]);
    exit();
}

// Enforce size limit (10MB)
$max_size = PROFILE_MAX_FILE_SIZE; // 10MB
if ($file_size > $max_size) {
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'message' => 'Ukuran file terlalu besar. Maksimal 10MB'
    ]);
    exit();
}

// Generate secure filename
$new_filename = profile_generate_filename($id_pengguna, $mime_type);
$new_file_path = $upload_dir . $new_filename;

// Ensure uniqueness (very unlikely collision)
$tries = 0;
while (file_exists($new_file_path) && $tries < 5) {
    $new_filename = profile_generate_filename($id_pengguna, $mime_type);
    $new_file_path = $upload_dir . $new_filename;
    $tries++;
}

// ============================================================================
// 6. PINDAHKAN FILE KE FOLDER TUJUAN
// ============================================================================
if (!move_uploaded_file($file_tmp, $new_file_path)) {
    http_response_code(500);
    if (ob_get_length()) @ob_end_clean();
    echo json_encode([
        'status' => false,
        'message' => 'Gagal menyimpan file ke server'
    ]);
    exit();
}
@chmod($new_file_path, 0644);

// ============================================================================
// 7. UPDATE DATABASE - SIMPAN NAMA FILE (TIDAK MENYIMPAN URL)
// ============================================================================
$conn = getConnection();
if (!$conn) {
    @unlink($new_file_path);
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Koneksi database gagal'
    ]);
    exit();
}

// Get previous filename to optionally clean up old file
$stmt_old = $conn->prepare("SELECT foto_profil FROM pengguna WHERE id = ? LIMIT 1");
$stmt_old->bind_param('s', $id_pengguna);
$stmt_old->execute();
$res_old = $stmt_old->get_result();
$old_row = $res_old ? $res_old->fetch_assoc() : null;
$old_filename = $old_row['foto_profil'] ?? null;
$stmt_old->close();

// Use prepared statement to update safely
$now = time();
$intId = intval($id_pengguna);
try {
    // Check whether the foto_profil_updated_at column exists to stay backward-compatible
    $hasColRes = $conn->query("SHOW COLUMNS FROM pengguna LIKE 'foto_profil_updated_at'");
    $useUpdatedAt = ($hasColRes && $hasColRes->num_rows > 0);

    if ($useUpdatedAt) {
        $stmt = $conn->prepare("UPDATE pengguna SET foto_profil = ?, foto_profil_updated_at = ? WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param('sii', $new_filename, $now, $intId);
    } else {
        // fallback for older schema
        $stmt = $conn->prepare("UPDATE pengguna SET foto_profil = ? WHERE id = ? LIMIT 1");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param('si', $new_filename, $intId);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new Exception('Execute failed: ' . $err);
    }
    $stmt->close();
} catch (Throwable $e) {
    @unlink($new_file_path);
    http_response_code(500);
    if (ob_get_length()) @ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => false,
        'message' => 'Gagal update database: ' . $e->getMessage()
    ]);
    exit();
}

// Optionally remove previous file if it exists in profile storage (best-effort)
if (!empty($old_filename)) {
    // Try per-user folder first
    $old_path_prof1 = PROFILE_STORAGE_PHOTO . $id_pengguna . DIRECTORY_SEPARATOR . $old_filename;
    $old_path_prof2 = PROFILE_STORAGE_PHOTO . $old_filename; // legacy location
    if (file_exists($old_path_prof1) && is_file($old_path_prof1) && realpath($old_path_prof1) !== realpath($new_file_path)) {
        @unlink($old_path_prof1);
    } elseif (file_exists($old_path_prof2) && is_file($old_path_prof2) && realpath($old_path_prof2) !== realpath($new_file_path)) {
        @unlink($old_path_prof2);
    }
}

// ============================================================================
// 8. PREPARE SIGNED PROXY URL FOR CLIENT (DO NOT EXPOSE FILE PATH)
// ============================================================================
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$exp = time() + 86400; // 24 hours
$payload = $id_pengguna . ':' . $new_filename . ':' . $exp;
$sig = hash_hmac('sha256', $payload, PROFILE_IMAGE_SECRET);
$proxy_url = $protocol . $host . '/gas/gas_web/login/user/foto_profil_image.php?id=' . urlencode($id_pengguna) . '&exp=' . $exp . '&sig=' . $sig;

// Return required response shape (filename + updated timestamp) and keep old fields for compatibility
if (ob_get_length()) @ob_end_clean();
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => true,
    'success' => true,
    'message' => 'Foto profil berhasil diperbarui',
    'foto_profil' => $new_filename,
    'foto_profil_updated_at' => $now,
    'foto_profil_url' => $proxy_url,
    'foto_profil_key' => $new_filename
]);
exit();

