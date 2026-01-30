<?php
$base = 'http://localhost/gas/gas_web/flutter_api';
function post($url, $data) {
    $opts = ['http' => [ 'method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($data), 'timeout' => 10 ]];
    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);
    return $res === false ? null : json_decode($res, true);
}
$id = isset($argv[1]) ? intval($argv[1]) : 60;
$res = post($base . '/approve_penarikan.php', ['no_keluar' => $id, 'action' => 'approve', 'approved_by' => 1]);
print_r($res);
