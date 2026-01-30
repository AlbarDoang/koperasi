<?php
// Lightweight test harness to call admin_action.php from CLI without HTTP client.
// WARNING: This will modify DB if a pending record is found. Use on dev only.
chdir(__DIR__);
session_start();
$_SESSION['id_user'] = 1; // admin
$_SERVER['REQUEST_METHOD'] = 'POST';
require_once __DIR__ . '/../../config/db.php';
// find a pending application
$res = mysqli_query($con, "SELECT id FROM pinjaman_kredit WHERE status = 'pending' LIMIT 1");
if ($res && ($row = mysqli_fetch_assoc($res))) {
    $id = (int)$row['id'];
    echo "Found pending id: $id\n";
    $_POST['id'] = $id;
    $_POST['action'] = 'reject';
    $_POST['reason'] = 'unit test reject reason';
    ob_start();
    include 'admin_action.php';
    $out = ob_get_clean();
    echo "Response: $out\n";
    // fetch row status after action
    $r2 = mysqli_query($con, "SELECT id, status, keterangan, catatan_admin FROM pinjaman_kredit WHERE id = $id LIMIT 1");
    if ($r2 && ($app = mysqli_fetch_assoc($r2))) {
        echo "Post-update status: " . $app['status'] . "\n";
        echo "keterangan: " . ($app['keterangan'] ?? '') . "\n";
        echo "catatan_admin: " . ($app['catatan_admin'] ?? '') . "\n";
    }
} else {
    echo "No pending application found.\n";
}
