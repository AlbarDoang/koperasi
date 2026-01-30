<?php
$url = 'http://192.168.1.8/gas/gas_web/login/admin/approval/approve_user_process.php';
$data = json_encode(['id'=>1, 'action'=>'approve']);
$opts = [
  'http'=>[
    'method'=>'POST',
    'header'=>'Content-Type: application/json\r\n',
    'content'=>$data,
    'ignore_errors'=>true,
    'timeout'=>5
  ]
];
$ctx = stream_context_create($opts);
$resp = @file_get_contents($url, false, $ctx);
$status = null;
foreach($http_response_header as $h) if (stripos($h, 'HTTP/')===0) $status = $h;
echo "HTTP: " . ($status ?: 'unknown') . "\n";
echo "---RAW RESPONSE START---\n";
echo ($resp === false ? '(no body)' : $resp) . "\n";
echo "---RAW RESPONSE END---\n";
// show headers
echo "---HEADERS---\n";
foreach($http_response_header as $h) echo $h . "\n";
?>