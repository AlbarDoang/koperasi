<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

try {
    require_once '../koneksi/db_auto.php';
    require_once '../koneksi/fungsi_indotgl.php';
    require_once 'function_all.php';

    if (!isset($connection) || !($connection instanceof PDO)) {
        throw new Exception('DB connection not found');
    }

    // Support both GET and POST from DataTables
    $request = $_POST ?: $_GET;

    $draw   = intval($request['draw'] ?? 1);
    $start  = max(0, intval($request['start'] ?? 0));
    $length = intval($request['length'] ?? 10);
    // DataTables uses -1 to indicate "all records"; treat as large number
    if ($length === -1) {
        $length = 1000000;
    }

    $search = trim($request['search']['value'] ?? '');
    // Accept only 'transfer_masuk' or 'transfer_keluar'; default to 'transfer_masuk' for compatibility
    $filterJenis = trim(strtolower($request['filter_jenis'] ?? 'transfer_masuk'));

    /* ================= TOTAL DATA ================= */
    // total across both transfer types (unfiltered) to report overall total
    $totalStmt = $connection->query("SELECT COUNT(*) FROM transaksi WHERE jenis_transaksi IN ('transfer_masuk','transfer_keluar')");
    $recordsTotal = (int)$totalStmt->fetchColumn();

    /* ================= FILTER ================= */
    $params = [];
    if ($filterJenis === 'transfer_masuk' || $filterJenis === 'transfer_keluar') {
        $where = " WHERE jenis_transaksi = :filterJenis";
        $params[':filterJenis'] = $filterJenis;
    } else {
        // invalid value: default to 'transfer_masuk'
        $where = " WHERE jenis_transaksi = :filterJenis";
        $params[':filterJenis'] = 'transfer_masuk';
    }

    if ($search !== '') {
        $where .= " AND (id_anggota LIKE :q OR keterangan LIKE :q OR jenis_transaksi LIKE :q)";
        $params[':q'] = "%$search%";
    }

    /* ================= FILTERED COUNT ================= */
    $filteredSql = "SELECT COUNT(*) FROM transaksi $where";
    $filteredStmt = $connection->prepare($filteredSql);
    foreach ($params as $k => $v) {
        $filteredStmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $filteredStmt->execute();
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    /* ================= ORDER ================= */
    $columns = [
        0 => 'id_transaksi',
        1 => 'id_anggota',
        2 => 'jenis_transaksi',
        3 => 'tanggal',
        4 => 'jumlah',
        5 => 'keterangan'
    ];

    $orderColIndex = intval($request['order'][0]['column'] ?? 3);
    $orderCol = $columns[$orderColIndex] ?? 'tanggal';
    $orderDir = strtoupper($request['order'][0]['dir'] ?? 'ASC');
    $orderDir = in_array($orderDir, ['ASC','DESC']) ? $orderDir : 'ASC';

    /* ================= DATA QUERY ================= */
    // Use explicit binding for LIMIT to avoid SQL injection and maintain integer typing
    $sql = "SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, keterangan, tanggal FROM transaksi $where ORDER BY $orderCol $orderDir LIMIT :start, :length";

    $stmt = $connection->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    $data = [];
    $no = $start + 1;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $idTrans = (int)$row['id_transaksi'];
        $idAnggota = htmlspecialchars($row['id_anggota']);
        $jenis = htmlspecialchars($row['jenis_transaksi']);
        $tanggal = htmlspecialchars($row['tanggal']);
        $jumlah = (float)$row['jumlah'];
        $keterangan = htmlspecialchars($row['keterangan'] ?? '');

        // human friendly jenis
        $jenisLabel = ($jenis === 'transfer_masuk') ? 'Transfer Masuk' : 'Transfer Keluar';

        $data[] = [
            "<div class='text-center'>{$no}</div>",
            "<div class='text-center'>".htmlspecialchars($idAnggota)."</div>",
            htmlspecialchars($jenisLabel),
            "<div class='text-center'>".tgl_indo($tanggal)."</div>",
            "Rp ".number_format($jumlah,0,',','.'),
            htmlspecialchars($keterangan),
            "<div class='text-center'>
                <a href='#myModal' class='btn-detail' data-bs-toggle='modal' data-id='".htmlspecialchars($idTrans)."'>
                    <button class='btn btn-primary btn-sm'><i class='fe fe-search'></i></button>
                </a>
            </div>"
        ];
        $no++;
    }

    // Ensure no other output precedes JSON
    ob_end_clean();

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // Always try to send back valid JSON; avoid sending HTML or half responses
    ob_end_clean();
    // For DataTables, return 200 with error field; this avoids Ajax error popup in some setups
    http_response_code(200);
    echo json_encode([
        'draw' => intval($request['draw'] ?? 1),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
