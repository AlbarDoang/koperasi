<?php
// CLI integration test for the full user flow
// Usage: php flow_test.php [--base-url=http://192.168.1.8/gas/gas_web] [--db-host=localhost] [--db-user=root] [--db-pass=] [--db-name=tabungan]

$options = getopt('', ['base-url::','db-host::','db-user::','db-pass::','db-name::','help::']);
$BASE = rtrim($options['base-url'] ?? 'http://192.168.1.8/gas/gas_web', '/');
$dbHost = $options['db-host'] ?? 'localhost';
$dbUser = $options['db-user'] ?? 'root';
$dbPass = $options['db-pass'] ?? '';
$dbName = $options['db-name'] ?? 'tabungan';

if (isset($options['help'])) {
    echo "Usage: php flow_test.php [--base-url=http://192.168.1.8/gas/gas_web] [--db-host=...]\n";
    exit(0);
}

function http_post_json($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status'=>$code,'body'=>$resp,'error'=>$err];
}

function http_post_multipart($url, $fields, $files) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    $post = $fields;
    foreach ($files as $name => $path) {
        if (class_exists('CURLFile')) {
            $post[$name] = new CURLFile($path);
        } else {
            $post[$name] = '@' . $path;
        }
    }
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status'=>$code,'body'=>$resp,'error'=>$err];
}

function http_post_form($url, $fields) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status'=>$code,'body'=>$resp,'error'=>$err];
}

function die_ok($msg) { echo "[OK] $msg\n"; }
function die_fail($msg) { echo "[FAIL] $msg\n"; exit(1); }

// DB connect
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_errno) die_fail('DB connect failed: ' . $mysqli->connect_error);
$mysqli->set_charset('utf8mb4');

echo "Using BASE=$BASE, DB={$dbUser}@{$dbHost}/{$dbName}\n";

// Helper: small PNG 1x1 (base64) used to create temp files
$smallPngB64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAuMBgk7f3h0AAAAASUVORK5CYII=';

function create_temp_image($prefix) {
    global $smallPngB64;
    $tmp = tempnam(sys_get_temp_dir(), $prefix);
    // rename to have .png extension (some servers look at extension)
    $new = $tmp . '.png';
    rename($tmp, $new);
    $data = base64_decode($smallPngB64);
    file_put_contents($new, $data);
    return $new;
}

// Generate unique phone number (08...)
$phone = '08' . strval(mt_rand(100000000, 999999999));
$password = 'TestPass123!';
$name = 'Test User ' . substr($phone, -4);

echo "\n=== Register (stage 1) ===\n";
$resp = http_post_json($BASE . '/flutter_api/register_tahap1.php', [
    'no_hp' => $phone,
    'kata_sandi' => $password,
    'nama_lengkap' => $name,
    'alamat_domisili' => 'Jl. Test 1',
    'tanggal_lahir' => '1990-01-01',
    'setuju_syarat' => 1
]);
if ($resp['error']) die_fail('HTTP error: ' . $resp['error']);
$body = json_decode($resp['body'], true);
if (!$body || empty($body['success'])) die_fail('Register step failed: ' . $resp['body']);
$id_pengguna = $body['data']['id_pengguna'] ?? $body['id_pengguna'] ?? $body['id'] ?? null;
// Older variations of the API may return id under different keys; try to read id from top-level if present
if (!$id_pengguna && isset($body['id_pengguna'])) $id_pengguna = $body['id_pengguna'];
if (!$id_pengguna) die_fail('No id_pengguna returned');
die_ok("Register succeeded, id_pengguna={$id_pengguna}, phone={$phone}");

// Upload KTP & selfie
echo "\n=== Register (stage 2) upload KTP & selfie ===\n";
$ktp = create_temp_image('ktp');
$selfie = create_temp_image('selfie');
$resp = http_post_multipart($BASE . '/flutter_api/register_tahap2.php', ['id_pengguna' => $id_pengguna], ['foto_ktp' => $ktp, 'foto_selfie' => $selfie]);
@unlink($ktp); @unlink($selfie);
if ($resp['error']) die_fail('HTTP error: ' . $resp['error']);
$body = json_decode($resp['body'], true);
if (!$body || empty($body['success'])) die_fail('register_tahap2 failed: ' . $resp['body']);
die_ok('register_tahap2: KTP & selfie uploaded, status submitted expected');

