<?php
require_once __DIR__ . '/../gas_web/config/database.php';
require_once __DIR__ . '/../gas_web/login/approval_helpers.php';

$schemaRes = approval_get_schema($con);
if (isset($schemaRes['error'])) { var_export($schemaRes); exit(1);} 
$schema = $schemaRes['schema'];
$rowsRes = approval_fetch_rows($con);
if (isset($rowsRes['error'])) { var_export($rowsRes); exit(1);} 
$found = null;
foreach ($rowsRes['rows'] as $r) {
    if ($r['status'] === 'pending') { $found = $r; break; }
}
if (!$found) { echo "No pending rows found\n"; exit(0); }
print_r($found);
$res = approval_apply_action($con, $schema, $found, 'approve', 1, null);
print_r($res);
