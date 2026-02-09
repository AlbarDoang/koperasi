<?php
// CLI test with server vars to simulate web request and debug flag
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
$_SERVER['HTTPS'] = 'off';

$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'search' => ['value' => ''],
    'order' => [[ 'column' => 1, 'dir' => 'desc' ]],
    'status' => 'all',
    '__debug__' => '1'
];

include __DIR__ . '/../gas_web/login/function/fetch_keluar_admin.php';
