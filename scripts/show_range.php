<?php
$start = intval($argv[1] ?? 1);
$end = intval($argv[2] ?? $start+10);
$lines = file(__DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php');
for($i=$start;$i<=$end;$i++){
    echo sprintf('%4d: %s', $i, rtrim($lines[$i-1])) . "\n";
}
?>