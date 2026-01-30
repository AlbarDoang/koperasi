<?php
// scripts/generate_api_token.php
// CLI utility to generate and store an api_token for a pengguna (user)
// Usage: php scripts/generate_api_token.php --user=1

declare(strict_types=1);

// Ensure script is run via CLI only
if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Error: This script must be run from the command line.\n");
    exit(1);
}

$options = getopt('', ['user:','help']);
if (isset($options['help'])) {
    echo "Usage: php scripts/generate_api_token.php --user=<id>\n";
    exit(0);
}

if (empty($options['user'])) {
    fwrite(STDERR, "Error: Missing --user parameter.\nUsage: php scripts/generate_api_token.php --user=<id>\n");
    exit(2);
}

$userId = $options['user'];
if (!is_numeric($userId) || (int)$userId <= 0) {
    fwrite(STDERR, "Error: --user must be a positive integer.\n");
    exit(2);
}
$userId = (int)$userId;

// Load DB connection (expects $con mysqli procedural)
require_once __DIR__ . '/../gas_web/config/database.php';
if (!isset($con) || !($con instanceof mysqli)) {
    fwrite(STDERR, "Error: Database connection (\$con) is not available. Check config/database.php\n");
    exit(3);
}

// Check user exists
$check = mysqli_prepare($con, 'SELECT id FROM pengguna WHERE id = ? LIMIT 1');
if ($check === false) {
    fwrite(STDERR, "Error: Preparing user lookup failed: " . mysqli_error($con) . "\n");
    exit(3);
}
mysqli_stmt_bind_param($check, 'i', $userId);
if (!mysqli_stmt_execute($check)) {
    fwrite(STDERR, "Error: Executing user lookup failed: " . mysqli_stmt_error($check) . "\n");
    mysqli_stmt_close($check);
    exit(3);
}
mysqli_stmt_bind_result($check, $foundId);
mysqli_stmt_fetch($check);
mysqli_stmt_close($check);

if (empty($foundId) || !is_numeric($foundId)) {
    fwrite(STDERR, "Error: User with id={$userId} not found.\n");
    exit(4);
}

// Generate secure token (40 hex chars)
try {
    $token = bin2hex(random_bytes(20));
} catch (Exception $e) {
    fwrite(STDERR, "Error: Failed to generate secure token: " . $e->getMessage() . "\n");
    exit(5);
}

// Update pengguna.api_token
$up = mysqli_prepare($con, 'UPDATE pengguna SET api_token = ? WHERE id = ?');
if ($up === false) {
    fwrite(STDERR, "Error: Preparing update failed: " . mysqli_error($con) . "\n");
    exit(3);
}
mysqli_stmt_bind_param($up, 'si', $token, $userId);
if (!mysqli_stmt_execute($up)) {
    fwrite(STDERR, "Error: Executing update failed: " . mysqli_stmt_error($up) . "\n");
    mysqli_stmt_close($up);
    exit(3);
}
$affected = mysqli_stmt_affected_rows($up);
mysqli_stmt_close($up);

if ($affected === 0) {
    // Could be that token was identical or no change; still consider success if user exists
    // We'll still output token so admin can use it
    echo "Generated API token for user {$userId}:\n" . $token . "\n";
    exit(0);
}

// Success
echo "Generated API token for user {$userId}:\n" . $token . "\n";
exit(0);
