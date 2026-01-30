<?php
header('Content-Type: application/json; charset=utf-8');
ob_start();
error_reporting(0);

include('../koneksi/db_auto.php');
include('../koneksi/fungsi_indotgl.php');
include('function_all.php');

function transfer_table_exists(PDO $connection, string $table): bool
{
	$stmt = $connection->prepare(
		'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
	);
	$stmt->execute([':table' => $table]);
	return (bool)$stmt->fetchColumn();
}

function transfer_column_exists(PDO $connection, string $table, string $column): bool
{
	$stmt = $connection->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
	$stmt->execute([':column' => $column]);
	return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function transfer_first_column(PDO $connection, string $table, array $candidates): ?string
{
	foreach ($candidates as $candidate) {
		if ($candidate && transfer_column_exists($connection, $table, $candidate)) {
			return $candidate;
		}
	}
	return null;
}

try {
	$tables = ['t_transfer', 'transfer'];
	$transferTable = null;
	foreach ($tables as $candidate) {
		if (transfer_table_exists($connection, $candidate)) {
			$transferTable = $candidate;
			break;
		}
	}

	if ($transferTable === null) {
		throw new RuntimeException('Tabel transfer tidak ditemukan.');
	}

	$idColumn = transfer_first_column($connection, $transferTable, ['id_transfer', 'id']);
	$noColumn = transfer_first_column($connection, $transferTable, ['no_transfer', 'kode_transfer']);
	if ($noColumn === null && $idColumn !== null) {
		$noColumn = $idColumn;
	}
	$senderIdColumn = transfer_first_column($connection, $transferTable, ['id_pengirim', 'dari_id_tabungan', 'id_tabungan_pengirim', 'id_pengguna_pengirim']);
	$senderNameColumn = transfer_first_column($connection, $transferTable, ['nama_pengirim', 'dari_nama', 'pengirim_nama']);
	$dateColumn = transfer_first_column($connection, $transferTable, ['tanggal', 'created_at', 'tgl_transfer']);
	$amountColumn = transfer_first_column($connection, $transferTable, ['nominal', 'jumlah', 'jumlah_transfer', 'total']);
	$receiverNameColumn = transfer_first_column($connection, $transferTable, ['nama_penerima', 'ke_nama', 'penerima_nama']);
	$receiverIdColumn = transfer_first_column($connection, $transferTable, ['id_penerima', 'ke_id_tabungan', 'id_tabungan_penerima', 'id_pengguna_penerima']);

	$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
	$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
	$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
	if ($length < 1) {
		$length = 10;
	}

	$whereClauses = [];
	$params = [];
	$searchValue = '';
	if (isset($_POST['search']['value'])) {
		$searchValue = trim($_POST['search']['value']);
	}
	if ($searchValue !== '') {
		$searchParts = [];
		$searchParam = '%' . $searchValue . '%';
		foreach ([$noColumn, $senderNameColumn, $receiverNameColumn, $senderIdColumn, $receiverIdColumn] as $column) {
			if ($column !== null) {
				$searchParts[] = "t.`{$column}` LIKE :search";
			}
		}
		if ($searchParts) {
			$whereClauses[] = '(' . implode(' OR ', $searchParts) . ')';
			$params[':search'] = $searchParam;
		}
	}

	$whereSql = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

	$orderColumns = [
		1 => $noColumn ? "t.`{$noColumn}`" : null,
		2 => $senderIdColumn ? "t.`{$senderIdColumn}`" : null,
		3 => $senderNameColumn ? "t.`{$senderNameColumn}`" : null,
		4 => $dateColumn ? "t.`{$dateColumn}`" : null,
		5 => $amountColumn ? "t.`{$amountColumn}`" : null,
	];

	$orderSql = '';
	if (isset($_POST['order'][0]['column'])) {
		$colIdx = intval($_POST['order'][0]['column']);
		$dir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';
		if (isset($orderColumns[$colIdx]) && $orderColumns[$colIdx] !== null) {
			$orderSql = ' ORDER BY ' . $orderColumns[$colIdx] . ' ' . $dir;
		}
	}
	if ($orderSql === '') {
		if ($dateColumn !== null) {
			$orderSql = " ORDER BY t.`{$dateColumn}` DESC";
		} elseif ($idColumn !== null) {
			$orderSql = " ORDER BY t.`{$idColumn}` DESC";
		}
	}

	$limitSql = ' LIMIT :start, :length';
	$tableSql = "`{$transferTable}`";

	$idExpr = $idColumn ? "t.`{$idColumn}`" : '0';
	$noExpr = $noColumn ? "t.`{$noColumn}`" : $idExpr;
	$senderIdExpr = $senderIdColumn ? "t.`{$senderIdColumn}`" : "''";
	$senderNameExpr = $senderNameColumn ? "t.`{$senderNameColumn}`" : "''";
	$dateExpr = $dateColumn ? "t.`{$dateColumn}`" : 'NULL';
	$amountExpr = $amountColumn ? "t.`{$amountColumn}`" : '0';
	$receiverNameExpr = $receiverNameColumn ? "t.`{$receiverNameColumn}`" : "''";
	$receiverIdExpr = $receiverIdColumn ? "t.`{$receiverIdColumn}`" : "''";

	$selectSql = "SELECT {$idExpr} AS row_id,
		{$noExpr} AS no_transfer,
		{$senderIdExpr} AS sender_id,
		{$senderNameExpr} AS sender_name,
		{$dateExpr} AS tanggal,
		{$amountExpr} AS nominal,
		{$receiverNameExpr} AS receiver_name,
		{$receiverIdExpr} AS receiver_id
	FROM {$tableSql} t {$whereSql}{$orderSql}{$limitSql}";

	$statement = $connection->prepare($selectSql);
	foreach ($params as $key => $value) {
		$statement->bindValue($key, $value);
	}
	$statement->bindValue(':start', $start, PDO::PARAM_INT);
	$statement->bindValue(':length', $length, PDO::PARAM_INT);
	$statement->execute();
	$rows = $statement->fetchAll(PDO::FETCH_ASSOC);

	$countTotalStmt = $connection->query("SELECT COUNT(*) FROM {$tableSql}");
	$recordsTotal = (int)$countTotalStmt->fetchColumn();

	if ($whereSql !== '') {
		$countFilteredStmt = $connection->prepare("SELECT COUNT(*) FROM {$tableSql} t {$whereSql}");
		foreach ($params as $key => $value) {
			$countFilteredStmt->bindValue($key, $value);
		}
		$countFilteredStmt->execute();
		$recordsFiltered = (int)$countFilteredStmt->fetchColumn();
	} else {
		$recordsFiltered = $recordsTotal;
	}

	$data = [];
	$no = $start + 1;
	foreach ($rows as $row) {
		$rawNoTransfer = isset($row['no_transfer']) ? (string)$row['no_transfer'] : '';
		$noTransfer = htmlspecialchars($rawNoTransfer);
		$noTransferUrl = rawurlencode($rawNoTransfer);
		$senderId = isset($row['sender_id']) ? htmlspecialchars((string)$row['sender_id']) : '';
		$senderName = isset($row['sender_name']) ? htmlspecialchars((string)$row['sender_name']) : '';
		$rawDate = $row['tanggal'] ?? '';
		$displayDate = '<div align="center">-</div>';
		if (!empty($rawDate)) {
			$justDate = substr((string)$rawDate, 0, 10);
			if ($justDate && $justDate !== '0000-00-00') {
				$displayDate = '<div align="center">' . tgl_indo($justDate) . '</div>';
			}
		}
		$amountValue = isset($row['nominal']) ? (float)$row['nominal'] : 0;
		$amountDisplay = 'Rp ' . number_format($amountValue, 0, ',', '.');

		$actionHtml = '<div align="center">'
			. '<a href="#myModal" id="custId" data-bs-toggle="modal" data-id="' . $noTransfer . '">'
			. '<button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Transaksi"><i class="fe fe-alert-octagon icon-lg"></i></button></a>'
			. '&nbsp;'
			. '<a href="kwitansi.php?no_transfer=' . $noTransferUrl . '"><button type="button" class="btn btn-success btn-sm btn-icon" title="Cetak Kwitansi Tabungan Masuk"><i class="fe fe-file-text"></i></button></a>'
			. '</div>';

		$data[] = [
			'<div align="center">' . ($no++) . '</div>',
			'<div align="center">' . $noTransfer . '</div>',
			'<div align="center">' . $senderId . '</div>',
			$senderName,
			$displayDate,
			$amountDisplay,
			$actionHtml,
		];
	}

	$output = [
		'draw' => $draw,
		'recordsTotal' => $recordsTotal,
		'recordsFiltered' => $recordsFiltered,
		'data' => $data,
	];

	$json = json_encode($output, JSON_UNESCAPED_UNICODE);
	if ($json === false) {
		$json = json_encode(['error' => json_last_error_msg()]);
	}
	ob_end_clean();
	echo $json;
	exit;
} catch (Throwable $e) {
	ob_end_clean();
	$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;
	echo json_encode([
		'draw' => $draw,
		'recordsTotal' => 0,
		'recordsFiltered' => 0,
		'data' => [],
		'error' => 'Server error'
	]);
	exit;
}
?>