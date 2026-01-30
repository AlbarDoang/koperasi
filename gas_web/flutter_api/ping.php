<?php
// Simple health-check endpoint for mobile app to verify API reachability
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$result = array(
    'ok' => true,
    'ts' => date('c'),
    'server' => 'GAS API',
    'version' => '1.0'
);
echo json_encode($result);
