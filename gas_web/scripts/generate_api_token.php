<?php
// scripts/generate_api_token.php
// CLI utility to generate / revoke API tokens for pengguna.id
// Usage:
//   php generate_api_token.php --user=123 [--length=32] [--force] [--revoke]

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script is CLI only.\n");
    exit(1);
}

$options = getopt('', ['user:', 'length::', 'force::', 'revoke::']);
if (!isset($options['user'])) {
    fwrite(STDERR, "Usage: php generate_api_token.php --user=123 [--length=32] [--force] [--revoke]\n");
    exit(1);
}

$userId = (int)$options['user'];
$bytes = isset($options['length']) ? max(16, (int)$options['length']) : 32;
$force = isset($options['force']);
$revoke = isset($options['revoke']);

require_once __DIR__ . '/../config/database.php';
if (!isset($db) || !($db instanceof PDO)) {
    fwrite(STDERR, "Database connection (\$db) is not available.\n");
    exit(1);
}

try {
    // Check api_token column existence
    $colStmt = $db->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengguna' AND COLUMN_NAME = 'api_token' LIMIT 1");
    $colStmt->execute();
    $colRow = $colStmt->fetch(PDO::FETCH_ASSOC);
    if (!$colRow || (int)$colRow['cnt'] === 0) {
        fwrite(STDERR, "Table pengguna does not have column 'api_token'. Run the migration first.\n");
        exit(1);
    }

    // Fetch user
    $stmt = $db->prepare('SELECT id, api_token FROM pengguna WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        fwrite(STDERR, "User with id={$userId} not found.\n");
        exit(1);
    }

    if ($revoke) {
        $upd = $db->prepare('UPDATE pengguna SET api_token = NULL WHERE id = :id');
        $upd->bindValue(':id', $userId, PDO::PARAM_INT);
        $upd->execute();
        fwrite(STDOUT, "Revoked API token for user {$userId}\n");
        exit(0);
    }

    if (!$force && !empty($user['api_token'])) {
        fwrite(STDERR, "User already has an api_token. Use --force to overwrite or --revoke to clear.\n");
        fwrite(STDERR, "Existing token (first 8 chars): " . substr($user['api_token'],0,8) . "...\n");
        exit(1);
    }

    // Generate token
    $token = bin2hex(random_bytes($bytes)); // length = 2*bytes hex chars

    $upd = $db->prepare('UPDATE pengguna SET api_token = :token WHERE id = :id');
    $upd->bindValue(':token', $token, PDO::PARAM_STR);
    $upd->bindValue(':id', $userId, PDO::PARAM_INT);
    $upd->execute();

    fwrite(STDOUT, "API token for user {$userId}:\n{$token}\n");
    fwrite(STDOUT, "(Save this token securely; it will not be shown again.)\n");

    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(2);
} catch (Exception $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(3);
}
