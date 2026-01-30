<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
$uid = 97;
$amount = 5000;
$sql = sprintf("INSERT INTO pinjaman_biasa (id_pengguna, jumlah_pinjaman, tenor, tujuan_penggunaan, status, created_at) VALUES (%d, %d, 3, 'test approval notif', 'pending', NOW())", $uid, $amount);
if ($connect->query($sql)) { echo "Inserted id=" . $connect->insert_id . "\n"; } else { echo "Insert failed: " . $connect->error . "\n"; }
