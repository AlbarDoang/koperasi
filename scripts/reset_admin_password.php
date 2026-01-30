<?php
require_once __DIR__ . '/../gas_web/login/koneksi/config.php';
// Find an admin user
$res = $con->query("SELECT id, username FROM user WHERE hak_akses='admin' LIMIT 1");
if (!$res || $res->num_rows === 0) {
    echo "No admin user found\n";
    exit(1);
}
$row = $res->fetch_assoc();
$id = $row['id'];
$username = $row['username'];
$newpass = 'Passw0rd123';
$hash = sha1($newpass);
$update = $con->prepare("UPDATE user SET password = ? WHERE id = ?");
$update->bind_param('si', $hash, $id);
$ok = $update->execute();
if ($ok) {
    echo "Updated admin $username (id=$id) password -> $newpass\n";
    exit(0);
} else {
    echo "Failed to update password: " . $con->error . "\n";
    exit(1);
}
