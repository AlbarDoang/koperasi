<?php
// CLI test: simulate DataTables POST
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 5,
    'search' => ['value' => ''],
    'order' => [[ 'column' => 1, 'dir' => 'desc' ]]
];

include __DIR__ . '/../gas_web/login/function/fetch_keluar_admin.php';
