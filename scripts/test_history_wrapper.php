<?php
$script = __DIR__ . '/../gas_web/flutter_api/get_history_by_jenis.php';
$payload = "id_tabungan=97&jenis=1&limit=5";
$tmp = sys_get_temp_dir() . '/hist_wrapper_' . uniqid() . '.php';
$wrap = "<?php\n" . "\$_POST = array(); parse_str('" . addslashes($payload) . "', \$_POST); \$_SERVER['REQUEST_METHOD'] = 'POST'; include('" . addslashes($script) . "');\n";
file_put_contents($tmp, $wrap);
$cmd = 'php ' . escapeshellarg($tmp);
exec($cmd, $out, $rc);
@unlink($tmp);
$out_str = implode("\n", $out);
file_put_contents(__DIR__ . '/test_history_wrapper_output.json', $out_str);
echo substr($out_str,0,400) . "\n";