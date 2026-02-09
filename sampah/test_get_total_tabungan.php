<?php
// Quick test to call get_total_tabungan.php with a supplied id_tabungan
$_REQUEST['id_tabungan'] = isset($argv[1]) ? $argv[1] : '95';
include __DIR__ . '/../gas_web/flutter_api/get_total_tabungan.php';
