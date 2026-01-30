<?php
// Always respond with JSON and suppress on-screen errors
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
// Buffer output to capture stray text
ob_start();

try {
	// Load connection: try koneksi/config.php first (MySQLi style)
	$includesOk = true;
	if (file_exists(__DIR__ . '/../../koneksi/config.php')) {
		include(__DIR__ . '/../../koneksi/config.php');
	} else {
		// Fallback to direct connection
		$con = new mysqli('localhost', 'root', '', 'tabungan');
		if ($con->connect_error) {
			throw new Exception('DB Connection Error: ' . $con->connect_error);
		}
	}

	// Load helper functions if available
	if (file_exists(__DIR__ . '/../../koneksi/fungsi_indotgl.php')) {
		include(__DIR__ . '/../../koneksi/fungsi_indotgl.php');
	}

	// DataTables request params
	$draw   = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
	$start  = isset($_POST['start']) ? intval($_POST['start']) : 0;
	$length = isset($_POST['length']) ? intval($_POST['length']) : 10;
	$search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
	$order  = isset($_POST['order'][0]) ? $_POST['order'][0] : null;
	// Optional status filter from client: pending/approved/rejected/all
	$statusFilter = isset($_POST['status_filter']) ? strtolower(trim((string)$_POST['status_filter'])) : 'all';

	// Map column indices to table columns in the order shown to the admin table
	$columns = [
		0 => 'id_mulai_nabung', // No (DB id)
		1 => 'tanggal',
		2 => 'nama_pengguna',
		3 => 'nomor_hp',
		4 => 'jenis_tabungan',
		5 => 'jumlah',
		6 => 'status',
		7 => 'id_mulai_nabung' // aksi uses id
	];

	$orderCol = 'id_mulai_nabung';
	$orderDir = 'ASC';
	if ($order && isset($order['column'])) {
		$colIdx = intval($order['column']);
		if (array_key_exists($colIdx, $columns)) {
			$orderCol = $columns[$colIdx];
		}
	}
	if ($order && isset($order['dir']) && in_array(strtoupper($order['dir']), ['ASC', 'DESC'])) {
		$orderDir = strtoupper($order['dir']);
	}

	// Build WHERE clauses (apply both search and status filter consistently)
	$where = [ '1=1' ];
	if ($search !== '') {
		$searchTerm = $con->real_escape_string('%' . $search . '%');
		$where[] = "(m.nama_pengguna LIKE '$searchTerm' OR m.nomor_hp LIKE '$searchTerm' OR m.jenis_tabungan LIKE '$searchTerm')";
	}

	if ($statusFilter === 'pending') {
		// Pending states in our DB
		$where[] = "(LOWER(m.status) IN ('menunggu_admin','menunggu_penyerahan','pending'))";
	} elseif ($statusFilter === 'approved' || $statusFilter === 'success' || $statusFilter === 'berhasil' || $statusFilter === 'disetujui') {
		// Approved / success states (also accept 'disetujui' as an alias)
		$where[] = "(LOWER(m.status) IN ('diterima','berhasil','sukses','success','disetujui'))";
	} elseif ($statusFilter === 'rejected') {
		// Rejected states (canonical 'ditolak' plus legacy alternatives)
		$where[] = "(LOWER(m.status) IN ('ditolak','gagal','failed'))";
	}

	$whereSql = implode(' AND ', $where);

	$baseSql = "SELECT m.id_mulai_nabung, m.tanggal, m.nama_pengguna, m.nomor_hp, m.jenis_tabungan, m.jumlah, m.status FROM mulai_nabung m WHERE $whereSql";
	$baseSql .= " ORDER BY $orderCol $orderDir LIMIT $start, $length";

	// Execute query
	$result = $con->query($baseSql);
	if (!$result) {
		throw new Exception('Query failed: ' . $con->error);
	}
	$rows = $result->fetch_all(MYSQLI_ASSOC);

	// Get filtered total
	$countSql = "SELECT COUNT(*) AS cnt FROM mulai_nabung m WHERE $whereSql";
	$countResult = $con->query($countSql);
	if (!$countResult) {
		throw new Exception('Count query failed: ' . $con->error);
	}
	$countRow = $countResult->fetch_assoc();
	$filteredTotal = intval($countRow['cnt']);
	// Get total unfiltered
	$totalResult = $con->query("SELECT COUNT(*) AS cnt FROM mulai_nabung");
	if (!$totalResult) {
		throw new Exception('Total count query failed: ' . $con->error);
	}
	$totalRow = $totalResult->fetch_assoc();
	$recordsTotal = intval($totalRow['cnt']);

	// Build data array
	$data = [];
	foreach ($rows as $row) {
		$sub = [];
		// No column (use DB id for now)
		$sub[] = htmlspecialchars($row['id_mulai_nabung']);
		// Tanggal: display as dd-mm-yyyy; tooltip uses tgl_indo() if available for a friendly label
		$tanggal_raw = $row['tanggal'];
		// Always show dd-mm-yyyy in the cell
		$tanggal_display = date('d-m-Y', strtotime($tanggal_raw));
		// Tooltip: prefer Indonesian friendly format when helper exists
		$tooltip = $tanggal_display;
		if (function_exists('tgl_indo')) {
			$nice = tgl_indo($tanggal_raw);
			if (!empty($nice)) { $tooltip = $nice; }
		}
		$sub[] = '<div class="cell-ellipsis" data-bs-toggle="tooltip" title="' . htmlspecialchars($tooltip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($tanggal_display) . '</div>';
		// Nama Pengguna (wrap allowed)
		$nama_full = $row['nama_pengguna'];
		$sub[] = '<div class="cell-wrap" data-bs-toggle="tooltip" title="' . htmlspecialchars($nama_full, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($nama_full) . '</div>';
		// No HP
		$nohp_full = $row['nomor_hp'];
		$sub[] = '<div class="cell-ellipsis" data-bs-toggle="tooltip" title="' . htmlspecialchars($nohp_full, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($nohp_full) . '</div>';
		// Jenis Tabungan
		$jenis_full = !empty($row['jenis_tabungan']) ? $row['jenis_tabungan'] : 'Tab Reguler';
		$sub[] = '<div class="cell-ellipsis" data-bs-toggle="tooltip" title="' . htmlspecialchars($jenis_full, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($jenis_full) . '</div>';
		// Jumlah (format Rupiah, right aligned)
		$jumlah_display = 'Rp ' . number_format($row['jumlah'], 0, ',', '.');
		$sub[] = '<div class="cell-ellipsis text-end" data-bs-toggle="tooltip" title="' . htmlspecialchars($jumlah_display, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($jumlah_display) . '</div>';
		// Status as badge — normalize display for some DB values while keeping exact DB values elsewhere
		$rawStatus = strtolower(trim((string)($row['status'] ?? '')));
		// Map display label: show 'Menunggu' for pending DB states and localize others
		$statusLabelMap = [
			'menunggu_admin' => 'Menunggu',
			'menunggu_penyerahan' => 'Menunggu',
			'pending' => 'Menunggu',
			'diterima' => 'Disetujui',
			'berhasil' => 'Disetujui',
			'sukses' => 'Disetujui',
			'success' => 'Disetujui',
			'disetujui' => 'Disetujui',
			'ditolak' => 'Ditolak',
			'gagal' => 'Ditolak',
			'failed' => 'Ditolak'
		];
		$label = isset($statusLabelMap[$rawStatus]) ? $statusLabelMap[$rawStatus] : ucfirst($rawStatus);
		if (in_array($rawStatus, ['diterima','berhasil','sukses','success','disetujui'])) {
			$statusHtml = '<span class="badge bg-success">' . $label . '</span>';
		} elseif (in_array($rawStatus, ['menunggu_admin','menunggu_penyerahan','pending'])) {
			$statusHtml = '<span class="badge bg-warning text-white">' . $label . '</span>';
		} elseif (in_array($rawStatus, ['ditolak','gagal','failed'])) {
			$statusHtml = '<span class="badge bg-danger">' . $label . '</span>';
		} else {
			$statusHtml = '<span class="badge bg-secondary">' . $label . '</span>';
		}
		$sub[] = $statusHtml;
		// Actions (keep previous dropdown behavior)
		$menuId = 'actionMenu' . htmlspecialchars($row['id_mulai_nabung']);
		$sts = strtolower(trim((string)($row['status'] ?? '')));
		if (in_array($sts, ['menunggu_admin','menunggu_penyerahan','pending'])) {
			$actionBtns = '<div class="dropdown">';
			$actionBtns .= '<button class="btn btn-sm btn-light dropdown-toggle" type="button" id="' . $menuId . '" data-bs-toggle="dropdown" aria-expanded="false">⋮</button>';
			$actionBtns .= '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="' . $menuId . '">';
			$actionBtns .= '<li><a class="dropdown-item" href="#" onclick="approveMasuk(\'' . htmlspecialchars($row['id_mulai_nabung']) . '\')">Setujui</a></li>';
			$actionBtns .= '<li><a class="dropdown-item text-danger" href="#" onclick="rejectMasuk(\'' . htmlspecialchars($row['id_mulai_nabung']) . '\')">Tolak</a></li>';
			$actionBtns .= '</ul></div>';
		} else {
			$actionBtns = '<span class="text-muted">-</span>';
		}
		$sub[] = $actionBtns;
		$data[] = $sub;
	}

	// Return JSON response
	$payload = [
		'draw' => $draw,
		'recordsTotal' => $recordsTotal,
		'recordsFiltered' => $filteredTotal,
		'data' => $data
	];

	// Clear any stray output
	$extra = ob_get_clean();
	if (!empty($extra) && strlen(trim($extra)) > 0) {
		@file_put_contents(__DIR__ . '/../fetch_masuk_debug.log', date('c') . " stray_output: " . $extra . "\n", FILE_APPEND);
	}

	http_response_code(200);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
	// Log the error
	$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
	@file_put_contents(__DIR__ . '/../fetch_masuk_error.log', date('c') . " error: " . $e->getMessage() . "\n", FILE_APPEND);
	
	// Clear any output and return clean error response
	$extra = ob_get_clean();
	if (!empty($extra) && strlen(trim($extra)) > 0) {
		@file_put_contents(__DIR__ . '/../fetch_masuk_debug.log', date('c') . " stray_output_on_error: " . $extra . "\n", FILE_APPEND);
	}

	http_response_code(200);
	echo json_encode([
		'draw' => $draw,
		'recordsTotal' => 0,
		'recordsFiltered' => 0,
		'data' => [],
		'error' => 'server_error'
	], JSON_UNESCAPED_UNICODE);
}
