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
$res = approval_apply_action($con, $schema, $found, 'reject', 1, 'Unit test rejection');
print_r($res);

// Check notifikasi table for recent pinjaman-related entries for the user
$uid = null;
if (isset($found['member_value'])) {
    $mc = approval_seek_member($con, $schema, $found['member_value']);
    if ($mc && isset($mc['data'])) {
        foreach (['id_pengguna','id','id_user','id_pengguna'] as $k) { if (isset($mc['data'][$k])) { $uid = (int)$mc['data'][$k]; break; } }
    }
}
if ($uid) {
    $stmt = $con->prepare("SELECT id, id_pengguna, type, title, message, data, read_status, created_at FROM notifikasi WHERE id_pengguna = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    echo "Recent notifikasi for user $uid:\n";
    print_r($rows);
} else {
    echo "Could not determine user id from pending row\n";
}

?>
