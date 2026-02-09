<?php 
/**
 * API: Upload Foto (KTP atau Selfie)
 * Support base64 image dari mobile app
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/api_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    if (empty($_POST['image_data']) || empty($_POST['image_type'])) {
        sendJsonResponse(false, 'image_data dan image_type wajib diisi');
    }
    
    $image_data = $_POST['image_data']; // Base64 string
    $image_type = $_POST['image_type']; // 'ktp' atau 'selfie'
    $id_pengguna = isset($_POST['id_pengguna']) ? $_POST['id_pengguna'] : '';
    
    // Validasi image_type
    if (!in_array($image_type, ['ktp', 'selfie'])) {
        sendJsonResponse(false, "image_type harus 'ktp' atau 'selfie'");
    }
    
    try {
        // Remove header dari base64 (jika ada)
        if (strpos($image_data, 'base64,') !== false) {
            $image_data = explode('base64,', $image_data)[1];
        }
        
        // Decode base64
        $image_decoded = base64_decode($image_data);
        
        if ($image_decoded === false) {
            throw new Exception("Gagal decode base64");
        }
        
        // Use central storage config for KYC
        require_once __DIR__ . '/storage_config.php';

        // Decode and validate size
        if (strlen($image_decoded) > KYC_MAX_FILE_SIZE) {
            throw new Exception('Gambar terlalu besar (max ' . intval(KYC_MAX_FILE_SIZE / (1024*1024)) . 'MB)');
        }

        // Determine mime (default to jpeg)
        $detectedMime = 'image/jpeg';
        if (preg_match('/^data:(image\/[^;]+);base64,/', $_POST['image_data'], $m)) {
            $detectedMime = $m[1];
        }
        $allowedMimes = isset($KYC_ALLOWED_MIMES) ? $KYC_ALLOWED_MIMES : ['image/jpeg','image/png'];
        $allowedText = 'JPG atau PNG';
        if (in_array('image/heic', $allowedMimes)) $allowedText .= ' (atau HEIC)';
        if (!in_array($detectedMime, $allowedMimes)) {
            throw new Exception('Format gambar tidak didukung (hanya ' . $allowedText . ')');
        }

        // Generate filename via helper (ensures extension mapping)
        $filename = kyc_generate_filename($image_type . '_' . (int)time(), $detectedMime);

        // Determine destination folder
        $upload_dir = ($image_type === 'ktp') ? KYC_STORAGE_KTP : KYC_STORAGE_SELFIE;
        if (!kyc_ensure_dir($upload_dir)) throw new Exception('Gagal menyiapkan folder penyimpanan');

        $file_path = $upload_dir . $filename;

        // Simpan file
        if (file_put_contents($file_path, $image_decoded) === false) {
            throw new Exception("Gagal menyimpan file");
        }

        // For compatibility, return filename (no public URL)
        $image_url = $filename;

        // Update database jika ada id_pengguna (disebut id_pengguna di form lama)
        if (!empty($id_pengguna)) {
            $column = ($image_type == 'ktp') ? 'foto_ktp' : 'foto_selfie';
            // store full path in DB
            $fullPath = $file_path;
            $sql_update = "UPDATE verifikasi_pengguna SET $column = '" . $connect->real_escape_string($fullPath) . "', updated_at = NOW() WHERE id_pengguna = '" . $connect->real_escape_string($id_pengguna) . "'";
            $connect->query($sql_update);
        }

        sendJsonResponse(true, 'Upload berhasil', array('data' => array('filename' => $filename, 'url' => $image_url, 'type' => $image_type)));
        
    } catch (Exception $e) {
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [upload_foto] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        sendJsonResponse(false, 'Gagal upload: ' . $e->getMessage());
    }
    
} else {
    sendJsonResponse(false, 'Method not allowed. Use POST');
}

