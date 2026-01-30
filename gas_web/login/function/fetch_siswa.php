<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

try {
	include('../koneksi/db_auto.php');
	include('../koneksi/fungsi_indotgl.php');
	include('function_all.php');

	$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
	$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
	$length = isset($_POST['length']) ? intval($_POST['length']) : 10;

	$sql = "SELECT * FROM siswa WHERE status = 'aktif'";
	$sql = "SELECT * FROM pengguna WHERE status = 'aktif'";
	$params = [];
	if (!empty($_POST['search']['value'])) {
		$sql .= " AND (nis LIKE :q OR nama LIKE :q)";
		$params[':q'] = '%' . $_POST['search']['value'] . '%';
	}

	$cols = [0 => 'id_anggota', 1 => 'id_tabungan', 2 => 'no_pengenal', 3 => 'nama', 4 => 'jk', 5 => 'no_wa', 6 => 'kelas'];
	$orderCol = 'id_anggota';
	$orderDir = 'DESC';
	if (isset($_POST['order'][0]['column'])) {
		$c = intval($_POST['order'][0]['column']);
		if (isset($cols[$c])) $orderCol = $cols[$c];
	}
	if (isset($_POST['order'][0]['dir']) && in_array(strtoupper($_POST['order'][0]['dir']), ['ASC', 'DESC'])) {
		$orderDir = strtoupper($_POST['order'][0]['dir']);
	}

	$sql .= " ORDER BY $orderCol $orderDir LIMIT :start, :length";

	if (!isset($connection)) throw new RuntimeException('db_unavailable');
	$stmt = $connection->prepare($sql);
	foreach ($params as $k => $v) $stmt->bindValue($k, $v);
	$stmt->bindValue(':start', $start, PDO::PARAM_INT);
	$stmt->bindValue(':length', $length, PDO::PARAM_INT);
	$stmt->execute();
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$countSql = "SELECT COUNT(*) FROM siswa WHERE status='aktif'" . (!empty($_POST['search']['value']) ? " AND (nis LIKE :q OR nama LIKE :q)" : '');
	$countSql = "SELECT COUNT(*) FROM pengguna WHERE status='aktif'" . (!empty($_POST['search']['value']) ? " AND (nis LIKE :q OR nama LIKE :q)" : '');
	$cntStmt = $connection->prepare($countSql);
	if (!empty($_POST['search']['value'])) $cntStmt->bindValue(':q', '%' . $_POST['search']['value'] . '%');
	$cntStmt->execute();
	$filteredTotal = intval($cntStmt->fetchColumn());

	$data = [];
	$no = $start + 1;
	foreach ($rows as $row) {
		$sub = [];
		$sub[] = '<div align="center">' . $no++ . '</div>';
		$sub[] = '<div align="center">' . htmlspecialchars($row['id_tabungan'] ?? '') . '</div>';
		$sub[] = '<div align="center">' . htmlspecialchars($row['no_pengenal'] ?? '') . '</div>';
		$sub[] = htmlspecialchars($row['nama'] ?? '');
		$sub[] = '<div align="center">' . htmlspecialchars($row['jk'] ?? '') . '</div>';
		$sub[] = '<div align="center">' . htmlspecialchars($row['no_wa'] ?? '') . '</div>';
		$sub[] = '<div align="center">' . htmlspecialchars($row['kelas'] ?? '') . '</div>';
		$sub[] = '<div align="center">'
			. '<a href="#myModal" id="custId" data-bs-toggle="modal" data-id="' . htmlspecialchars($row['id']) . '">'
			. '<button type="button" class="btn btn-dark btn-sm btn-icon" title="Detail Akun"><i class="fe fe-unlock icon-lg"></i></button></a> '
			. '<a href="detail_pengguna.php?id_pengguna=' . urlencode($row['id']) . '"><button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Pengguna"><i class="fe fe-alert-octagon"></i></button></a> '
			. '<a href="buku_tabungan.php?id_pengguna=' . urlencode($row['id']) . '"><button type="button" class="btn btn-success btn-sm btn-icon" title="Cek Buku Tabungan"><i class="fe fe-file-text"></i></button></a> '
			. '<a href="#mdDelete" id="custId" data-bs-toggle="modal" data-id="' . htmlspecialchars($row['id']) . '">'
			. '<button type="button" class="btn btn-danger btn-sm btn-icon" title="Hapus Transaksi"><i class="fe fe-trash-2"></i></button></a>'
			. '</div>';
		$data[] = $sub;
	}

	$out = [
		'draw' => $draw,
		'recordsTotal' => $filteredTotal,
		'recordsFiltered' => (function_exists('get_total_all_records_siswa') ? intval(get_total_all_records_siswa()) : $filteredTotal),
		'recordsFiltered' => (function_exists('get_total_all_records_pengguna') ? intval(get_total_all_records_pengguna()) : $filteredTotal),
		'data' => $data
	];

	$extra = ob_get_clean();
	if (!empty($extra)) @file_put_contents(__DIR__ . '/../logs/fetch_siswa_output_debug.log', date('c') . " stray_output:\n" . $extra . "\n", FILE_APPEND);
	echo json_encode($out, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	$extra = ob_get_clean();
	@file_put_contents(__DIR__ . '/../logs/fetch_siswa_error.log', date('c') . " ajax error: " . $e->getMessage() . "\nextra:\n" . $extra . "\n", FILE_APPEND);
	$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
	echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'server_error'], JSON_UNESCAPED_UNICODE);
}
