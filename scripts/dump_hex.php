<?php
$path = __DIR__ . DIRECTORY_SEPARATOR . 'example.jpg';
if (!file_exists($path)) { echo "MISSING\n"; exit(1); }
$contents = file_get_contents($path);
$size = strlen($contents);
$head = substr($contents, 0, 64);
$tail = substr($contents, -64);
echo "size=" . $size . PHP_EOL;
echo "head=" . bin2hex($head) . PHP_EOL;
echo "tail=" . bin2hex($tail) . PHP_EOL;
?>