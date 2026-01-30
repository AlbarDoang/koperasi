<?php
$lines = file(__DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php');
for($i=360;$i<=376;$i++){
    echo sprintf('%4d: %s', $i, rtrim($lines[$i-1])) . "\n";
}
?>