<?php
/**
 * Reconcile Saldo Script
 * - Computes saldo from ledger (transaksi / tabungan) and updates pengguna.saldo
 * - Requires a secret token for non-CLI usage to prevent accidental execution
 * - Supports dry_run to only report changes without updating DB
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once('connection.php');
require_once('helpers.php');

// Optional: set a secret token here (change to a long, random string before use)
$RECONCILE_SECRET = 'CHANGE_ME_PLEASE';

$conn = getConnection();
if (!$conn) {
	http_response_code(500);
	echo json_encode(['status' => false, 'message' => 'Koneksi database gagal']);
	exit();
}

// If not CLI, require secret token
$dryRun = (isset($_GET['dry_run']) && ($_GET['dry_run'] == '1' || strtolower($_GET['dry_run']) == 'true')) ? true : false;
$isCLI = (PHP_SAPI === 'cli');
$suppliedSecret = isset($_GET['secret']) ? $_GET['secret'] : (isset($_POST['secret']) ? $_POST['secret'] : null);
if (!$isCLI) {
	if (empty($suppliedSecret) || $suppliedSecret !== $RECONCILE_SECRET) {
		http_response_code(403);
		echo json_encode(['status' => false, 'message' => 'Unauthorized. Supply valid secret.']);
		exit();
	}
}

$batchSize = 500;
$offset = 0;
$changed = 0;
$processed = 0;
$errors = [];
$logFile = __DIR__ . '/logs/reconcile.log';

// Summary placeholders
$rows = [];

// Optional single user support
$single_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : null);
$single_id_tabungan = isset($_GET['id_tabungan']) ? $_GET['id_tabungan'] : (isset($_POST['id_tabungan']) ? $_POST['id_tabungan'] : null);

// If running via CLI, parse simple key=value args (php script.php id=1 dry_run=1)
if ($isCLI && !empty($argv) && count($argv) > 1) {
	foreach (array_slice($argv, 1) as $arg) {
		if (strpos($arg, '=') !== false) {
			list($k, $v) = explode('=', $arg, 2);
			$k = trim($k);
			$v = trim($v);
			if ($k === 'id') $single_id = intval($v);
			if ($k === 'id_tabungan') $single_id_tabungan = $v;
			if ($k === 'dry_run' || $k === 'dryrun') $dryRun = ($v == '1' || strtolower($v) === 'true');
		}
	}
}

// $q will be created per-loop iteration so offset works for batch runs; for single runs we'll keep the specific WHERE

// Batch loop begins here; will stop when single user or no more rows
while (true) {
	// Build query each loop (to reflect offset in batch runs)
	if ($single_id !== null) {
		// Select all columns (table may have different column names); we'll extract known columns dynamically
		$q = "SELECT * FROM pengguna WHERE id='" . intval($single_id) . "' LIMIT 1";
	} else if (!empty($single_id_tabungan)) {
		$q = "SELECT * FROM pengguna WHERE id_tabungan='" . mysqli_real_escape_string($conn, $single_id_tabungan) . "' LIMIT 1";
	} else {
		$q = "SELECT * FROM pengguna LIMIT $batchSize OFFSET $offset";
	}
	$res = mysqli_query($conn, $q);
	if (!$res) break;
	if (mysqli_num_rows($res) === 0) break;

	while ($row = mysqli_fetch_assoc($res)) {
		$processed++;
		// Extract commonly used id fields safely
		$id = $row['id'] ?? ($row['id_pengguna'] ?? ($row['id_pengguna'] ?? null));
		$id_tabungan_val = $row['id_tabungan'] ?? ($row['nis'] ?? ($row['id_tabungan_raw'] ?? ''));
		$saldo_db = intval($row['saldo'] ?? ($row['saldo_db'] ?? 0));

		// Compute from transaksi -> prefer id_tabungan
		$saldo_calculated = null;
		$calc_source = null;
		// Use safe helper for transaction summation
		$trxSum = null;
		if (!empty($id_tabungan_val) && function_exists('safe_sum_transaksi')) {
			$trxSum = safe_sum_transaksi($conn, $id_tabungan_val);
			if ($trxSum !== null) {
				$saldo_calculated = floatval($trxSum['saldo']);
				$calc_source = 'transaksi';
			}
		}
		// Count transactions and get last trx (only when id_tabungan exists and table transaksi exists)
		$count_transaksi = 0;
		$last_transaksi = null;
		if (!empty($id_tabungan_val)) {
			$check_transaksi_table = mysqli_query($conn, "SHOW TABLES LIKE 'transaksi'");
			if ($check_transaksi_table && mysqli_num_rows($check_transaksi_table) > 0) {
				$count_q = "SELECT COUNT(*) as cnt FROM transaksi WHERE id_tabungan='" . $conn->real_escape_string($id_tabungan_val) . "'";
				$res_count = mysqli_query($conn, $count_q);
			$count_transaksi = 0;
			if ($res_count && mysqli_num_rows($res_count) > 0) {
				$row_cnt = mysqli_fetch_assoc($res_count);
				$count_transaksi = intval($row_cnt['cnt']);
			}
				$sql_last = "SELECT * FROM transaksi WHERE id_tabungan='" . $conn->real_escape_string($id_tabungan_val) . "' ORDER BY id_transaksi DESC LIMIT 1";
				$rlast = mysqli_query($conn, $sql_last);
				if ($rlast && mysqli_num_rows($rlast) > 0) {
					$last_transaksi = mysqli_fetch_assoc($rlast);
				}
			}
		}

		// Fallback: tabungan table
		if ($saldo_calculated === null) {
			$check_tabungan = mysqli_query($conn, "SHOW TABLES LIKE 'tabungan'");
			if ($check_tabungan && mysqli_num_rows($check_tabungan) > 0) {
				$sql_tabungan = "SELECT COALESCE(SUM(CASE WHEN jenis='masuk' THEN jumlah ELSE 0 END), 0) as total_masuk, COALESCE(SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END), 0) as total_keluar FROM tabungan WHERE ";
				if (!empty($row['id_pengguna'])) {
					$sql_tabungan .= "id_pengguna='" . mysqli_real_escape_string($conn, $row['id_pengguna']) . "'";
				} else if (!empty($row['id'])) {
					$check_idPengguna = mysqli_query($conn, "SHOW COLUMNS FROM tabungan LIKE 'id_pengguna'");
					if ($check_idPengguna && mysqli_num_rows($check_idPengguna) > 0) {
						$sql_tabungan .= "id_pengguna='" . mysqli_real_escape_string($conn, $row['id']) . "'";
					} else {
						$sql_tabungan .= "id_tabungan='" . mysqli_real_escape_string($conn, $id_tabungan_val) . "'";
					}
				} else {
					$sql_tabungan .= "id_tabungan='" . mysqli_real_escape_string($conn, $id_tabungan_val) . "'";
				}
				$res_tabungan = mysqli_query($conn, $sql_tabungan);
				if ($res_tabungan && mysqli_num_rows($res_tabungan) > 0) {
					$row_tabungan = mysqli_fetch_assoc($res_tabungan);
					$saldo_calculated = floatval($row_tabungan['total_masuk']) - floatval($row_tabungan['total_keluar']);
					$calc_source = 'tabungan';
				}
			}
		}

		// Fallback to DB saldo if still null
		if ($saldo_calculated === null) {
			$saldo_calculated = $saldo_db;
			$calc_source = 'pengguna.saldo';
		}

		// Update if mismatch (and not dry run)
		if (intval($saldo_calculated) !== intval($saldo_db)) {
			$action = 'no_action';
			if ($dryRun) {
				$action = 'dry_run_prediction';
			} else {
				$upd = mysqli_query($conn, "UPDATE pengguna SET saldo='" . intval($saldo_calculated) . "' WHERE id='" . intval($id) . "' LIMIT 1");
				if ($upd) {
					$changed++;
					$action = 'updated';
				} else {
					$errors[] = "Gagal update id=$id: " . mysqli_error($conn);
					$action = 'error';
				}
			}
		} else {
			$action = 'no_change';
		}

		$rowLog = ['ts' => date('c'), 'id' => $id, 'id_tabungan' => $id_tabungan_val, 'saldo_db' => $saldo_db, 'saldo_calc' => $saldo_calculated, 'source' => $calc_source, 'action' => $action, 'count_transaksi' => $count_transaksi ?? 0, 'last_transaksi' => $last_transaksi ?? null];
		@file_put_contents($logFile, json_encode($rowLog) . PHP_EOL, FILE_APPEND | LOCK_EX);
		$rows[] = $rowLog;
	}

	// If we ran a single user query, don't loop over all users
	if ($single_id !== null || !empty($single_id_tabungan)) {
		break;
	}

	$offset += $batchSize;
}

$summary = ['status' => true, 'processed' => $processed, 'changed' => $changed, 'errors' => $errors, 'rows' => $rows];
echo json_encode($summary, JSON_PRETTY_PRINT);
exit();


