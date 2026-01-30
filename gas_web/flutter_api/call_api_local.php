<?php
// Server-side helper to call endpoint via HTTP and return debug info
function call($path, $post) {
    $url = 'http://localhost/gas/gas_web/flutter_api/' . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $res = curl_exec($ch);
    if ($res === false) {
        return ['error' => curl_error($ch)];
    }
    $info = curl_getinfo($ch);
    $headerLen = $info['header_size'];
    $header = substr($res, 0, $headerLen);
    $body = substr($res, $headerLen);
    curl_close($ch);
    return ['info' => $info, 'header' => $header, 'body' => $body];
}

header('Content-Type: application/json');
$res = call('get_summary_by_jenis.php', ['id_tabungan' => '95']);
echo json_encode($res, JSON_PRETTY_PRINT);
