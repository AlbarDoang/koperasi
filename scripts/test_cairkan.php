<?php
// test_cairkan.php - simulate a basic cairkan_tabungan request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['id_pengguna'] = '95';
$_POST['id_jenis_tabungan'] = '1';
$_POST['nominal'] = '10000';
ob_start();
include __DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php';
$out = ob_get_clean();
file_put_contents(__DIR__ . '/test_cairkan_output.txt', $out);
echo "Done. Output saved to scripts/test_cairkan_output.txt\n";
echo $out; 
?>