<?php
// Simple CLI test runner for flutter_api/add_penarikan.php
// Usage: php run_add_penarikan_test.php <id_pengguna> <jumlah> <id_petugas> [keterangan]

$argvCount = count($argv);
if ($argvCount < 4) {
    echo "Usage: php run_add_penarikan_test.php <id_pengguna> <jumlah> <id_petugas> [keterangan]\n";
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['id_pengguna'] = $argv[1];
$_POST['jumlah'] = $argv[2];
$_POST['id_petugas'] = $argv[3];
if (isset($argv[4])) $_POST['keterangan'] = $argv[4];

// capture output
ob_start();
include __DIR__ . '/../gas_web/flutter_api/add_penarikan.php';
$out = ob_get_clean();

echo "Response:\n" . $out . "\n";

