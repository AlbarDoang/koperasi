<?php
// DataTables server-side implementation: list pengguna with status_akun = 'pending'
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);
try {
    include('../koneksi/db_auto.php');

    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;

    // Select columns to match 'Semua Pengguna' without Waktu:
    // No | Nomor HP | Nama | Alamat | Tgl Lahir | Status | Aksi
    $baseSql = "SELECT id, no_hp, nama_lengkap, alamat_domisili, tanggal_lahir, status_akun FROM pengguna WHERE LOWER(status_akun) = 'pending'";
    $params = [];
    if (!empty($_POST['search']['value'])) {
        $search = '%' . $_POST['search']['value'] . '%';
        $baseSql .= " AND (nama_lengkap LIKE :q OR no_hp LIKE :q OR alamat_domisili LIKE :q)";
        $params[':q'] = $search;
    }

    // Map DataTables column index -> database column for ordering
    // DataTables columns: 0=No,1=NoHP,2=Nama,3=Alamat,4=TglLahir,5=Status,6=Aksi
    $cols = [1 => 'no_hp', 2 => 'nama_lengkap', 3 => 'alamat_domisili', 4 => 'tanggal_lahir', 5 => 'status_akun'];
    $orderCol = 'nama_lengkap';
    $orderDir = 'ASC';
    if (isset($_POST['order'][0]['column'])) {
        $c = intval($_POST['order'][0]['column']);
        if (isset($cols[$c])) $orderCol = $cols[$c];
    }
    if (isset($_POST['order'][0]['dir']) && in_array(strtoupper($_POST['order'][0]['dir']), ['ASC', 'DESC'])) $orderDir = strtoupper($_POST['order'][0]['dir']);

    $sql = $baseSql . " ORDER BY $orderCol $orderDir LIMIT :start, :length";

    if (!isset($connection)) throw new RuntimeException('db_unavailable');
    $stmt = $connection->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count filtered (search) results
    $countSql = "SELECT COUNT(*) FROM pengguna WHERE LOWER(status_akun) = 'pending'" . (!empty($_POST['search']['value']) ? " AND (nama_lengkap LIKE :q OR no_hp LIKE :q OR alamat_domisili LIKE :q)" : '');
    $cntStmt = $connection->prepare($countSql);
    if (!empty($_POST['search']['value'])) $cntStmt->bindValue(':q', '%' . $_POST['search']['value'] . '%');
    $cntStmt->execute();
    $filteredTotal = intval($cntStmt->fetchColumn());

    // Count total (no search) so DataTables can paginate correctly
    $totalSql = "SELECT COUNT(*) FROM pengguna WHERE LOWER(status_akun) = 'pending'";
    $totalStmt = $connection->prepare($totalSql);
    $totalStmt->execute();
    $totalRecords = intval($totalStmt->fetchColumn());

    // helper: format phone locally (convert leading 62 to 0)
    $formatPhone = function ($hp) {
        $s = preg_replace('/[^0-9+]/', '', (string)$hp);
        if ($s === '') return '';
        if ($s[0] === '+') $s = substr($s,1);
        if (strncmp($s, '62', 2) === 0) return '0' . substr($s,2);
        return $s;
    };

    $data = [];
    $no = $start + 1;
    foreach ($rows as $row) {
        $sub = [];
        // No
        $sub[] = '<div align="center">' . $no++ . '</div>';
        // Nomor HP (local format)
        $sub[] = htmlspecialchars($formatPhone($row['no_hp']), ENT_QUOTES, 'UTF-8');
        // Nama
        $sub[] = htmlspecialchars($row['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8');
        // Alamat
        $sub[] = htmlspecialchars($row['alamat_domisili'] ?? '-', ENT_QUOTES, 'UTF-8');
        // Tgl Lahir
        $sub[] = htmlspecialchars($row['tanggal_lahir'] ?? '-', ENT_QUOTES, 'UTF-8');
        // Status (with badge)
        $st = htmlspecialchars($row['status_akun'] ?? '', ENT_QUOTES, 'UTF-8');
        $stuc = strtoupper(trim($st));
        $badge = 'bg-secondary';
        if (strpos($stuc, 'PEND') !== false) $badge = 'bg-warning';
        else if (strpos($stuc, 'APPROV') !== false || strpos($stuc, 'TER') !== false || strpos($stuc, 'AKTIF') !== false) $badge = 'bg-success';
        else if (strpos($stuc, 'DITOLAK') !== false || strpos($stuc, 'REJECT') !== false) $badge = 'bg-danger';
        $sub[] = '<span class="badge ' . $badge . '">' . $st . '</span>';

        // Actions
        // Actions: three-dot dropdown with Detail / Terima / Tolak
        $actions  = '<div class="dropdown" align="center">';
        $actions .= '<button class="btn btn-sm btn-light dropdown-toggle" type="button" id="actionMenu' . intval($row['id']) . '" data-bs-toggle="dropdown" aria-expanded="false">⋮</button>';
        $actions .= '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionMenu' . intval($row['id']) . '">';
        $actions .= '<li><a class="dropdown-item" href="#" onclick="showUserDetailFromRekap(event,this)" data-id="' . intval($row['id']) . '">Detail</a></li>';
        $actions .= '<li><a class="dropdown-item" href="#" onclick="approveUser(' . intval($row['id']) . ')">Terima Aktivasi</a></li>';
        $actions .= '<li><a class="dropdown-item text-danger" href="#" onclick="rejectUser(' . intval($row['id']) . ')">Tolak Aktivasi</a></li>';
        $actions .= '</ul>';
        $actions .= '</div>';
        $sub[] = $actions;

        $data[] = $sub;
    }

    $out = ['draw' => $draw, 'recordsTotal' => $totalRecords, 'recordsFiltered' => $filteredTotal, 'data' => $data];
    // Debug: log number of rows returned to help diagnose empty table issues
    @file_put_contents(__DIR__ . '/fetch_pending_users_ok.log', date('c') . " rows=" . count($rows) . " total={$totalRecords} filtered={$filteredTotal}\n", FILE_APPEND);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Exception $e) {
    // Log error for local debugging (do not expose details to client)
    @file_put_contents(__DIR__ . '/fetch_pending_users_error.log', date('c') . " " . $e->getMessage() . "\n", FILE_APPEND);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'server_error'], JSON_UNESCAPED_UNICODE);
    exit;
}
