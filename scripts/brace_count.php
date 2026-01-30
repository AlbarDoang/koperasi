<?php
$file = __DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php';
$lines = file($file);
$open = 0; $close = 0;
foreach ($lines as $i => $line) {
    $open += substr_count($line, '{');
    $close += substr_count($line, '}');
    if ($close > $open) {
        echo "Unmatched close brace at line " . ($i+1) . "\n";
        break;
    }
}
echo "Total open:{" . $open . " close:" . $close . "\n";
?>