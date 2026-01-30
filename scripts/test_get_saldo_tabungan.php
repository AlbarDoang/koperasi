<?php
// Quick test to call get_saldo_tabungan.php with a supplied id_tabungan
$val = isset($argv[1]) ? $argv[1] : '95';
$_REQUEST['id_tabungan'] = $val;
$_GET['id_tabungan'] = $val;
include __DIR__ . '/../gas_web/flutter_api/get_saldo_tabungan.php';
