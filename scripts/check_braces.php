<?php
$s = file('c:\xampp\htdocs\gas\gas_web\flutter_api\get_summary_by_jenis.php');
$count = 0;
foreach ($s as $i => $line) {
    $lineNum = $i + 1;
    for ($j = 0; $j < strlen($line); $j++) {
        $c = $line[$j];
        if ($c === '{') $count++;
        if ($c === '}') $count--;
    }
    echo "$lineNum -> count=$count\n";
}
echo "FINAL count=$count\n";
?>