// New test: upload oversized KTP (>15MB) should be rejected with a friendly message
echo "\n=== Upload oversized KTP (>15MB) ===\n";
$big = tempnam(sys_get_temp_dir(), 'bigktp') . '.png';
$data = base64_decode($smallPngB64);
file_put_contents($big, $data);
$targetSize = (15 * 1024 * 1024) + (1 * 1024 * 1024); // 16MB
$cur = filesize($big);
if ($cur < $targetSize) {
    $f = fopen($big, 'ab');
    $need = $targetSize - $cur;
    $chunk = str_repeat("\0", 1024 * 1024);
    while ($need > 0) {
        $write = ($need > strlen($chunk)) ? $chunk : substr($chunk, 0, $need);
        fwrite($f, $write);
        $need -= strlen($write);
    }
    fclose($f);
}
$self = create_temp_image('selfie');
$resp = http_post_multipart($BASE . '/flutter_api/register_tahap2.php', ['id_pengguna' => $id_pengguna], ['foto_ktp' => $big, 'foto_selfie' => $self]);
@unlink($big); @unlink($self);
if ($resp['error']) die_fail('HTTP error: ' . $resp['error']);
$body = json_decode($resp['body'], true);
if ($body && empty($body['success']) && strpos(($body['message'] ?? ''), 'Ukuran foto KTP terlalu besar') !== false) {
    die_ok('Oversized KTP properly rejected');
} else {
    die_fail('Oversized KTP not rejected as expected: ' . $resp['body']);
}

// New test: Register using international 62 format should result in DB storing local 08 format
echo "\n=== Register with 62 format input ===\n";
$phone2_local = '08' . strval(mt_rand(100000000, 999999999));
$phone2_int = '62' . substr($phone2_local, 1);
$resp = http_post_json($BASE . '/flutter_api/register_tahap1.php', [
    'no_hp' => $phone2_int,
    'kata_sandi' => $password,
    'nama_lengkap' => 'Test User Int',
    'alamat_domisili' => 'Jl. Test 2',
    'tanggal_lahir' => '1992-02-02',
    'setuju_syarat' => 1
]);
if ($resp['error']) die_fail('HTTP error: ' . $resp['error']);
$body = json_decode($resp['body'], true);
if (!$body || empty($body['success'])) die_fail('Register with 62 failed: ' . $resp['body']);
$id2 = $body['data']['id_pengguna'] ?? $body['id_pengguna'] ?? $body['id'] ?? null;
if (!$id2) die_fail('No id returned for register with 62');

// Query DB to verify stored format
$q = $mysqli->prepare('SELECT no_hp FROM pengguna WHERE id = ? LIMIT 1');
$q->bind_param('i', $id2); $q->execute(); $res = $q->get_result(); $row = $res->fetch_assoc(); $stored_no = $row['no_hp'] ?? null; $q->close();
if (!$stored_no) die_fail('Unable to fetch stored phone for id ' . $id2);
if (substr($stored_no, 0, 1) !== '0') die_fail('Stored phone not in local 08 format: ' . $stored_no);
die_ok('Register with 62 stored as local 08: ' . $stored_no);

// Send OTP (activasi)
echo "\n=== Send OTP ===\n";
$resp = http_post_json($BASE . '/flutter_api/aktivasi_akun.php', ['action' => 'send_otp', 'no_hp' => $phone]);
if ($resp['error']) die_fail('HTTP error: ' . $resp['error']);
$body = json_decode($resp['body'], true);
if (!$body || empty($body['success'])) die_fail('send_otp failed: ' . $resp['body']);
die_ok('send_otp succeeded (OTP sent)');

// Fetch latest OTP from DB
$normalized = preg_replace('/[^0-9]/', '', $phone);
if (substr($normalized,0,1) === '0') $no_wa = '62' . substr($normalized,1); else $no_wa = $normalized;
$q = $mysqli->prepare('SELECT kode_otp FROM otp_codes WHERE no_wa = ? ORDER BY created_at DESC LIMIT 1');
$q->bind_param('s', $no_wa);
$q->execute();
$res = $q->get_result();
if (!$res || $res->num_rows === 0) die_fail('OTP not found in DB');
$row = $res->fetch_assoc();
$otp = $row['kode_otp'];
$q->close();
die_ok("OTP retrieved from DB: {$otp}");



// Also verify the WEB path (verifikasi_otp.php) redirects to the login page and sets session success
echo "\n=== Verify OTP (Web) and redirect ===\n";
// Use cookie file so we can retain session between POST and subsequent GET
$cookie_file = sys_get_temp_dir() . '/flowtest_cookie_' . uniqid();
$ch = curl_init($BASE . '/verifikasi_otp.php?no_hp=' . urlencode($phone));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['kode_otp' => $otp]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($raw, 0, $header_size);
$body_text = substr($raw, $header_size);
$err = curl_error($ch);
curl_close($ch);
if ($err) die_fail('cURL error (web verify): ' . $err);
// Expecting a redirect to login/
if ($code !== 302 && $code !== 303) die_fail('Expected redirect status from verifikasi_otp.php, got HTTP ' . $code . '\nHeaders:\n' . $headers);
if (!preg_match('/Location:\s*(\S+)/i', $headers, $m)) die_fail('No Location header in response: ' . $headers);
$loc = $m[1];
if (stripos($loc, 'login/') === false) die_fail('verifikasi_otp.php did not redirect to login/, got: ' . $loc);

