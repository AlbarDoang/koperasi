<?php
header('Content-Type: application/json; charset=utf-8');
ob_start();
error_reporting(0);

include('../koneksi/db_auto.php');
include('../koneksi/fungsi_indotgl.php');
include('../koneksi/fungsi_waktu.php');

/**
 * Check whether a table exists in the current database.
 */
function table_exists(PDO $connection, string $table): bool
{
	static $cache = [];

	if (isset($cache[$table])) {
		return $cache[$table];
	}

	$stmt = $connection->prepare(
		'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
	);
	$stmt->execute([':table' => $table]);
	$cache[$table] = (bool)$stmt->fetchColumn();

	return $cache[$table];
}

/**
 * Check whether a column exists in a given table.
 */
function column_exists(PDO $connection, string $table, string $column): bool
{
	static $cache = [];
	$key = $table . '.' . $column;

	if (isset($cache[$key])) {
		return $cache[$key];
	}

	$stmt = $connection->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
	$stmt->execute([':column' => $column]);
	$cache[$key] = $stmt->fetch(PDO::FETCH_ASSOC) !== false;

	return $cache[$key];
}

/**
 * Return the first column that exists from the provided list.
 */
function first_existing_column(PDO $connection, string $table, array $candidates): ?string
{
	foreach ($candidates as $candidate) {
		if ($candidate && column_exists($connection, $table, $candidate)) {
			return $candidate;
		}
	}

	return null;
}

