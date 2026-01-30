<?php
// api/pinjaman/list.php
// List pinjaman (loan) applications for a given user
// - Accepts GET or POST
// - Parameters:
//   - id_pengguna (required)
//   - status (optional, filter by status)
//   - limit (optional, default 50, max 100)
//   - offset (optional, default 0)
// - Uses PDO prepared statements (require '../../config/database.php')
// - Returns JSON only

declare(strict_types=1);

// Hide PHP errors from responses
ini_set('display_errors', '0');
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
// header('Access-Control-Allow-Origin: *'); // enable during development if needed

try {
    // Accept GET or POST
    $method = $_SERVER['REQUEST_METHOD'];
    $input = [];
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        if ($raw !== null) {
            $raw = trim((string)$raw);
            $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
        }
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'JSON decode error: ' . json_last_error_msg()]);
                exit;
            }
            $input = is_array($json) ? $json : [];
        } else {
            // fallback to form data
            $input = $_POST;
        }
    } else {
        // GET
        $input = $_GET;
    }

    // Optional raw params
    $id_pengguna = $input['id_pengguna'] ?? null;
    $status = isset($input['status']) ? trim((string)$input['status']) : null;
    $limit = isset($input['limit']) ? (int)$input['limit'] : 50;
    $offset = isset($input['offset']) ? (int)$input['offset'] : 0;

    // === Authentication enforcement ===
    // This endpoint only returns loans for the authenticated caller.
    // We accept either a PHP session (web) or an Authorization: Bearer <token> header.
    if (session_status() === PHP_SESSION_NONE) session_start();

    $auth_user_id = null;
    if (isset($_SESSION['id_user']) && is_numeric($_SESSION['id_user'])) {
        $auth_user_id = (int)$_SESSION['id_user'];
    }

    // If no session-based user, attempt Bearer token lookup using mysqli and $con
    if ($auth_user_id === null) {
        // helper to get bearer token
        function get_bearer_token_from_request() {
            if (function_exists('getallheaders')) {
                $hdrs = getallheaders();
                $ah = $hdrs['Authorization'] ?? ($hdrs['authorization'] ?? null);
                if ($ah && preg_match('/Bearer\s+(.+)/i', $ah, $m)) return trim($m[1]);
            }
            $ah = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
            if ($ah && preg_match('/Bearer\s+(.+)/i', $ah, $m)) return trim($m[1]);
            if (function_exists('apache_request_headers')) {
                $hdrs = apache_request_headers();
                $ah = $hdrs['Authorization'] ?? ($hdrs['authorization'] ?? null);
                if ($ah && preg_match('/Bearer\s+(.+)/i', $ah, $m)) return trim($m[1]);
            }
            if (!empty($_GET['access_token'])) return trim((string)$_GET['access_token']);
            if (!empty($_POST['access_token'])) return trim((string)$_POST['access_token']);
            return null;
        }

        $token = get_bearer_token_from_request();
        if ($token !== null && $token !== '') {
            // require DB connection early for token lookup
            require_once __DIR__ . '/../../config/database.php';
            if (isset($con) && ($con instanceof mysqli)) {
                // Check if api_token column exists
                $colSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengguna' AND COLUMN_NAME = 'api_token' LIMIT 1";
                $colRes = mysqli_query($con, $colSql);
                if ($colRes) {
                    $colRow = mysqli_fetch_assoc($colRes);
                    if ($colRow && (int)$colRow['cnt'] > 0) {
                        $stmt = mysqli_prepare($con, "SELECT id FROM pengguna WHERE api_token = ? LIMIT 1");
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, 's', $token);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_bind_result($stmt, $foundId);
                            mysqli_stmt_fetch($stmt);
                            mysqli_stmt_close($stmt);
                            if (!empty($foundId)) $auth_user_id = (int)$foundId;
                        } else {
                            error_log('pinjaman/list.php: failed to prepare token lookup: ' . mysqli_error($con));
                        }
                    }
                } else {
                    error_log('pinjaman/list.php: failed to query INFORMATION_SCHEMA: ' . mysqli_error($con));
                }
            }
        }
    }

    if ($auth_user_id === null) {
        http_response_code(401);
        echo json_encode(['status' => false, 'message' => 'Autentikasi diperlukan. Sertakan cookie sesi atau header Authorization: Bearer <token>']);
        exit;
    }

    // Force id_pengguna to the authenticated user (ignore client-supplied id_pengguna)
    $id_pengguna = $auth_user_id;

    // Validate id_pengguna (now from auth)
    if (!is_numeric($id_pengguna) || $id_pengguna <= 0) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'id_pengguna dari autentikasi tidak valid.']);
        exit;
    }
    $id_pengguna = (int)$id_pengguna;

    // Validate limit/offset
    if ($limit <= 0) $limit = 50;
    if ($limit > 100) $limit = 100;
    if ($offset < 0) $offset = 0;

    // Validate optional status if provided (to avoid SQL injection via column values)
    $allowedStatuses = ['pending','approved','rejected','berjalan','lunas'];
    if ($status !== null && $status !== '') {
        if (!in_array($status, $allowedStatuses, true)) {
            http_response_code(400);
            echo json_encode(['status' => false, 'message' => 'Status tidak valid.']);
            exit;
        }
    }

    // Database (use mysqli procedural $con from config)
    require_once __DIR__ . '/../../config/database.php';
    if (!isset($con) || !($con instanceof mysqli)) {
        error_log('Database connection ($con) missing in pinjaman/list.php');
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Server database belum terkonfigurasi.']);
        exit;
    }

    // Build query with positional params
    $sql = "SELECT id, id_pengguna, jenis_pinjaman, jumlah_pinjaman, tenor, tujuan_penggunaan, cicilan_per_bulan, total_pinjaman, status, catatan_admin, created_at
            FROM pinjaman
            WHERE id_pengguna = ?";

    $types = 'i';
    $params = [$id_pengguna];

    if ($status !== null && $status !== '') {
        $sql .= " AND status = ?";
        $types .= 's';
        $params[] = $status;
    }

    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $types .= 'ii';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        error_log('pinjaman/list.php: prepare failed: ' . mysqli_error($con));
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Gagal mengambil data pinjaman.']);
        exit;
    }

    // bind params dynamically
    $bind_names = [];
    $bind_names[] = $types;
    foreach ($params as $i => $value) {
        $bind_name = 'bind' . $i;
        $$bind_name = $value;
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $bind_names));

    if (!mysqli_stmt_execute($stmt)) {
        error_log('pinjaman/list.php: execute failed: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Gagal mengambil data pinjaman.']);
        exit;
    }

    // fetch result set (requires mysqlnd), fallback to bind_result approach if not available
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result !== false) {
        while ($r = mysqli_fetch_assoc($result)) {
            $rows[] = $r;
        }
        mysqli_free_result($result);
    } else {
        // fallback: bind_result
        mysqli_stmt_store_result($stmt);
        $meta = mysqli_stmt_result_metadata($stmt);
        if ($meta) {
            $fields = [];
            $row = [];
            while ($field = mysqli_fetch_field($meta)) {
                $fields[] = &$row[$field->name];
            }
            call_user_func_array('mysqli_stmt_bind_result', array_merge([$stmt], $fields));
            while (mysqli_stmt_fetch($stmt)) {
                $rec = [];
                foreach ($row as $k => $v) $rec[$k] = $v;
                $rows[] = $rec;
            }
            mysqli_free_result($meta);
        }
    }
    mysqli_stmt_close($stmt);

    // Optionally fetch total count (without limit) for pagination info using mysqli
    $countSql = "SELECT COUNT(*) as total FROM pinjaman WHERE id_pengguna = ?";
    $countTypes = 'i';
    $countParams = [$id_pengguna];
    if ($status !== null && $status !== '') {
        $countSql .= " AND status = ?";
        $countTypes .= 's';
        $countParams[] = $status;
    }
    $cntStmt = mysqli_prepare($con, $countSql);
    if ($cntStmt) {
        $bind_names = [];
        $bind_names[] = $countTypes;
        foreach ($countParams as $i => $v) {
            $bn = 'cbind' . $i;
            $$bn = $v;
            $bind_names[] = &$$bn;
        }
        call_user_func_array('mysqli_stmt_bind_param', array_merge([$cntStmt], $bind_names));
        if (mysqli_stmt_execute($cntStmt)) {
            $cntRes = mysqli_stmt_get_result($cntStmt);
            if ($cntRes !== false) {
                $cntRow = mysqli_fetch_assoc($cntRes);
                $total = isset($cntRow['total']) ? (int)$cntRow['total'] : count($rows);
                mysqli_free_result($cntRes);
            } else {
                // fallback
                mysqli_stmt_bind_result($cntStmt, $totalCount);
                mysqli_stmt_fetch($cntStmt);
                $total = isset($totalCount) ? (int)$totalCount : count($rows);
            }
        } else {
            error_log('pinjaman/list.php: count execute failed: ' . mysqli_stmt_error($cntStmt));
            $total = count($rows);
        }
        mysqli_stmt_close($cntStmt);
    } else {
        error_log('pinjaman/list.php: count prepare failed: ' . mysqli_error($con));
        $total = count($rows);
    }

    http_response_code(200);
    echo json_encode(['status' => true, 'data' => $rows, 'total' => $total]);
    exit;

} catch (Exception $ex) {
    error_log('pinjaman/list.php Exception: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Terjadi kesalahan. Silakan coba lagi nanti.']);
    exit;
}