die_ok('verifikasi_otp.php redirected to login/');

// Follow the redirect Location returned by verifikasi_otp.php to ensure we get the exact page
$redirect_path = $loc;
if (strpos($redirect_path, '://') === false) {
    // relative Location - build absolute URL against BASE
    $redirect_url = rtrim($BASE, '/') . '/' . ltrim($redirect_path, '/');
} else {
    $redirect_url = $redirect_path;
}
$ch2 = curl_init($redirect_url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookie_file);
$html = curl_exec($ch2);
$err2 = curl_error($ch2);
curl_close($ch2);
if ($err2) die_fail('cURL error when fetching login page: ' . $err2);
$expected_msg = 'Pengajuan aktivasi akun diterima, silakan tunggu persetujuan admin.';
if (stripos($html, $expected_msg) === false) {
    $dump = sys_get_temp_dir() . '/flowtest_login_html_' . uniqid() . '.html';
    file_put_contents($dump, $html);
    die_fail('Login redirect page did not contain the activation success message; dumped HTML to ' . $dump);
}
die_ok('Login page contains activation success message');

// Confirm DB status is pending
$q = $mysqli->prepare('SELECT LOWER(status_akun) as st FROM pengguna WHERE id = ? LIMIT 1');
$q->bind_param('i', $id_pengguna);
$q->execute();
$res = $q->get_result();
$row = $res->fetch_assoc();
$q->close();
if (strtolower($row['st']) !== 'pending') die_fail('DB: status_akun is not pending after verify_otp, got ' . ($row['st'] ?? 'NULL'));
die_ok('DB status_akun = pending');

// ADMIN: APPROVE
echo "\n=== Admin approve user ===\n";
$adminResp = http_post_json($BASE . '/login/admin/approval/approve_user_process.php', ['id' => intval($id_pengguna), 'action' => 'approve']);
if ($adminResp['error']) die_fail('Admin approve HTTP error: ' . $adminResp['error']);
$body = json_decode($adminResp['body'], true);
if (!$body || empty($body['success'])) die_fail('Admin approve failed: ' . $adminResp['body']);

// Confirm DB is approved
$q = $mysqli->prepare('SELECT LOWER(status_akun) as st FROM pengguna WHERE id = ? LIMIT 1');
$q->bind_param('i', $id_pengguna);
$q->execute();
$res = $q->get_result();
$row = $res->fetch_assoc();
$q->close();
if (strtolower($row['st']) !== 'approved') die_fail('DB: status_akun not approved after admin approve: ' . ($row['st'] ?? 'NULL'));
die_ok('Admin approve marked user as approved');

// LOGIN should succeed and indicate needs_set_pin = true
echo "\n=== Login (expect needs_set_pin = true) ===\n";
$loginResp = http_post_json($BASE . '/flutter_api/login.php', ['no_hp' => $phone, 'password' => $password]);
if ($loginResp['error']) die_fail('Login HTTP error: ' . $loginResp['error']);
$body = json_decode($loginResp['body'], true);
if (!$body || empty($body['success'])) die_fail('Login failed: ' . $loginResp['body']);
if (empty($body['needs_set_pin'])) die_fail('Login did not indicate needs_set_pin = true');
die_ok('Login returned needs_set_pin = true');

// Also confirm web client request gets a redirect URL to set_pin
$loginRespWeb = http_post_json($BASE . '/flutter_api/login.php?client=web', ['no_hp' => $phone, 'password' => $password, 'client' => 'web']);
if ($loginRespWeb['error']) die_fail('Login (web) HTTP error: ' . $loginRespWeb['error']);
$bodyWeb = json_decode($loginRespWeb['body'], true);
if (!$bodyWeb || empty($bodyWeb['success'])) die_fail('Web login failed: ' . $loginRespWeb['body']);
if (empty($bodyWeb['needs_set_pin'])) die_fail('Web login did not indicate needs_set_pin = true');
if (empty($bodyWeb['redirect']) || stripos($bodyWeb['redirect'], 'set_pin') === false) die_fail('Web login did not return redirect to set_pin.php: ' . json_encode($bodyWeb));
die_ok('Web login returned redirect to set_pin.php');

