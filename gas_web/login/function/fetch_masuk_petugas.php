<?php
// Return clean JSON for DataTables — buffer and strip any stray output
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();
try {
	include('../koneksi/db_auto.php');
	include('../koneksi/fungsi_indotgl.php');
	include('function_all.php');

	$query = '';
	$output = array();
	// Select t_masuk and try to fetch user's phone if available via id_tabungan
	$query .= "SELECT m.*, p.no_hp AS no_hp FROM t_masuk m LEFT JOIN pengguna p ON p.id_tabungan = m.id_tabungan ";
	if (isset($_POST["search"]["value"])) {
		$val = addslashes($_POST["search"]["value"]);
		$query .= 'WHERE m.no_masuk LIKE "%' . $val . '%" ';
		$query .= 'OR m.nama LIKE "%' . $val . '%" ';
		$query .= 'OR p.no_hp LIKE "%' . $val . '%" ';
	}
	if (isset($_POST["order"])) {
		$col = intval($_POST['order']['0']['column']);
		$dir = ($_POST['order']['0']['dir'] === 'asc') ? 'ASC' : 'DESC';
		$query .= 'ORDER BY ' . $col . ' ' . $dir . ' ';
	} else {
		$query .= 'ORDER BY id_masuk DESC ';
	}
	if (isset($_POST["length"]) && $_POST["length"] != -1) {
		$start = intval($_POST['start']);
		$length = intval($_POST['length']);
		$query .= 'LIMIT ' . $start . ', ' . $length;
	}
	$statement = $connection->prepare($query);
	$statement->execute();
	$result = $statement->fetchAll();
	$data = array();
	$no = 1;
	$filtered_rows = $statement->rowCount();
	foreach ($result as $row) {
		$sub_array = array();
		// No (client will replace with page numbering)
		$sub_array[] = htmlspecialchars($row["id_masuk"]);
		// Tanggal
		$tgl = isset($row["tanggal"]) ? (function_exists('tgl_indo') ? tgl_indo($row["tanggal"]) : date('d-m-Y', strtotime($row["tanggal"]))) : '-';
		$sub_array[] = '<div class="cell-ellipsis text-center" data-bs-toggle="tooltip" title="' . htmlspecialchars($tgl) . '">' . htmlspecialchars($tgl) . '</div>';
		// Nama Pengguna
		$sub_array[] = '<div class="cell-wrap" data-bs-toggle="tooltip" title="' . htmlspecialchars($row["nama"]) . '">' . htmlspecialchars($row["nama"]) . '</div>';
		// No. HP (if available)
		$nohp = !empty($row["no_hp"]) ? $row["no_hp"] : '-';
		$sub_array[] = '<div class="cell-ellipsis text-center" data-bs-toggle="tooltip" title="' . htmlspecialchars($nohp) . '">' . htmlspecialchars($nohp) . '</div>';
		// Jenis Tabungan (not stored in t_masuk) — show dash if not available
		$jenis = isset($row["jenis_tabungan"]) ? $row["jenis_tabungan"] : '-';
		$sub_array[] = '<div class="cell-ellipsis" data-bs-toggle="tooltip" title="' . htmlspecialchars($jenis) . '">' . htmlspecialchars($jenis) . '</div>';
		// Jumlah (format Rupiah)
		$jumlah_display = 'Rp ' . number_format($row["jumlah"], 0, ',', '.');
		$sub_array[] = '<div class="cell-ellipsis text-end" data-bs-toggle="tooltip" title="' . htmlspecialchars($jumlah_display) . '">' . htmlspecialchars($jumlah_display) . '</div>';
		// Status — t_masuk entries are treated as successful deposits by default
		$statusHtml = '<span class="badge bg-success">Berhasil</span>';
		$sub_array[] = '<div class="text-center">' . $statusHtml . '</div>';
		// Actions
		$sub_array[] = '<div class="text-center"> 

	<a href="#myModal" id="custId" data-bs-toggle="modal" data-id="' . htmlspecialchars($row["no_masuk"]) . '">
	<button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Transaksi"><i class="fe fe-alert-octagon icon-lg"></i></button></a>
    
	<a href="kwitansi.php?no_masuk=' . htmlspecialchars($row["no_masuk"]) . '"><button type="button" class="btn btn-success btn-sm btn-icon" title="Cetak Kwitansi Tabungan Masuk"><i class="fe fe-file-text"></i></button></a>
    
	</div>';
		$data[] = $sub_array;
	}
	$output = array(
		"draw"                =>    isset($_POST["draw"]) ? intval($_POST["draw"]) : 1,
		"recordsTotal"        =>     $filtered_rows,
		"recordsFiltered"    =>    function_exists('get_total_all_records_masuk') ? get_total_all_records_masuk() : $filtered_rows,
		"data"                =>    $data
	);

	// capture any stray output that might break JSON
	$extra = ob_get_clean();
	if (!empty($extra)) {
		@file_put_contents(__DIR__ . '/../logs/fetch_masuk_petugas_output_debug.log', date('c') . " stray_output:\n" . $extra . "\n", FILE_APPEND);
	}

	$json = json_encode($output, JSON_UNESCAPED_UNICODE);
	echo $json;
	exit;
} catch (Throwable $e) {
	$extra = ob_get_clean();
	@file_put_contents(__DIR__ . '/../logs/fetch_masuk_petugas_error.log', date('c') . " ajax error: " . $e->getMessage() . "\nextra_output:\n" . $extra . "\n", FILE_APPEND);
	$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
	$out = [
		'draw' => $draw,
		'recordsTotal' => 0,
		'recordsFiltered' => 0,
		'data' => [],
		'error' => 'server_error'
	];
	echo json_encode($out, JSON_UNESCAPED_UNICODE);
	exit;
}
