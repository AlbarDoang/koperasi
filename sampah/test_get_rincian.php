<?php
// test_get_rincian.php - fetch get_rincian_tabungan as CLI include
$_REQUEST['id_tabungan'] = '97';
ob_start();
include __DIR__ . '/../gas_web/flutter_api/get_rincian_tabungan.php';
$out = ob_get_clean();
file_put_contents(__DIR__ . '/test_get_rincian_output.txt', $out);
echo $out;
