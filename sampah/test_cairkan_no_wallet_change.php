<?php
/**
 * Test: call cairkan_tabungan.php and assert pengguna.saldo unchanged and
 * no WALLET_CREDIT/DEBIT entries created for that user in flutter_api/saldo_audit.log
 * Usage: php scripts/test_cairkan_no_wallet_change.php
 */
require __DIR__ . '/../gas_web/flutter_api/connection.php';

// Choose a user/jens with available balance >= nominal
$nominal = 1; // small amount
$jenis = 1; // default
$found = false;
$q = "SELECT tm.id_pengguna as id, tm.id_jenis_tabungan as jenis, COALESCE(SUM(tm.jumlah),0) as total_in, (SELECT COALESCE(SUM(jumlah),0) FROM tabungan_keluar WHERE id_pengguna=tm.id_pengguna AND id_jenis_tabungan=tm.id_jenis_tabungan) as total_out FROM tabungan_masuk tm GROUP BY tm.id_pengguna, tm.id_jenis_tabungan HAVING (total_in - total_out) >= " . intval($nominal) . " ORDER BY id DESC LIMIT 1";
$res = $connect->query($q);
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $uid = intval($row['id']);
    $jenis = intval($row['jenis']);
    $found = true;
    $stmt = $connect->prepare('SELECT saldo FROM pengguna WHERE id = ? LIMIT 1'); $stmt->bind_param('i', $uid); $stmt->execute(); $r = $stmt->get_result(); $row2 = $r->fetch_assoc(); $saldo_before = floatval($row2['saldo']); $stmt->close();
}
if (!$found) { echo "No user with available per-jenis balance >= {$nominal} found.\n"; exit(1); }

// read file size to build a precise baseline (avoid false positives from older entries)
$logfile = __DIR__ . '/../gas_web/flutter_api/saldo_audit.log';
$baseline_size = 0;
if (file_exists($logfile)) {
    $baseline_size = filesize($logfile);
}

// perform request by running the endpoint in a separate PHP process (to avoid include() exiting the parent process)
$scriptPath = __DIR__ . '/../gas_web/flutter_api/cairkan_tabungan.php';
$payload = "id_pengguna=" . urlencode((string)$uid) . "&id_jenis_tabungan=" . urlencode((string)$jenis) . "&nominal=" . urlencode((string)$nominal);
// Create a short temporary wrapper PHP file to avoid quoting/parse issues with php -r and capture output robustly
$tmp = sys_get_temp_dir() . '/cairkan_wrapper_' . uniqid() . '.php';
$payload_b64 = base64_encode($payload);
$wrap = "<?php\n" . "\$payload = base64_decode('" . $payload_b64 . "'); parse_str(\$payload, \$_POST); \$_SERVER['REQUEST_METHOD'] = 'POST'; include('" . addslashes($scriptPath) . "');\n";
file_put_contents($tmp, $wrap);
$cmd = 'php ' . escapeshellarg($tmp);
exec($cmd, $outLines, $rc);
$out = implode("\n", $outLines);
@unlink($tmp);
// The included process will exit after sending JSON; parse output
echo "endpoint response:\n" . $out . "\n";
if ($rc !== 0) {
    echo "WARN: endpoint process exited with code {$rc}\n";
}


// check saldo after
$stmt = $connect->prepare('SELECT saldo FROM pengguna WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $uid); $stmt->execute(); $r = $stmt->get_result(); $row = $r->fetch_assoc(); $saldo_after = floatval($row['saldo']); $stmt->close();

if ($saldo_after !== $saldo_before) {
    echo "FAIL: pengguna.saldo changed from {$saldo_before} to {$saldo_after}\n";
    exit(2);
} else {
    echo "OK: pengguna.saldo unchanged ({$saldo_after})\n";
}

// Check saldo_audit.log for WALLET_CREDIT/DEBIT entries added since baseline
$diff = '';
if (file_exists($logfile)) {
    $content = file_get_contents($logfile);
    $diff = ($baseline_size < strlen($content)) ? substr($content, $baseline_size) : '';
}
if (stripos($diff, "WALLET_CREDIT user={$uid}") !== false || stripos($diff, "WALLET_DEBIT user={$uid}") !== false) {
    echo "FAIL: Detected WALLET_CREDIT/DEBIT entries for user {$uid} after cairkan. Review saldo_audit.log.\n";
    echo "Recent log diff:\n" . $diff . "\n";
    exit(3);
} else {
    echo "OK: No WALLET_CREDIT/DEBIT entries for user {$uid} detected in recent audit log.\n";
}

// Also ensure a 'Permintaan Pencairan Diajukan' notification was created for this user
$notiStmt = $connect->prepare("SELECT id, title, message, created_at FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 1");
$notiStmt->bind_param('i', $uid); $notiStmt->execute(); $notiRes = $notiStmt->get_result();
$recentNoti = $notiRes && $notiRes->num_rows > 0 ? $notiRes->fetch_assoc() : null; $notiStmt->close();
if ($recentNoti && stripos($recentNoti['title'] ?? '', 'Permintaan Pencairan') !== false) {
    echo "OK: Found notification for user {$uid} (title={$recentNoti['title']}).\n";
} else {
    echo "FAIL: No 'Permintaan Pencairan' notification found for user {$uid}.\n";
    exit(4);
}

echo "Test passed.\n";
exit(0);
