<?php
/**
 * Update Foto API - Flutter Mobile
 * Menggunakan database terpusat dari /config/database.php
 */
require_once __DIR__ . '/api_bootstrap.php';
// Note: older scripts used $con from config/database.php; connection.php also exposes $con and $connect

// Variabel $con dan $koneksi sudah tersedia dari config
//include_once "../function/fungsi/thumb/fungsi_thumb.php";

//=========  Upload gambar untuk Foto Profil ============
function thumb_foto_profil($fupload_name,$direktori){
  // File gambar yang di upload
  $file_upload = $direktori . $fupload_name;

  // Read file contents and create image resource (supports jpeg/png/gif)
  $image_data = @file_get_contents($file_upload);
  if ($image_data === false) return false;

  $gbr_asli = @imagecreatefromstring($image_data);
  if ($gbr_asli === false) return false;

  $lebar    = imagesx($gbr_asli);
  $tinggi   = imagesy($gbr_asli);

  // Simpan dalam versi yang diinginkan 160 pixel (thumbnailnya)
  $thu_lebar  = 160;
  $thu_tinggi = intval(($thu_lebar / $lebar) * $tinggi);

  // Fungsi untuk mengubah ukuran gambar (resample)
  $gbr_thumb = imagecreatetruecolor($thu_lebar, $thu_tinggi);
  imagecopyresampled($gbr_thumb, $gbr_asli, 0, 0, 0, 0, $thu_lebar, $thu_tinggi, $lebar, $tinggi);

  // Simpan gambar yang versi thumbnailnya as JPEG (quality 85)
  imagejpeg($gbr_thumb, $direktori . "thumb." . $fupload_name, 85);

  // Hapus gambar yang ada di memori
  imagedestroy($gbr_asli);
  imagedestroy($gbr_thumb);
  return true;
}

// ============= End Foto Profil  ================

if($_SERVER['REQUEST_METHOD'] == "POST"){
    $response = array();
    $username = isset($_POST['username']) ? $_POST['username'] : '';

    $orig_name = isset($_FILES['image']['name']) ? $_FILES['image']['name'] : '';
    if (empty($orig_name)) {
        sendJsonResponse(false, 'No file uploaded');
    }

    // Ensure upload folder exists
    $upload_dir = '../assets/images/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate a safe unique filename preserving extension
    $ext = pathinfo($orig_name, PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : 'jpg';
    $safeUser = preg_replace('/[^0-9a-zA-Z]/','', $username);
    $unique = 'user_' . ($safeUser ?: 'anon') . '_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $image = $unique;

    $imagePath = $upload_dir . $image;
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
        sendJsonResponse(false, 'Move upload failed');
    }

    $folder = $upload_dir;
    // create thumbnail
    thumb_foto_profil($image, $folder);

    // Use a relative URL for storage
    $image_url = 'assets/images/' . $image;

    $saved = false;

    // First, try to update pengguna table if it exists and has a matching record
    $hasPengguna = mysqli_query($con, "SHOW TABLES LIKE 'pengguna'");
    if ($hasPengguna && mysqli_num_rows($hasPengguna) > 0) {
        // Try to match by id
        $escaped = mysqli_real_escape_string($con, $username);
        $q = "SELECT id FROM pengguna WHERE id='" . $escaped . "' LIMIT 1";
        $res = mysqli_query($con, $q);
        if ($res && mysqli_num_rows($res) == 0) {
            // try matching by phone number
            $q2 = "SELECT id FROM pengguna WHERE no_hp='" . $escaped . "' LIMIT 1";
            $res = mysqli_query($con, $q2);
        }

        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
            $peng_id = $row['id'];

            // Ensure column `foto` exists on pengguna; add if missing
            $col = mysqli_query($con, "SHOW COLUMNS FROM pengguna LIKE 'foto'");
            if (!$col || mysqli_num_rows($col) == 0) {
                @mysqli_query($con, "ALTER TABLE pengguna ADD COLUMN foto VARCHAR(255) NULL");
            }

            $update = "UPDATE pengguna SET foto='" . mysqli_real_escape_string($con, $image_url) . "' WHERE id='" . mysqli_real_escape_string($con, $peng_id) . "'";
            $ok = mysqli_query($con, $update);
            if ($ok) {
                // Build absolute URL to the saved image
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT']==443) ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']); // e.g. /gas/gas_web/flutter_api
                $baseWeb = str_replace('/flutter_api', '', $scriptDir); // /gas/gas_web
                $absUrl = $scheme . '://' . $host . $baseWeb . '/' . $image_url;

                $response['value'] = 'OK';
                $response['target'] = 'pengguna';
                $response['url'] = $absUrl;
                $saved = true;
            }
        }
    }

    // If not saved to pengguna, fallback to siswa (legacy)
    if (!$saved) {
        $data = "SELECT * FROM pengguna WHERE id_tabungan='" . mysqli_real_escape_string($con, $username) . "'";
        $sqldata = mysqli_query($con, $data);
        $fetchdata = mysqli_fetch_array($sqldata);
        if ($fetchdata) {
            $query = "UPDATE pengguna SET foto='" . mysqli_real_escape_string($con, $image) . "' WHERE id_tabungan='" . mysqli_real_escape_string($con, $fetchdata['id_tabungan']) . "'";
            $save = mysqli_query($con, $query);
            if ($save) {
                // Build absolute URL for siswa fallback as well
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT']==443) ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
                $baseWeb = str_replace('/flutter_api', '', $scriptDir);
                $absUrl = $scheme . '://' . $host . $baseWeb . '/' . $image_url;

                $response['value'] = 'OK';
                $response['target'] = 'siswa';
                $response['url'] = $absUrl;
                $saved = true;
            }
        }
    }

    if ($saved) {
        sendJsonResponse(true, 'OK', array('data' => $response));
    } else {
        sendJsonResponse(false, 'No matching user found to update');
    }

}
