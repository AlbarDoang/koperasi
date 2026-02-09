<?php
require_once __DIR__ . '/../gas_web/flutter_api/connection.php';
include __DIR__ . '/../gas_web/login/function/ledger_helpers.php';
$ok = insert_ledger_masuk($connect, 95, 50000, 'Test deposit', 1, null);
echo json_encode(['ok'=>$ok]);
