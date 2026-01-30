<?php
// debug_cairkan.php - run cairkan_tabungan.php from CLI with simulated POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['id_pengguna'] = '97';
$_POST['jenis'] = '1';
$_POST['jumlah'] = '100';
ob_start();
include __DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php';
$out = ob_get_clean();
file_put_contents(__DIR__ . '/debug_cairkan_output.txt', $out);
echo "Done. Output saved to scripts/debug_cairkan_output.txt\n"; 
?>