<?php
/**
 * fetch_keluar_admin.php
 * Server-side backend for DataTables (Pencairan Tabungan - Admin)
 * - Strict schema: only uses columns defined in spec
 * - Defaults to showing status = 'pending'
 * - Returns valid DataTables JSON: { draw, recordsTotal, recordsFiltered, data }
 * - Uses mysqli prepared statements and returns JSON on any error
 */

header('Content-Type: application/json; charset=utf-8');
// Do not leak PHP warnings/notices
ini_set('display_errors', '0');
error_reporting(0);
ob_start();

try {
    require_once dirname(__DIR__) . '/koneksi/config.php';

    // Choose mysqli connection object
    $db = null;
    if (isset($koneksi) && $koneksi instanceof mysqli) $db = $koneksi;
    elseif (isset($connect) && $connect instanceof mysqli) $db = $connect;
    elseif (isset($con) && $con instanceof mysqli) $db = $con;
    else throw new RuntimeException('db_unavailable');

    // Basic DataTables params
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    $start = isset($_POST['start']) ? max(0, intval($_POST['start'])) : 0;
    $length = isset($_POST['length']) ? intval($_POST['length']) : 10;
    $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

    // Status filter: accept 'pending', 'approved', 'rejected', or 'all'. Default to 'all' to match UI behavior.
    $statusFilter = isset($_POST['status']) && $_POST['status'] !== '' ? trim($_POST['status']) : 'all';
    $statusFilter = strtolower($statusFilter);
    $allowedStatuses = ['pending','approved','rejected','all'];
    if (!in_array($statusFilter, $allowedStatuses, true)) {
        $statusFilter = 'all';
    }

    // Ensure required tables exist
    $r1 = $db->query("SHOW TABLES LIKE 'tabungan_keluar'");
    $r2 = $db->query("SHOW TABLES LIKE 'pengguna'");
    $r3 = $db->query("SHOW TABLES LIKE 'jenis_tabungan'");
    if (!($r1 && $r1->num_rows > 0 && $r2 && $r2->num_rows > 0 && $r3 && $r3->num_rows > 0)) {
        $out = ['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'missing_table'];
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Strict implementation: query ONLY `tabungan_keluar` (current schema)
    // Build WHERE clauses
    $whereBase = '';
    $whereSearch = '';

    $bindParams = []; // positional array for mysqli
    $bindTypes = '';

    // Enforce status filter (case-insensitive match) unless explicitly 'all'
    if ($statusFilter !== 'all') {
        $whereBase = 'WHERE LOWER(tk.status) = LOWER(?)';
        $bindParams[] = $statusFilter;
        $bindTypes .= 's';
    }

    // Global search across user name, jenis, and id
    if ($searchValue !== '') {
        $whereSearch = ($whereBase === '' ? 'WHERE ' : ' AND ') . '(p.nama_lengkap LIKE ? OR jt.nama_jenis LIKE ? OR tk.id = ?)';
        $bindParams[] = '%' . $searchValue . '%';
        $bindParams[] = '%' . $searchValue . '%';
        $bindParams[] = ctype_digit($searchValue) ? intval($searchValue) : -1;
        $bindTypes .= 'ssi';
    }

    // Compose combined WHERE SQL
    $whereSql = trim(($whereBase === '' ? '' : $whereBase) . ($whereSearch === '' ? '' : $whereSearch));

    // ORDER BY mapping (DataTables column index -> DB column)
    // Table columns (client-side): 0:No, 1:Tanggal Pengajuan, 2:Nama Pengguna, 3:Jenis Tabungan, 4:Jumlah, 5:Status, 6:Aksi
    $allowedOrder = [
        1 => 'tk.created_at',
        2 => 'p.nama_lengkap',
        3 => 'jt.nama_jenis',
        4 => 'tk.jumlah',
        5 => 'tk.status'
    ];
    $orderColIndex = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 1;
    $orderDir = (isset($_POST['order'][0]['dir']) && strtolower($_POST['order'][0]['dir']) === 'desc') ? 'DESC' : 'ASC';
    $orderBy = isset($allowedOrder[$orderColIndex]) ? $allowedOrder[$orderColIndex] : 'tk.created_at';

    // Determine limit/offset
    $len = ($length == -1) ? 1000000 : max(1, $length);
    $offset = max(0, $start);

    // Attempt to use PDO if it was provided by config
    $pdo = null;
    if (isset($pdo_global) && $pdo_global instanceof PDO) {
        $pdo = $pdo_global; // legacy named var in some configs
    } elseif (extension_loaded('pdo_mysql')) {
        // try building PDO from constants if available
        if (defined('DB_HOST') && defined('DB_DATABASE') && defined('DB_USERNAME')) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_DATABASE . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USERNAME, defined('DB_PASSWORD') ? DB_PASSWORD : '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
    }

    // Use PDO if available otherwise mysqli. Strictly target tabungan_keluar schema.
    $rows = [];

    // 1) recordsTotal (count without search)
    $sqlTotal = "SELECT COUNT(*) FROM tabungan_keluar tk JOIN pengguna p ON p.id = tk.id_pengguna JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan " . ($whereBase === '' ? '' : $whereBase);

    if ($pdo instanceof PDO) {
        $stmt = $pdo->prepare($sqlTotal);
        // bind params for total (status only)
        if (!empty($bindParams)) {
            // bind first N params (bindParams may include search params; for total we only want status param)
            $bindIndex = 1;
            foreach ($bindParams as $i => $v) {
                // Only bind status (first param) for total
                if ($i > 0 && $searchValue !== '') break;
                $stmt->bindValue($bindIndex++, $v, (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR));
            }
        }
        $stmt->execute();
        $recordsTotal = (int)$stmt->fetchColumn();

        // recordsFiltered (apply search if present)
        if ($searchValue !== '') {
            $sqlFiltered = "SELECT COUNT(*) FROM tabungan_keluar tk JOIN pengguna p ON p.id = tk.id_pengguna JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan " . $whereSql;
            $stmt = $pdo->prepare($sqlFiltered);
            $idx = 1;
            foreach ($bindParams as $v) { $stmt->bindValue($idx++, $v, (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR)); }
            $stmt->execute(); $recordsFiltered = (int)$stmt->fetchColumn();
        } else {
            $recordsFiltered = $recordsTotal;
        }

        // Data query
        $sqlData = "SELECT tk.id, p.nama_lengkap, jt.nama_jenis, tk.jumlah, tk.keterangan, tk.status, tk.created_at
                    FROM tabungan_keluar tk
                    JOIN pengguna p ON p.id = tk.id_pengguna
                    JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                    " . $whereSql . " ORDER BY $orderBy $orderDir LIMIT :offset, :limit";
        $stmt = $pdo->prepare($sqlData);
        $idx = 1;
        foreach ($bindParams as $v) { $stmt->bindValue($idx++, $v, (is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR)); }
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$len, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // mysqli path
        $stmt = $db->prepare($sqlTotal);
        if ($stmt === false) throw new RuntimeException('DB prepare failed: ' . $db->error);

        // bind only status for total (if present)
        if (!empty($bindParams) && $whereBase !== '') {
            $t = substr($bindTypes, 0, 1);
            $vals = [$bindParams[0]];
            $stmt->bind_param($t, ...$vals);
        }
        $stmt->execute(); $stmt->bind_result($countTotal); $stmt->fetch(); $stmt->close(); $recordsTotal = intval($countTotal ?? 0);

        // recordsFiltered
        if ($searchValue !== '') {
            $sqlFiltered = "SELECT COUNT(*) FROM tabungan_keluar tk JOIN pengguna p ON p.id = tk.id_pengguna JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan " . $whereSql;
            $stmt = $db->prepare($sqlFiltered);
            if ($stmt === false) throw new RuntimeException('DB prepare failed: ' . $db->error);
            // bind all search params
            if (!empty($bindParams)) {
                $stmt->bind_param($bindTypes, ...$bindParams);
            }
            $stmt->execute(); $stmt->bind_result($countFiltered); $stmt->fetch(); $stmt->close(); $recordsFiltered = intval($countFiltered ?? 0);
        } else {
            $recordsFiltered = $recordsTotal;
        }

        // data query
        $sqlData = "SELECT tk.id, p.nama_lengkap, jt.nama_jenis, tk.jumlah, tk.keterangan, tk.status, tk.created_at
                    FROM tabungan_keluar tk
                    JOIN pengguna p ON p.id = tk.id_pengguna
                    JOIN jenis_tabungan jt ON jt.id = tk.id_jenis_tabungan
                    " . $whereSql . " ORDER BY $orderBy $orderDir LIMIT ?, ?";
        $stmt = $db->prepare($sqlData);
        if ($stmt === false) throw new RuntimeException('DB prepare failed: ' . $db->error);

        // bind params (search params first if any) then offset/limit
        $bindVals = $bindParams;
        $bindT = $bindTypes;
        $bindVals[] = $offset; $bindVals[] = $len; $bindT .= 'ii';
        if (!empty($bindVals)) {
            $stmt->bind_param($bindT, ...$bindVals);
        }
        $stmt->execute(); $res = $stmt->get_result(); $rows = $res->fetch_all(MYSQLI_ASSOC); $stmt->close();
    }

    // Build response rows per spec (final columns:
    // No | Tanggal Pengajuan | Nama Pengguna | Jenis Tabungan | Jumlah Pencairan | Status | Aksi
    $data = [];
    $seq = $start + 1;
    foreach ($rows as $r) {
        $tanggalRaw = isset($r['created_at']) ? $r['created_at'] : (isset($r['tanggal']) ? $r['tanggal'] : null);
        // display dd-mm-yyyy
        $tanggalDisplay = $tanggalRaw ? date('d-m-Y', strtotime($tanggalRaw)) : '';
        $name = isset($r['nama_lengkap']) ? $r['nama_lengkap'] : (isset($r['nama']) ? $r['nama'] : '');
        $jenis = isset($r['nama_jenis']) ? $r['nama_jenis'] : 'Reguler';
        $jumlahRaw = isset($r['jumlah']) ? $r['jumlah'] : 0;
        $formattedJumlah = 'Rp ' . number_format((float)$jumlahRaw, 0, ',', '.');
        $statusVal = isset($r['status']) ? strtolower($r['status']) : 'pending';
        // Map status values to Indonesian labels
        $statusMap = [
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'pending'  => 'Menunggu'
        ];
        $statusLabel = isset($statusMap[$statusVal]) ? $statusMap[$statusVal] : ucfirst($statusVal);
        // Provide a 'no' identifier compatible with approve_penarikan expectations
        $noVal = null;
        if (isset($r['no_keluar']) && !empty($r['no_keluar'])) {
            $noVal = $r['no_keluar'];
        } elseif (isset($r['created_at']) && isset($r['id'])) {
            $ts = strtotime($r['created_at']);
            if ($ts === false) $ts = time();
            $noVal = 'TK-' . date('YmdHis', $ts) . '-' . intval($r['id']);
        }
        $actionPayload = json_encode(['id' => (int)($r['id'] ?? 0), 'status' => $statusVal, 'no' => $noVal]);

        $data[] = [
            (string)$seq++,            // No (index)
            (string)$tanggalDisplay,   // Tanggal Pengajuan (dd-mm-yyyy)
            (string)$name,             // Nama Pengguna
            (string)$jenis,            // Jenis Tabungan
            (string)$formattedJumlah,  // Jumlah Pencairan (formatted rupiah)
            (string)$statusLabel,      // Status
            $actionPayload            // Aksi (JSON for frontend)
        ];
    }

    $out = ['draw' => intval($draw), 'recordsTotal' => intval($recordsTotal), 'recordsFiltered' => intval($recordsFiltered), 'data' => $data];

    // Clean buffer and output JSON
    $extra = '';
    if (ob_get_level()) $extra = ob_get_clean();
    if (!empty($extra)) {
        @file_put_contents(__DIR__ . '/../logs/fetch_keluar_admin_output_debug.log', date('c') . " stray_output:\n" . $extra . "\n", FILE_APPEND);
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;


} catch (Throwable $e) {
    $extra = '';
    if (ob_get_level()) $extra = ob_get_clean();
    @file_put_contents(__DIR__ . '/../logs/fetch_keluar_admin_error.log', date('c') . " ajax error: " . $e->getMessage() . "\nextra:\n" . $extra . "\n", FILE_APPEND);
    $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
    $resp = ['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'server_error'];
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}
