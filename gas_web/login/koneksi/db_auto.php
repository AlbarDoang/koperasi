<?php
// Backward-compatible PDO connection wrapper
// `db.php` sets `$database` from centralized config
try {
	include_once __DIR__ . '/db.php';
} catch (Throwable $e) {
	// If include fails, ensure $database is undefined but do not fatal
}

$username = defined('DB_USER') ? DB_USER : 'root';
$password = defined('DB_PASS') ? DB_PASS : '';
$dsn = 'mysql:host=localhost;dbname=' . (isset($database) ? $database : (defined('DB_NAME') ? DB_NAME : '')) . ';charset=utf8';
try {
	$connection = new PDO($dsn, $username, $password, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	]);
} catch (Throwable $e) {
	// Do not expose DB errors to client; log to file for local debugging
	@file_put_contents(__DIR__ . '/../fetch_db_error.log', date('c') . " PDO connection error: " . $e->getMessage() . "\n", FILE_APPEND);
	$connection = false;
}
