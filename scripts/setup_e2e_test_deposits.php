<?php
/**
 * Quick setup: Insert test deposits to enable E2E testing
 */
require __DIR__ . '/../gas_web/flutter_api/connection.php';

$user_id = 97;
$jenis = 1;
$amount = 50000;

// Insert test topup (without status column)
$stmt = $connect->prepare("INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) VALUES (?, ?, ?, 'E2E test topup', NOW(), NOW())");
$stmt->bind_param('iid', $user_id, $jenis, $amount);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    echo "[OK] Test deposit created: user_id={$user_id}, jenis={$jenis}, amount={$amount}\n";
} else {
    echo "[FAIL] Could not create test deposit: " . $connect->error . "\n";
    exit(1);
}
?>
