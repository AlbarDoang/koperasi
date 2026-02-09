<?php
// Implementation for DataTables transaksi endpoint (buffered & safe)
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

    $sql = "SELECT t.*, s.nis, s.nama FROM tabungan t JOIN pengguna s ON t.id_pengguna = s.id_pengguna";
    $params = [];
    if (!empty($_POST['search']['value'])) {
        $sql .= " WHERE (s.nama LIKE :q OR s.nis LIKE :q)";
        $params[':q'] = '%' . $_POST['search']['value'] . '%';
    }

    $cols = [0 => 't.id_tabungan', 1 => 't.id_tabungan', 2 => 's.nama', 3 => 't.id_tabungan', 4 => 't.tanggal', 5 => 't.jumlah', 6 => 't.keterangan'];
    $orderCol = 't.id_tabungan';
    $orderDir = 'DESC';
    if (isset($_POST['order'][0]['column'])) {
        $c = intval($_POST['order'][0]['column']);
        if (isset($cols[$c])) $orderCol = $cols[$c];
    }
    if (isset($_POST['order'][0]['dir']) && in_array(strtoupper($_POST['order'][0]['dir']), ['ASC', 'DESC'])) $orderDir = strtoupper($_POST['order'][0]['dir']);

    $sql .= " ORDER BY $orderCol $orderDir LIMIT :start, :length";

    if (!isset($connection)) throw new RuntimeException('db_unavailable');
    $stmt = $connection->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countSql = "SELECT COUNT(*) FROM tabungan t JOIN pengguna s ON t.id_pengguna=s.id_pengguna" . (!empty($_POST['search']['value']) ? " WHERE (s.nama LIKE :q OR s.nis LIKE :q)" : '');
    $cntStmt = $connection->prepare($countSql);
    if (!empty($_POST['search']['value'])) $cntStmt->bindValue(':q', '%' . $_POST['search']['value'] . '%');
    $cntStmt->execute();
    $filteredTotal = intval($cntStmt->fetchColumn());

    $data = [];
    $no = $start + 1;
    foreach ($rows as $row) {
        $sub = [];
        $sub[] = '<div align="center">' . $no++ . '</div>';
        $sub[] = '<div align="center">' . (empty($row['no_masuk']) ? htmlspecialchars($row['no_keluar']) : htmlspecialchars($row['no_masuk'])) . '</div>';
        $sub[] = htmlspecialchars($row['nama']);
        $sub[] = '<div align="center">' . htmlspecialchars($row['id_tabungan']) . '</div>';
        $sub[] = '<div align="center">' . tgl_indo($row['tanggal']) . '</div>';
        $sub[] = empty($row['no_masuk']) ? ('<div align="center"><font color=red><b>' . htmlspecialchars($row['kegiatan']) . '</b></font></div>') : ('<div align="center"><font color=green><b>' . htmlspecialchars($row['kegiatan']) . '</b></font></div>');
        $sub[] = empty($row['no_masuk']) ? ('Rp ' . number_format($row['nominal_k'])) : ('Rp ' . number_format($row['nominal_m']));
        $sub[] = '<div align="center"><a href="#myModal" id="custId" data-bs-toggle="modal" data-id="' . htmlspecialchars($row['id_transaksi']) . '">'
            . '<button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Transaksi"><i class="fe fe-alert-octagon icon-lg"></i></button></a></div>';
        $data[] = $sub;
    }

    $out = ['draw' => $draw, 'recordsTotal' => $filteredTotal, 'recordsFiltered' => (function_exists('get_total_all_records_transaksi') ? intval(get_total_all_records_transaksi()) : $filteredTotal), 'data' => $data];
    $extra = ob_get_clean();
    if (!empty($extra)) @file_put_contents(__DIR__ . '/../logs/fetch_transaksi_output_debug.log', date('c') . " stray_output:\n" . $extra . "\n", FILE_APPEND);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    $extra = ob_get_clean();
    @file_put_contents(__DIR__ . '/../logs/fetch_transaksi_error.log', date('c') . " ajax error: " . $e->getMessage() . "\nextra:\n" . $extra . "\n", FILE_APPEND);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'server_error'], JSON_UNESCAPED_UNICODE);
    exit;
}

