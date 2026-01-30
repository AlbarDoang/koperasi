<?php
// flutter_api/token_helper.php
// Small helper functions for api_token management and lookup

declare(strict_types=1);

/**
 * Check whether `pengguna.api_token` column exists in current database
 * @param PDO $db
 * @return bool
 */
function pengguna_has_api_token_column(PDO $db): bool {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengguna' AND COLUMN_NAME = 'api_token' LIMIT 1");
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return (bool)($r && (int)$r['cnt'] > 0);
}

/**
 * Find user id from api token. Returns int user id or null if not found or column missing.
 * @param PDO $db
 * @param string $token
 * @return int|null
 */
function find_user_id_by_api_token(PDO $db, string $token): ?int {
    if (trim($token) === '') return null;
    if (!pengguna_has_api_token_column($db)) return null;
    $stmt = $db->prepare("SELECT id FROM pengguna WHERE api_token = :token LIMIT 1");
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['id'])) return (int)$row['id'];
    return null;
}

/**
 * Generate a cryptographically secure API token (hex string). Default is 32 bytes -> 64 hex chars.
 * @param int $bytes
 * @return string
 */
function generate_api_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}
