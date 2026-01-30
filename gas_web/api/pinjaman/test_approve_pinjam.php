<?php
// Temporary test: simulate admin approval for a specific pending id in `pinjaman`
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../login/approval_helpers.php';

$testId = $argv[1] ?? '11';
$adminId = 1; // adjust as needed

$schemaResult = approval_get_schema($con);
var_export($schemaResult);
if (isset($schemaResult['error'])) exit(1);
$schema = $schemaResult['schema'];
$pendingRow = approval_fetch_pending_row($con, $schema, $testId);
var_export($pendingRow);
if (!$pendingRow) exit(2);

$res = approval_apply_action($con, $schema, $pendingRow, 'approve', $adminId, null);
var_export($res);

?>