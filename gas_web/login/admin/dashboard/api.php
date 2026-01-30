<?php
// Simple API endpoint for dashboard real-time updates
// Requires admin user

require_once __DIR__ . '/../../middleware/AdminMiddleware.php';
AdminMiddleware::handle();

// Database connection
if (!isset($con)) {
    include __DIR__ . '/../../koneksi/config.php';
}

require_once __DIR__ . '/../../dashboard_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate');

$kpis = dashboard_collect_kpis($con);
$transactionSummary = dashboard_transaction_summary($con);
$chartPayload = dashboard_generate_chart_payload($con, 6);
$components = [];
if (function_exists('dashboard_total_koperasi_components')) {
    $components = dashboard_total_koperasi_components($con);
}

// ensure numeric-only values (no formatted currencies)
// (helpers already return numbers for kpis and chart payload)

echo json_encode([
    'kpis' => $kpis,
    'transaction_summary' => $transactionSummary,
    'chart' => $chartPayload,
    'components' => $components
], JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);

exit;