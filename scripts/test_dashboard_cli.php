<?php
require_once __DIR__ . '/../gas_web/config/database.php';
require_once __DIR__ . '/../gas_web/login/dashboard_helpers.php';

$con = getConnection();
if (!$con) {
    echo "DB connection failed\n";
    exit(1);
}

$kpis = dashboard_collect_kpis($con);
$tx = dashboard_transaction_summary($con);

echo "KPIs:\n";
foreach ($kpis as $k => $v) {
    echo " - $k: ";
    if (is_numeric($v)) echo $v; else echo json_encode($v);
    echo "\n";
}

echo "\nTransaction summary (deposit/withdraw/transfer):\n";
print_r($tx);

echo "\nChart payload last 3 days:\n";
$p = dashboard_generate_chart_payload($con, 3);
print_r($p);

echo "\nPending top-ups total: " . dashboard_total_pending_topups($con) . "\n";
echo "Total transactions: " . dashboard_total_transactions($con) . "\n";
echo "Total koperasi balance: " . dashboard_total_koperasi_balance($con) . "\n";

?>
