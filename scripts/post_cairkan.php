<?php
$base = 'http://localhost/gas/gas_web/flutter_api';
function post($url, $data) {
    $opts = ['http' => [ 'method' => 'POST', 'header' => "Content-type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($data), 'timeout' => 10 ]];
    $context = stream_context_create($opts);
    $res = @file_get_contents($url, false, $context);
    return $res === false ? null : json_decode($res, true);
}
$res = post($base . '/cairkan_tabungan.php', ['id_pengguna' => 97, 'id_jenis_tabungan' => 1, 'nominal' => 1000]);
print_r($res);
