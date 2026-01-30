<?php
// scripts/migrate_normalize_pinjaman.php
// CLI helper to normalize legacy pinjaman columns into a consistent schema.
// Usage: php scripts/migrate_normalize_pinjaman.php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../gas_web/config/database.php';
if (!isset($con) || !($con instanceof mysqli)) {
    fwrite(STDERR, "Database connection (\$con) is not available. Check config/database.php\n");
    exit(2);
}

function q($con, $sql) {
    $res = mysqli_query($con, $sql);
    if ($res === false) {
        fwrite(STDERR, "SQL error: " . mysqli_error($con) . "\nSQL: " . $sql . "\n");
        return false;
    }
    return $res;
}

// Inspect columns
$cols = [];
$res = q($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pinjaman'");
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) $cols[$r['COLUMN_NAME']] = true;
    mysqli_free_result($res);
}

fwrite(STDOUT, "Detected columns: " . implode(', ', array_keys($cols)) . "\n");

// 1) Normalize amount column: prefer `jumlah_pinjaman`, fallback from `jumlah`
if (!isset($cols['jumlah_pinjaman']) && isset($cols['jumlah'])) {
    fwrite(STDOUT, "Renaming column `jumlah` -> `jumlah_pinjaman`...\n");
    $sql = "ALTER TABLE pinjaman CHANGE COLUMN `jumlah` `jumlah_pinjaman` BIGINT NULL";
    if (q($con, $sql) === false) exit(3);
    fwrite(STDOUT, "Rename succeeded.\n");
    $cols['jumlah_pinjaman'] = true;
    unset($cols['jumlah']);
}

// If both exist, copy data where jumlah_pinjaman is null/0
if (isset($cols['jumlah_pinjaman']) && isset($cols['jumlah'])) {
    fwrite(STDOUT, "Copying values from `jumlah` -> `jumlah_pinjaman` where empty...\n");
    $sql = "UPDATE pinjaman SET jumlah_pinjaman = jumlah WHERE (jumlah_pinjaman IS NULL OR jumlah_pinjaman = 0) AND (jumlah IS NOT NULL AND jumlah <> 0)";
    q($con, $sql);
    fwrite(STDOUT, "Copy completed. Affected rows: " . mysqli_affected_rows($con) . "\n");
}

// 2) Normalize status column: prefer `status`, fallback from `status_pinjaman`
if (!isset($cols['status']) && isset($cols['status_pinjaman'])) {
    fwrite(STDOUT, "Renaming column `status_pinjaman` -> `status`...\n");
    $sql = "ALTER TABLE pinjaman CHANGE COLUMN `status_pinjaman` `status` VARCHAR(32) NULL";
    if (q($con, $sql) === false) exit(4);
    fwrite(STDOUT, "Rename succeeded.\n");
    $cols['status'] = true;
    unset($cols['status_pinjaman']);
}

// If both exist, copy where status is empty
if (isset($cols['status']) && isset($cols['status_pinjaman'])) {
    fwrite(STDOUT, "Copying values from `status_pinjaman` -> `status` where empty...\n");
    $sql = "UPDATE pinjaman SET status = status_pinjaman WHERE (status IS NULL OR TRIM(status) = '') AND (status_pinjaman IS NOT NULL AND TRIM(status_pinjaman) <> '')";
    q($con, $sql);
    fwrite(STDOUT, "Copy completed. Affected rows: " . mysqli_affected_rows($con) . "\n");
}

// 3) Ensure no NULL/empty status: set to 'pending'
fwrite(STDOUT, "Setting empty status to 'pending' for legacy rows...\n");
$res = q($con, "UPDATE pinjaman SET status = 'pending' WHERE status IS NULL OR TRIM(status) = ''");
if ($res !== false) fwrite(STDOUT, "Updated rows: " . mysqli_affected_rows($con) . "\n");

// 4) If jumlah_pinjaman exists but wrongly typed (e.g., VARCHAR), optionally cast/convert - we will just print the column type
$colTypeRes = q($con, "SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pinjaman' AND COLUMN_NAME IN ('jumlah_pinjaman','jumlah')");
if ($colTypeRes) {
    while ($r = mysqli_fetch_assoc($colTypeRes)) {
        fwrite(STDOUT, "Column {$r['COLUMN_NAME']} type: {$r['COLUMN_TYPE']}\n");
    }
    mysqli_free_result($colTypeRes);
}

// 5) Report summary
$res = q($con, "SELECT COUNT(*) as cnt FROM pinjaman");
$row = mysqli_fetch_assoc($res);
fwrite(STDOUT, "Total rows in pinjaman: " . ($row['cnt'] ?? 'unknown') . "\n");
$res2 = q($con, "SELECT COUNT(*) as cnt FROM pinjaman WHERE status = 'pending'");
$row2 = mysqli_fetch_assoc($res2);
fwrite(STDOUT, "Pending rows after migration: " . ($row2['cnt'] ?? 'unknown') . "\n");

fwrite(STDOUT, "Done. NOTE: This script performs schema changes if necessary. Review the output and ensure you have a DB backup before running.\n");

exit(0);
