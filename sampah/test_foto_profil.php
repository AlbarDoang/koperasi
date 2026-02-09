<?php
// Simple test script to exercise update_foto_profil.php and foto_profil_image proxy
// Usage: php scripts/test_foto_profil.php <id_pengguna> <path_to_image>
if ($argc < 3) {
    echo "Usage: php scripts/test_foto_profil.php <id_pengguna> <image_path>\n";
    exit(1);
}
$id = $argv[1];
$image = $argv[2];
// If the provided image path does not exist, generate a tiny 1x1 PNG automatically
if (!file_exists($image)) {
    $tmpDir = sys_get_temp_dir();
    $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'test_1x1_' . bin2hex(random_bytes(6)) . '.png';
    $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAn8B9d3H2eYAAAAASUVORK5CYII='; // 1x1 transparent PNG
    file_put_contents($tmpFile, base64_decode($pngBase64));
    if (file_exists($tmpFile)) {
        echo "Generated temporary image: $tmpFile\n";
        $image = $tmpFile;
    } else {
        echo "File not found: $image\n";
        exit(2);
    }
}
$ch = curl_init();
$api = 'http://192.168.43.151/gas/gas_web/flutter_api/update_foto_profil.php';
$post = [
    'id_pengguna' => $id,
    'foto_profil' => new CURLFile($image)
];
curl_setopt($ch, CURLOPT_URL, $api);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($response === false) { echo "Curl error: " . curl_error($ch) . "\n"; exit(3); }
echo "Upload HTTP: $http\n";
echo "Response:\n$response\n";
$data = json_decode($response, true);
if (isset($data['foto_profil'])) {
    $url = $data['foto_profil'];
    echo "Attempt to fetch signed URL: $url\n";
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, $url);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch2, CURLOPT_HEADER, true);
    curl_setopt($ch2, CURLOPT_NOBODY, false);
    $resp2 = curl_exec($ch2);
    $http2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    echo "Fetch signed URL HTTP: $http2\n";
    curl_close($ch2);
    // Also test unauthorized access without signature (should be 403)
    $noSig = 'http://192.168.43.151/gas/gas_web/login/user/foto_profil_image.php?id=' . urlencode($id);
    $ch3 = curl_init();
    curl_setopt($ch3, CURLOPT_URL, $noSig);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HEADER, true);
    curl_setopt($ch3, CURLOPT_NOBODY, false);
    $resp3 = curl_exec($ch3);
    $http3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    echo "Fetch without sig HTTP: $http3\n";
    curl_close($ch3);
}
curl_close($ch);
echo "Done.\n";
?>