// SET PIN via web set_pin.php (POST form)
echo "\n=== Set transaction PIN ===\n";
$pin = sprintf('%06d', mt_rand(100000, 999999));
$resp = http_post_form($BASE . '/set_pin.php?no_hp=' . urlencode($phone), ['pin' => $pin, 'pin_confirm' => $pin]);
// set_pin redirects; just check DB for pin
$q = $mysqli->prepare('SELECT pin FROM pengguna WHERE id = ? LIMIT 1');
$q->bind_param('i', $id_pengguna);
$q->execute();
$res = $q->get_result();
$row = $res->fetch_assoc();
$q->close();
if (empty($row['pin'])) die_fail('PIN not set in DB');
// verify hash is valid
if (!password_verify($pin, $row['pin'])) die_fail('Stored PIN hash does not match provided PIN');
die_ok('PIN set and stored in DB');

// Re-login should now return needs_set_pin = false
echo "\n=== Login after PIN set (expect needs_set_pin = false) ===\n";
$loginResp = http_post_json($BASE . '/flutter_api/login.php', ['no_hp' => $phone, 'password' => $password]);
$body = json_decode($loginResp['body'], true);
if (!$body || empty($body['success'])) die_fail('Login failed after PIN set: ' . $loginResp['body']);
if (!empty($body['needs_set_pin'])) die_fail('needs_set_pin should be false after setting PIN');
die_ok('Login after PIN set succeeded and needs_set_pin = false');

// ===== REJECTION SCENARIO =====
// Create a second user and go through same flow but admin rejects

echo "\n=== REJECTION SCENARIO: create user and submit for verification ===\n";
$phone2 = '08' . strval(mt_rand(100000000, 999999999));
$resp = http_post_json($BASE . '/flutter_api/register_tahap1.php', ['no_hp' => $phone2, 'kata_sandi' => $password, 'nama_lengkap' => 'RejectUser']);
$body = json_decode($resp['body'], true);
if (!$body || empty($body['success'])) die_fail('register2 stage1 failed: ' . $resp['body']);
$id2 = $body['data']['id_pengguna'] ?? $body['id_pengguna'] ?? null;
if (!$id2) die_fail('No id_pengguna returned for reject user');
$ktp = create_temp_image('ktp2'); $selfie = create_temp_image('selfie2');
$resp = http_post_multipart($BASE . '/flutter_api/register_tahap2.php', ['id_pengguna' => $id2], ['foto_ktp' => $ktp, 'foto_selfie' => $selfie]);
@unlink($ktp); @unlink($selfie);
$body = json_decode($resp['body'], true);
if (!$body || empty($body['success'])) die_fail('register_tahap2 failed for reject scenario: ' . $resp['body']);

// Send and verify OTP
$resp = http_post_json($BASE . '/flutter_api/aktivasi_akun.php', ['action'=>'send_otp','no_hp'=>$phone2]);
$normalized2 = preg_replace('/[^0-9]/', '', $phone2);
if (substr($normalized2,0,1)=='0') $no_wa2 = '62' . substr($normalized2,1); else $no_wa2 = $normalized2;
$q = $mysqli->prepare('SELECT kode_otp FROM otp_codes WHERE no_wa = ? ORDER BY created_at DESC LIMIT 1');
$q->bind_param('s', $no_wa2); $q->execute(); $res = $q->get_result(); $row = $res->fetch_assoc(); $q->close(); $otp2 = $row['kode_otp'] ?? null;
if (!$otp2) die_fail('OTP for reject scenario not found');
$resp = http_post_json($BASE . '/flutter_api/aktivasi_akun.php', ['action'=>'verify_otp','no_hp'=>$phone2,'otp'=>$otp2]);
$body = json_decode($resp['body'], true); if (!$body || empty($body['success'])) die_fail('verify_otp failed for reject scenario');

// Admin reject
$reason = 'Dokumen tidak jelas';
$adminResp = http_post_json($BASE . '/login/admin/approval/approve_user_process.php', ['id'=>intval($id2), 'action'=>'reject', 'reason'=>$reason]);
$body = json_decode($adminResp['body'], true); if (!$body || empty($body['success'])) die_fail('Admin reject failed: ' . $adminResp['body']);
die_ok('Admin rejected user');

// Attempt login - should be 403 with reason
$login = http_post_json($BASE . '/flutter_api/login.php', ['no_hp'=>$phone2, 'password'=>$password]);
$loginBody = json_decode($login['body'], true);
if ($login['status'] !== 403 && (!is_array($loginBody) || (!empty($loginBody['success'])))) die_fail('Login for rejected user did not return 403: ' . $login['body']);
$msg = $loginBody['message'] ?? '';
if (stripos($msg, 'ditolak') === false && stripos($msg, 'rejected') === false) die_fail('Rejected login message not containing rejection: ' . $msg);
if (stripos($msg, $reason) === false) die_fail('Rejected login message did not include reason: ' . $msg);
die_ok('Rejected user login prevented and includes rejection reason');

echo "\nALL TESTS PASSED\n";
exit(0);
?>