<?php
// Quick CLI test for get_history_by_jenis
$_POST = [];
$_POST['id_tabungan'] = '97';
// pass a jenis that exists or leave as number; to be safe pass 'Reguler' if it exists, otherwise numeric '1'
$_POST['jenis'] = '1';
$_POST['periode'] = '365';
$_POST['limit'] = 200;
// ensure errors are visible here
ini_set('display_errors',1);
error_reporting(E_ALL);
// Emulate a POST request environment for CLI
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . '/../gas_web/flutter_api/get_history_by_jenis.php';
