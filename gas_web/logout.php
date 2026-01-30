<?php
session_start();
session_destroy();
// Dynamic path detection untuk redirect
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
header('Location: ' . $protocol . '://' . $host . $base_path . '/index.php');
exit();
?>