<?php
/**
 * Simple runner to apply SQL file to the current DB using PDO.
 * CAUTION: This will execute statements in the provided SQL file. Run only if you have backed up.
 * Usage: php apply_tabungan_schema.php [--apply]
 */
require_once __DIR__ . '/../config/database.php';
$pdo = getConnectionOOP();
if (!$pdo) {
    echo "Cannot connect to DB - aborting\n";
    exit(1);
}
$path = __DIR__ . '/sql/001_tabungan_triggers_and_view.sql';
if (!file_exists($path)) {
    echo "SQL file not found: $path\n";
    exit(1);
}
$content = file_get_contents($path);
// Basic safety: ensure the file has the expected marker
if (strpos($content, 'trg_tab_keluar_before_insert') === false) {
    echo "SQL file seems not the expected one - aborting\n";
    exit(1);
}
if ($argc < 2 || $argv[1] !== '--apply') {
    echo "Preview of SQL file: (not applying). Run with --apply to execute.\n";
    echo "--- BEGIN PREVIEW ---\n";
    echo substr($content, 0, 2000);
    echo "\n--- END PREVIEW ---\n";
    echo "To apply: php apply_tabungan_schema.php --apply\n";
    exit(0);
}

// When user confirms, try to execute using a naive splitting on DELIMITER statements
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Remove DELIMITER lines to allow PDO->exec to parse
    $normalized = preg_replace('/DELIMITER\s+\$\$/i', '', $content);
    $normalized = str_replace('$$', ';', $normalized);
    // Split by semicolon - WARNING: naive; will fail on embedded semicolons in stored procedures
    $stmts = array_filter(array_map('trim', explode(';', $normalized)));
    foreach ($stmts as $s) {
        if (stripos($s, '-- EOF') !== false) break;
        if ($s === '') continue;
        $pdo->exec($s);
    }
    echo "SQL applied successfully.\n";
} catch (PDOException $e) {
    echo "Error applying SQL: " . $e->getMessage() . "\n";
    exit(1);
}
