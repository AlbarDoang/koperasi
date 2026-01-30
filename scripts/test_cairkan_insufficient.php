<?php
// Test: call cairkan_tabungan.php with a nominal greater than available to assert proper error message
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['id_pengguna'] = isset($argv[1]) ? $argv[1] : '95';
$_POST['id_jenis_tabungan'] = isset($argv[2]) ? $argv[2] : '1';
$_POST['nominal'] = isset($argv[3]) ? $argv[3] : '999999999';
include __DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php';
