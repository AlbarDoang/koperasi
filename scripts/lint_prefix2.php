<?php
$N = intval($argv[1] ?? 200);
$src = file(__DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php');
$prefix = array_slice($src, 0, $N);
// remove leading PHP open tag if present
if (strpos($prefix[0],'<?php') !== false) $prefix[0] = preg_replace('/^<\?php\s*/','',$prefix[0],1);
$fn = __DIR__ . '/prefix2.tmp.php';
file_put_contents($fn, "<?php\n" . implode('', $prefix) . "\n?>");
$output = null; $ret = null;
exec("php -l " . escapeshellarg($fn) . " 2>&1", $output, $ret);
echo implode("\n", $output) . "\n";
?>