<?php
// scripts/admin_test_pinjaman_run.php
// Single CLI script to create a pinjaman via /api/pinjaman/submit.php and verify it appears on admin approval page.
// Usage: php scripts/admin_test_pinjaman_run.php --user=1 --cookie='PHPSESSID=abc' [--base='http://localhost/gas_web'] [--amount=100000]

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$options = getopt('', ['user:','cookie:','base::','amount::','tenor::','tujuan::','help']);
if (isset($options['help']) || empty($options['user']) || empty($options['cookie'])) {
    echo "Usage: php scripts/admin_test_pinjaman_run.php --user=<id> --cookie='PHPSESSID=...' [--base='http://localhost/gas_web'] [--amount=100000] [--tenor=6] [--tujuan='desc']\n";
    exit(0);
}

$userId = (int)$options['user'];
$cookie = $options['cookie'];
$base = rtrim($options['base'] ?? 'http://localhost/gas_web', " /");
$amount = isset($options['amount']) ? (float)$options['amount'] : 100000;
$tenor = isset($options['tenor']) ? (int)$options['tenor'] : 6;
$tujuan = $options['tujuan'] ?? ('admin-test-' . time() . '-' . bin2hex(random_bytes(3)));

$submitUrl = $base . '/api/pinjaman/submit.php';
$adminUrl = $base . '/admin/pinjaman_approval.php';

function http_post_json($url, $jsonBody, $headers = []) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json','Accept: application/json'], $headers));
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $err === '', 'code' => $code, 'body' => $resp, 'error' => $err];
    }
    // fallback: file_get_contents
    $opts = ['http' => ['method' => 'POST', 'header' => implode("\r\n", array_merge(['Content-Type: application/json','Accept: application/json'],$headers)), 'content' => $jsonBody, 'ignore_errors' => true]];
    $ctx = stream_context_create($opts);
    $body = file_get_contents($url, false, $ctx);
    $meta = $http_response_header ?? [];
    // try to parse status line
    $code = 0;
    if (!empty($meta) && preg_match('#HTTP/\d+\.\d+\s+(\d{3})#', $meta[0], $m)) $code = (int)$m[1];
    return ['ok' => true, 'code' => $code, 'body' => $body, 'error' => ''];
}

function http_get($url, $headers = []) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['ok' => $err === '', 'code' => $code, 'body' => $resp, 'error' => $err];
    }
    $opts = ['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'ignore_errors' => true]];
    $ctx = stream_context_create($opts);
    $body = file_get_contents($url, false, $ctx);
    $meta = $http_response_header ?? [];
    $code = 0;
    if (!empty($meta) && preg_match('#HTTP/\d+\.\d+\s+(\d{3})#', $meta[0], $m)) $code = (int)$m[1];
    return ['ok' => true, 'code' => $code, 'body' => $body, 'error' => ''];
}

// 1) Submit pinjaman (JSON)
$payload = ['id_pengguna' => $userId, 'jumlah_pinjaman' => $amount, 'tenor' => $tenor, 'tujuan_penggunaan' => $tujuan];
$resp = http_post_json($submitUrl, json_encode($payload));
$out = ['submit' => $resp, 'submitted_payload' => $payload];
if (!$resp['ok']) {
    $out['success'] = false;
    $out['error'] = 'HTTP client error: ' . $resp['error'];
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(2);
}

$decoded = json_decode($resp['body'], true);
$out['submit_decoded'] = $decoded;

if (!is_array($decoded) || empty($decoded['status']) || !$decoded['status']) {
    $out['success'] = false;
    $out['error'] = 'API returned error';
    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(3);
}

$insertedId = $decoded['id'] ?? null;
$out['inserted_id'] = $insertedId;

// 2) Wait briefly and fetch admin page with cookie
sleep(1);
$headers = ["Cookie: $cookie", 'Accept: text/html'];
$adminResp = http_get($adminUrl, $headers);
$out['admin_fetch'] = ['code' => $adminResp['code']];

$found = false;
$snippet = null;
if ($adminResp['ok'] && is_string($adminResp['body'])) {
    $body = $adminResp['body'];
    // Try to find by unique tujuan_penggunaan text (best) or by inserted id or by user id + amount
    if ($insertedId !== null && $insertedId !== '') {
        if (strpos($body, (string)$insertedId) !== false) $found = true;
    }
    if (!$found && !empty($tujuan) && strpos($body, $tujuan) !== false) $found = true;
    if (!$found) {
        // Try match userId and amount
        if (strpos($body, (string)$userId) !== false && strpos($body, (string)intval($amount)) !== false) $found = true;
    }

    // Grab snippet for debugging
    $pos = $insertedId ? strpos($body, (string)$insertedId) : (strpos($body, $tujuan) ?: strpos($body, (string)$userId));
    if ($pos !== false && $pos !== null) {
        $start = max(0, $pos - 200);
        $snippet = substr($body, $start, 500);
    } else {
        $snippet = substr($body, 0, 500);
    }
}

$out['found_on_admin'] = $found;
$out['admin_snippet'] = $snippet;

$out['success'] = $found;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($found ? 0 : 5);
