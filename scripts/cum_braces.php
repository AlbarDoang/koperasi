<?php
$lines = file(__DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php');
$balance = 0;
foreach ($lines as $i=>$line) {
    $balance += substr_count($line,'{');
    $balance -= substr_count($line,'}');
    if ($i+1 >= 340 && $i+1 <= 380) echo sprintf('%4d: bal=%2d %s', $i+1, $balance, rtrim($line)) . "\n";
}
echo "Final balance: $balance\n";
?>