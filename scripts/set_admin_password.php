<?php
// One-off script to set admin password to adminGAS123 (SHA1), run from project root
require_once __DIR__ . '/../gas_web/login/koneksi/config.php';

$new = 'adminGAS123';
$hash = sha1($new);

$stmt = $con->prepare("UPDATE user SET password = ? WHERE username = ?");
$u = 'admin';
if (!$stmt) {
    echo "PREPARE_ERR: " . $con->error . PHP_EOL;
    exit(1);
}

$stmt->bind_param('ss', $hash, $u);
$ok = $stmt->execute();

echo "EXEC_OK:" . ($ok ? '1' : '0') . PHP_EOL;
echo "AFFECTED:" . $stmt->affected_rows . PHP_EOL;

$s = $con->prepare("SELECT password FROM user WHERE username = ? LIMIT 1");
$s->bind_param('s', $u);
$s->execute();
$res = $s->get_result();
$row = $res ? $res->fetch_assoc() : null;

echo "DB_HASH:" . ($row['password'] ?? 'NULL') . PHP_EOL;
echo "EXP_HASH:" . $hash . PHP_EOL;

// Done
