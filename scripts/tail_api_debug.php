<?php
$f = __DIR__ . '/../flutter_api/api_debug.log';
if (!file_exists($f)) {
    echo "NO LOG FILE\n";
    exit(0);
}
$lines = file($f, FILE_IGNORE_NEW_LINES);
$last = array_slice($lines, -200);
foreach ($last as $l) {
    echo $l . "\n";
}
