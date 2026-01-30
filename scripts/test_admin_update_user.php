<?php
// Quick manual test script for admin_update_user.php
// Usage: php test_admin_update_user.php <user_id>
// This script will print status_akun before and after calling admin_update_user.php

if ($argc < 2) {
    echo "Usage: php test_admin_update_user.php <user_id>\n";
    exit(1);
}
$id = intval($argv[1]);
require_once __DIR__ . '/../gas_web/connection.php';

function getStatus($conn, $id) {
    $q = $conn->prepare('SELECT id, nama_lengkap, no_hp, status_akun, approved_at, is_active FROM pengguna WHERE id = ? LIMIT 1');
    $q->bind_param('i', $id);
    $q->execute();
    $r = $q->get_result();
    if ($r && $r->num_rows) return $r->fetch_assoc();
    return null;
}

$before = getStatus($connect, $id);
if (!$before) { echo "User not found: $id\n"; exit(1); }
print_r(['before' => $before]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/gas/gas_web/flutter_api/admin_update_user.php');
curl_setopt($ch, CURLOPT_POST, 1);
// Intentional attempt to change status -> should be rejected
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'id' => $id,
    'nama_lengkap' => $before['nama_lengkap'] . ' TEST',
    'no_hp' => $before['no_hp'],
    'status_akun' => 'rejected'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp = curl_exec($ch);
curl_close($ch);
echo "Response when attempting status change:\n" . $resp . "\n";

// Now a valid update without status
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1/gas/gas_web/flutter_api/admin_update_user.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'id' => $id,
    'nama_lengkap' => $before['nama_lengkap'] . ' UPDATED',
    'no_hp' => $before['no_hp']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$resp2 = curl_exec($ch);
curl_close($ch);
echo "Response when updating profile:\n" . $resp2 . "\n";

$after = getStatus($connect, $id);
print_r(['after' => $after]);

// Verify status didn't change
if ($before['status_akun'] === $after['status_akun']) {
    echo "Status unchanged ✅\n";
} else {
    echo "Status CHANGED ⚠️ before={$before['status_akun']} after={$after['status_akun']}\n";
}

?>