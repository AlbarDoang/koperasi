<?php
// Simulate admin session and GET params then include verification_image.php to trigger its behavior (CLI test)
session_start();
$_SESSION['id_user'] = 1;
$_SESSION['akses'] = 'admin';
// Choose a test user id that exists in DB (from previous inspect)
$_GET['user_id'] = 1;
// type can be 'ktp' or 'selfie'
$_GET['type'] = 'ktp';
// Run the proxy
require_once __DIR__ . '/../gas_web/login/admin/verification_image.php';
echo "done\n";