try {
	$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 0;

	$params = [];
	$joins = [];

	$siswaIdColumn = first_existing_column($connection, 'pengguna', ['id', 'id_pengguna', 'id_pengguna']);
	if ($siswaIdColumn === null) {
		throw new RuntimeException('Kolom ID pengguna tidak ditemukan.');
	}

	$hasStatusColumn = column_exists($connection, 'pengguna', 'status');

	// Build identifier expression (ID tabungan / NIS / fallback)
	$identifierParts = [];
	foreach (['id_tabungan', 'nis', 'no_pengenal', 'username'] as $candidate) {
		if (column_exists($connection, 'pengguna', $candidate)) {
			$identifierParts[] = 's.' . $candidate;
		}
	}
	if (!$identifierParts) {
		$identifierParts[] = 'CAST(s.' . $siswaIdColumn . ' AS CHAR)';
	}
	$identifierExpr = 'COALESCE(' . implode(', ', $identifierParts) . ')';

	// Build kelas expression
	if (column_exists($connection, 'pengguna', 'kelas')) {
		$kelasExprRaw = 's.kelas';
	} else {
		$idKelasColumn = first_existing_column($connection, 'pengguna', ['id_kelas', 'kelas_id']);
		if ($idKelasColumn && table_exists($connection, 'kelas') && column_exists($connection, 'kelas', 'id_kelas')) {
			$joins[] = 'LEFT JOIN kelas k ON s.' . $idKelasColumn . ' = k.id_kelas';
			$kelasExprRaw = 'k.nama_kelas';
		} else {
			$kelasExprRaw = 'NULL';
		}
	}
	$kelasExpr = 'COALESCE(' . $kelasExprRaw . ", '-')";

	// Build saldo expression
	$saldoColumn = first_existing_column($connection, 'pengguna', ['saldo', 'saldo_tabungan', 'saldo_akhir']);
	$saldoExpr = $saldoColumn ? 's.' . $saldoColumn : '0';

	// Build last transaction expression with fallbacks
	$lastTransExpr = null;

	if (column_exists($connection, 'pengguna', 'transaksi_terakhir')) {
		$lastTransExpr = 's.transaksi_terakhir';
	}

	if ($lastTransExpr === null && table_exists($connection, 'tabungan') && column_exists($connection, 'tabungan', 'tanggal')) {
		$tabunganJoinColumn = null;
		$tabunganKeyColumn = null;

		if (column_exists($connection, 'tabungan', 'id_pengguna')) {
			$candidate = first_existing_column($connection, 'pengguna', ['id_pengguna', 'id_pengguna', 'id']);
			if ($candidate !== null) {
				$tabunganJoinColumn = $candidate;
				$tabunganKeyColumn = 'id_pengguna';
			}
		} elseif (column_exists($connection, 'tabungan', 'id_pengguna') || column_exists($connection, 'tabungan', 'id_siswa')) {
			$candidate = first_existing_column($connection, 'pengguna', ['id_pengguna', 'id']);
			if ($candidate !== null) {
				$tabunganJoinColumn = $candidate;
				$tabunganKeyColumn = (column_exists($connection, 'tabungan', 'id_pengguna') ? 'id_pengguna' : 'id_siswa');
			}
		} elseif (column_exists($connection, 'tabungan', 'id_tabungan') && column_exists($connection, 'pengguna', 'id_tabungan')) {
			$tabunganJoinColumn = 'id_tabungan';
			$tabunganKeyColumn = 'id_tabungan';
		}

		if ($tabunganJoinColumn !== null && $tabunganKeyColumn !== null) {
			$joins[] = 'LEFT JOIN (SELECT ' . $tabunganKeyColumn . ' AS join_id, MAX(tanggal) AS last_transaksi FROM tabungan GROUP BY ' . $tabunganKeyColumn . ') tab_last ON tab_last.join_id = s.' . $tabunganJoinColumn;
			$lastTransExpr = 'tab_last.last_transaksi';
		}
	}

	if ($lastTransExpr === null && table_exists($connection, 'transaksi') && column_exists($connection, 'transaksi', 'tanggal')) {
		if (column_exists($connection, 'transaksi', 'id_pengguna') || column_exists($connection, 'transaksi', 'id_siswa')) {
			$candidate = first_existing_column($connection, 'pengguna', ['id_pengguna', 'id']);
			if ($candidate !== null) {
				$transCol = column_exists($connection, 'transaksi', 'id_pengguna') ? 'id_pengguna' : 'id_siswa';
				$joins[] = 'LEFT JOIN (SELECT ' . $transCol . ' AS join_id, MAX(tanggal) AS last_transaksi FROM transaksi GROUP BY ' . $transCol . ') trx_last ON trx_last.join_id = s.' . $candidate;
				$lastTransExpr = 'trx_last.last_transaksi';
			}
		} elseif (column_exists($connection, 'transaksi', 'id_tabungan')) {
			$candidate = first_existing_column($connection, 'pengguna', ['id_tabungan', 'nis', 'no_pengenal']);
			if ($candidate !== null) {
				$joins[] = 'LEFT JOIN (SELECT id_tabungan AS join_id, MAX(tanggal) AS last_transaksi FROM transaksi GROUP BY id_tabungan) trx_last ON trx_last.join_id = s.' . $candidate;
				$lastTransExpr = 'trx_last.last_transaksi';
			}
		}
	}

	if ($lastTransExpr === null) {
		if (column_exists($connection, 'pengguna', 'updated_at')) {
			$lastTransExpr = 's.updated_at';
		} elseif (column_exists($connection, 'pengguna', 'created_at')) {
			$lastTransExpr = 's.created_at';
		} else {
			$lastTransExpr = 'NULL';
		}
	}

	$selectParts = [
		's.' . $siswaIdColumn . ' AS row_id',
		$identifierExpr . ' AS tabungan_id',
		's.nama AS nama',
		$kelasExpr . ' AS kelas',
		$saldoExpr . ' AS saldo',
		$lastTransExpr . ' AS last_transaksi'
	];

	$whereClauses = ['1 = 1'];
	if ($hasStatusColumn) {
		$whereClauses[] = "s.status = 'aktif'";
	}

	if (isset($_POST['search']['value']) && $_POST['search']['value'] !== '') {
		$searchValue = trim($_POST['search']['value']);
		if ($searchValue !== '') {
			$params[':search'] = '%' . $searchValue . '%';
			$whereClauses[] = '(' . $identifierExpr . ' LIKE :search OR s.nama LIKE :search OR ' . $kelasExpr . ' LIKE :search)';
		}
	}

	$whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

	$orderColumns = [
		0 => null,
		1 => 'tabungan_id',
		2 => 'nama',
		3 => 'kelas',
		4 => 'saldo',
		5 => 'last_transaksi',
	];

	$orderSql = '';
	if (isset($_POST['order'][0]['column'])) {
		$colIdx = intval($_POST['order'][0]['column']);
		$dir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'asc') ? 'ASC' : 'DESC';

		if (!empty($orderColumns[$colIdx])) {
			$orderSql = ' ORDER BY ' . $orderColumns[$colIdx] . ' ' . $dir;
		}
	}

	if ($orderSql === '') {
		$orderSql = ' ORDER BY last_transaksi DESC';
	}

	$limitSql = '';
	if (isset($_POST['length']) && intval($_POST['length']) !== -1) {
		$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
		$length = intval($_POST['length']);
		if ($length < 1) {
			$length = 10;
		}
		$limitSql = ' LIMIT ' . $start . ', ' . $length;
	}

	$joinSql = $joins ? ' ' . implode(' ', $joins) : '';

	// Total records (without search)
	$totalSql = 'SELECT COUNT(*) FROM pengguna s';
	if ($hasStatusColumn) {
		$totalSql .= " WHERE s.status = 'aktif'";
	}
	$recordsTotal = (int)$connection->query($totalSql)->fetchColumn();

	// Filtered count
	$countSql = 'SELECT COUNT(*) FROM pengguna s' . $joinSql . ' ' . $whereSql;
	$countStmt = $connection->prepare($countSql);
	foreach ($params as $key => $value) {
		$countStmt->bindValue($key, $value);
	}
	$countStmt->execute();
	$recordsFiltered = (int)$countStmt->fetchColumn();

	// Main query
	$sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM pengguna s' . $joinSql . ' ' . $whereSql . $orderSql . $limitSql;
	$statement = $connection->prepare($sql);
	foreach ($params as $key => $value) {
		$statement->bindValue($key, $value);
	}
	$statement->execute();
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);

	$data = [];
	$no = isset($_POST['start']) ? intval($_POST['start']) + 1 : 1;

	foreach ($result as $row) {
		$tabunganId = isset($row['tabungan_id']) ? htmlspecialchars((string)$row['tabungan_id']) : '';
		$nama = isset($row['nama']) ? htmlspecialchars((string)$row['nama']) : '';
		$kelas = isset($row['kelas']) ? htmlspecialchars((string)$row['kelas']) : '-';

		$saldoNumber = isset($row['saldo']) ? (float)$row['saldo'] : 0;
		$saldoFormatted = 'Rp ' . number_format($saldoNumber, 0, ',', '.');

		$tanggalRaw = $row['last_transaksi'] ?? '';
		$tanggalDisplay = '<div align="center">-</div>';
		if (!empty($tanggalRaw) && $tanggalRaw !== '0000-00-00') {
			$tanggal = substr((string)$tanggalRaw, 0, 10);
			if ($tanggal && $tanggal !== '0000-00-00') {
				$tanggalDisplay = '<div align="center">' . tgl_indo($tanggal) . '</div>';
			}
		}

		$rowId = isset($row['row_id']) ? htmlspecialchars((string)$row['row_id']) : '';
		$menuId = 'actionMenu' . $rowId;

		$dropdown = '<div class="text-center">
				<div class="dropdown">
					<button class="btn btn-light btn-sm dropdown-toggle" type="button" id="' . $menuId . '" data-bs-toggle="dropdown">
						<i class="fe fe-more-vertical"></i>
					</button>
					<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="' . $menuId . '">
						<li>
							<button class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#myMasuk" data-id="' . $rowId . '">
								<span class="badge rounded-pill bg-warning me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center">
									<i class="fe fe-zoom-in" style="color:white"></i>
								</span>
								Tambah Masuk
							</button>
						</li>
						<li>
							<button class="dropdown-item d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#myTarik" data-id="' . $rowId . '">
								<span class="badge rounded-pill bg-success me-2" style="width:28px;height:28px;display:flex;align-items:center;justify-content:center">
									<i class="fe fe-zoom-out" style="color:white"></i>
								</span>
								Tambah Tarik
							</button>
						</li>
					</ul>
				</div>
			</div>';

		$data[] = [
			'<div align="center">' . ($no++) . '</div>',
			'<div align="center">' . $tabunganId . '</div>',
			$nama,
			'<div align="center">' . $kelas . '</div>',
			'<div align="center">' . $saldoFormatted . '</div>',
			$tanggalDisplay,
			$dropdown,
		];
	}

	$output = [
		'draw' => $draw,
		'recordsTotal' => $recordsTotal,
		'recordsFiltered' => $recordsFiltered,
		'data' => $data,
	];
} catch (Throwable $e) {
	$output = [
		'draw' => isset($draw) ? $draw : 0,
		'recordsTotal' => 0,
		'recordsFiltered' => 0,
		'data' => [],
		'error' => 'Server error',
	];
}

$json = json_encode($output, JSON_UNESCAPED_UNICODE);
if ($json === false) {
	$json = json_encode(['error' => json_last_error_msg()]);
}

ob_end_clean();
echo $json;
exit;

