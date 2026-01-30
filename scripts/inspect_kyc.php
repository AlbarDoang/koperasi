<?php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'tabungan';
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) { echo "DB connect error: " . $mysqli->connect_error . "\n"; exit(1); }
$mysqli->set_charset('utf8mb4');
$res = $mysqli->query("SELECT id_pengguna, foto_ktp, foto_selfie, created_at FROM verifikasi_pengguna ORDER BY created_at DESC LIMIT 20");
if (!$res) { echo "Query failed: " . $mysqli->error . "\n"; exit(1); }
while ($r = $res->fetch_assoc()) {
    $id = $r['id_pengguna'];
    $ktp = $r['foto_ktp'];
    $selfie = $r['foto_selfie'];
    echo "user_id={$id}\n";
    echo "  foto_ktp DB: " . var_export($ktp, true) . "\n";
    echo "    realpath: " . var_export(realpath($ktp), true) . "\n";
    echo "    exists: " . (file_exists($ktp) ? 'yes' : 'no') . "\n";
    echo "  foto_selfie DB: " . var_export($selfie, true) . "\n";
    echo "    realpath: " . var_export(realpath($selfie), true) . "\n";
    echo "    exists: " . (file_exists($selfie) ? 'yes' : 'no') . "\n";
    // Try candidate basenames
    $bn_ktp = basename($ktp);
    $bn_selfie = basename($selfie);
    echo "  basename ktp: " . $bn_ktp . "\n";
    echo "  basename selfie: " . $bn_selfie . "\n";

    // Load storage_config.php to check KYC constants
    $sc = __DIR__ . '/../gas_web/flutter_api/storage_config.php';
    if (file_exists($sc)) {
        require_once $sc;
        echo "  KYC_STORAGE_BASE: " . (defined('KYC_STORAGE_BASE') ? KYC_STORAGE_BASE : '(not defined)') . "\n";
        echo "  KYC_STORAGE_KTP: " . (defined('KYC_STORAGE_KTP') ? KYC_STORAGE_KTP : '(not defined)') . "\n";
        echo "  KYC_STORAGE_SELFIE: " . (defined('KYC_STORAGE_SELFIE') ? KYC_STORAGE_SELFIE : '(not defined)') . "\n";

        $candidates = [
            (defined('KYC_STORAGE_KTP') ? KYC_STORAGE_KTP . $bn_ktp : null),
            (defined('KYC_STORAGE_KTP') ? KYC_STORAGE_KTP . $id . DIRECTORY_SEPARATOR . $bn_ktp : null),
            (defined('KYC_STORAGE_SELFIE') ? KYC_STORAGE_SELFIE . $bn_selfie : null),
            (defined('KYC_STORAGE_SELFIE') ? KYC_STORAGE_SELFIE . $id . DIRECTORY_SEPARATOR . $bn_selfie : null),
            __DIR__ . '/../gas_web/flutter_api/foto_verifikasi/' . $bn_ktp,
            __DIR__ . '/../gas_web/flutter_api/foto_verifikasi/' . $bn_selfie,
        ];
        foreach ($candidates as $cand) {
            if (!$cand) continue;
            echo "    candidate: " . $cand . "\n";
            echo "      realpath: " . var_export(realpath($cand), true) . "\n";
            echo "      exists: " . (file_exists($cand) ? 'yes' : 'no') . "\n";
        }
    } else {
        echo "  storage_config.php not found at expected location: $sc\n";
    }

    echo "\n";
}
$mysqli->close();
