<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once 'helpers.php';

$argv_vals = $_SERVER['argv'] ?? [];
$options = [];
foreach (array_slice($argv_vals, 1) as $a) {
    if (strpos($a, '=') === false) continue;
    list($k, $v) = explode('=', $a, 2);
    $options[trim($k)] = trim($v);
}

$id = isset($options['id']) ? intval($options['id']) : 0;
$dry = isset($options['dry_run']) ? ($options['dry_run'] === '1' || strtolower($options['dry_run']) === 'true') : true;

if ($id <= 0) {
    echo json_encode(['status'=>false, 'message'=>'Missing id parameter.']);
    exit(1);
}

$conn = getConnection();
if (!$conn) { echo json_encode(['status'=>false,'message'=>'DB conn failed']); exit(1); }

// Fetch user
$res = $conn->query("SELECT * FROM pengguna WHERE id='" . intval($id) . "' LIMIT 1");
if (!$res || $res->num_rows === 0) { echo json_encode(['status'=>false, 'message'=>'Pengguna not found']); exit(1); }
$row = $res->fetch_assoc();

// Backup row to logs
$logDir = __DIR__ . '/logs';
if (!file_exists($logDir)) mkdir($logDir, 0755, true);
$backupFile = $logDir . '/reconcile_backup_' . $id . '_' . date('Ymd_His') . '.json';
file_put_contents($backupFile, json_encode(['ts'=>date('c'), 'user'=>$row], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Compute ledger sums
$id_tabungan = $row['id_tabungan'] ?? ($row['nis'] ?? '');
$ledger_sum = 0.0;
$calc_source = 'none';
$count_trx = 0;
$last_trx = null;

// Prefer transaksi if present
$check_trx = $conn->query("SHOW TABLES LIKE 'transaksi'");
if ($check_trx && $check_trx->num_rows > 0) {
    $safe = $conn->real_escape_string($row['id_anggota'] ?? $row['id'] ?? '');
    // Use id_anggota if present in transaksi
    $sumQ = "SELECT SUM(CASE WHEN jenis_transaksi IN ('setoran','transfer_masuk') THEN jumlah ELSE 0 END) as masuk, SUM(CASE WHEN jenis_transaksi IN ('penarikan','transfer_keluar') THEN jumlah ELSE 0 END) as keluar FROM transaksi WHERE id_anggota='" . $safe . "'";
    $r = $conn->query($sumQ);
    if ($r && $r->num_rows > 0) {
        $arr = $r->fetch_assoc();
        $masuk = floatval($arr['masuk'] ?? 0);
        $keluar = floatval($arr['keluar'] ?? 0);
        $ledger_sum = $masuk - $keluar;
        $calc_source = 'transaksi';

        $rCnt = $conn->query("SELECT COUNT(*) as cnt FROM transaksi WHERE id_anggota='".$safe."'");
        if ($rCnt && $rCnt->num_rows > 0) $count_trx = intval($rCnt->fetch_assoc()['cnt']);
        $rLast = $conn->query("SELECT * FROM transaksi WHERE id_anggota='".$safe."' ORDER BY id_transaksi DESC LIMIT 1");
        if ($rLast && $rLast->num_rows > 0) $last_trx = $rLast->fetch_assoc();
    }
}

// If no trx and tabungan exists compute tabungan
if ($ledger_sum == 0.0) {
    $check_tab = $conn->query("SHOW TABLES LIKE 'tabungan'");
    if ($check_tab && $check_tab->num_rows > 0) {
        $safeTab = $conn->real_escape_string($row['id_anggota'] ?? ($row['id'] ?? $id_tabungan));
        $sql_tab = "SELECT SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE 0 END) as masuk, SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END) as keluar FROM tabungan WHERE id_anggota='".$safeTab."'";
        $r = $conn->query($sql_tab);
        if ($r && $r->num_rows > 0) {
            $arr = $r->fetch_assoc();
            $ledger_sum = floatval($arr['masuk'] ?? 0) - floatval($arr['keluar'] ?? 0);
            $calc_source = 'tabungan';
        }
    }
}

// If ledger_sum still zero and no transactions, and pengguna.saldo>0, we'll set saldo to 0 as per your choice
$currentSaldo = intval($row['saldo'] ?? 0);

$action = 'noop';
$applied = false;
if ($currentSaldo !== intval($ledger_sum)) {
    if ($ledger_sum === 0.0) {
        $action = 'set_zero';
        if (!$dry) {
            $upd = $conn->query("UPDATE pengguna SET saldo='0' WHERE id='" . intval($id) . "' LIMIT 1");
            if ($upd) {
                $applied = true;
            }
        }
    } else {
        $action = 'sync_to_ledger';
        if (!$dry) {
            $upd = $conn->query("UPDATE pengguna SET saldo='" . intval($ledger_sum) . "' WHERE id='" . intval($id) . "' LIMIT 1");
            if ($upd) {
                $applied = true;
            }
        }
    }
}

// Log change
$changeLog = [
    'ts' => date('c'),
    'id'=>$id,
    'current_saldo'=>$currentSaldo,
    'ledger_sum'=>intval($ledger_sum),
    'calc_source'=>$calc_source,
    'action'=>$action,
    'applied'=>$applied,
    'dry_run'=>$dry
];
file_put_contents($logDir . '/reconcile_changes.log', json_encode($changeLog) . PHP_EOL, FILE_APPEND | LOCK_EX);

echo json_encode($changeLog, JSON_PRETTY_PRINT);

