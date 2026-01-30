<?php
require_once __DIR__ . '/gas_web/config/database.php';

$con = getConnection();
if (!$con) {
    die("DB connection failed");
}

// WARNING: This script updates 'pengguna.saldo' directly without creating ledger entries.
// Use only for emergency/migration purposes. For production, use ledger inserts or the
// admin UI which creates ledger rows to maintain audit and balance consistency.

// Show counts and sample before
$result = $con->query("SELECT COUNT(*) as cnt, COALESCE(SUM(saldo),0) as total_saldo FROM pengguna");
$before = $result->fetch_assoc();

// Backup: write previous saldo totals to a file with timestamp
$backupFile = __DIR__ . '/backup_saldo_' . date('Ymd_His') . '.sql';
$resultRows = $con->query("SELECT id, saldo FROM pengguna");
$fh = fopen($backupFile, 'w');
if ($fh) {
    while ($r = $resultRows->fetch_assoc()) {
        fwrite($fh, "UPDATE pengguna SET saldo = " . (float)$r['saldo'] . " WHERE id = " . intval($r['id']) . ";\n");
    }
    fclose($fh);
}

// Set saldo to 0 for all siswa
$update = $con->query("UPDATE pengguna SET saldo = 0");

// Show after
$result2 = $con->query("SELECT COUNT(*) as cnt, COALESCE(SUM(saldo),0) as total_saldo FROM pengguna");
$after = $result2->fetch_assoc();

echo "<h2>Set Saldo to 0 - Result</h2>";
echo "<p>Rows affected: " . ($con->affected_rows) . "</p>";
echo "<h3>Before:</h3>";
echo "<pre>" . print_r($before, true) . "</pre>";
echo "<h3>After:</h3>";
echo "<pre>" . print_r($after, true) . "</pre>";
echo "<p>Backup of previous saldo saved to: " . basename($backupFile) . "</p>";

$con->close();
?>