<?php
// API: Get Saldo (from mulai_nabung)
// Method: GET
// Params: id_tabungan
// Returns: { success: true, saldo: <int> } or { success: false, message: ... }

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'connection.php';

// Read parameter from GET
$id_tabungan = isset($_GET['id_tabungan']) ? trim($_GET['id_tabungan']) : '';

if ($id_tabungan === '') {
    echo json_encode(["success" => false, "message" => "id_tabungan wajib diisi"]);
    exit();
}

if (empty($connect)) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_saldo_tabungan] Missing DB connection\n", FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Internal server error"]);
    exit();
}

try {
    // Compute saldo from ledger: SUM(tabungan_masuk.jumlah) - SUM(tabungan_keluar.jumlah)
    $totalIn = 0;
    $ct_in = $connect->query("SHOW TABLES LIKE 'tabungan_masuk'");
    if ($ct_in && $ct_in->num_rows > 0) {
        $has_status = false;
        $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'status'"); if ($c && $c->num_rows > 0) $has_status = true;
        $statusClause = $has_status ? " AND status = 'berhasil'" : '';

        // Resolve internal user id for accurate lookup
        $user_id = null;
        $id_safe = $connect->real_escape_string($id_tabungan);
        $possibleCols = ['id_tabungan','username','no_hp','id','id_pengguna'];
        $whereParts = [];
        foreach ($possibleCols as $col) {
            $chk = $connect->query("SHOW COLUMNS FROM pengguna LIKE '$col'");
            if ($chk && $chk->num_rows > 0) $whereParts[] = "$col = '$id_safe'";
        }
        if (!empty($whereParts)) {
            $where = implode(' OR ', $whereParts);
            $q = $connect->query("SELECT id FROM pengguna WHERE ($where) LIMIT 1");
            if ($q && $q->num_rows > 0) $user_id = intval($q->fetch_assoc()['id']);
        }

        if ($user_id !== null) {
            $stmtIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total_in FROM tabungan_masuk WHERE id_pengguna = ?" . $statusClause);
            $stmtIn->bind_param('i', $user_id);
            $stmtIn->execute(); $resIn = $stmtIn->get_result(); $rowIn = $resIn->fetch_assoc(); $totalIn = intval($rowIn['total_in'] ?? 0); $stmtIn->close();
        } else {
            $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total_in FROM tabungan_masuk m WHERE m.id_pengguna IN (SELECT id FROM pengguna WHERE id_tabungan = ? OR username = ? OR no_hp = ? LIMIT 1)" . $statusClause;
            $stmtIn2 = $connect->prepare($sql);
            if ($stmtIn2) {
                $stmtIn2->bind_param('sss', $id_tabungan, $id_tabungan, $id_tabungan);
                $stmtIn2->execute(); $resIn = $stmtIn2->get_result(); $rowIn = $resIn->fetch_assoc(); $totalIn = intval($rowIn['total_in'] ?? 0); $stmtIn2->close();
            }
        }
    } else {
        // fallback to mulai_nabung (legacy)
        $has_status = false;
        $c = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'status'"); if ($c && $c->num_rows > 0) $has_status = true;
        $statusClause = $has_status ? " AND status = 'berhasil'" : '';
        $sqlIn = "SELECT COALESCE(SUM(jumlah),0) AS total_in FROM mulai_nabung WHERE id_tabungan = ?" . $statusClause;
        if (!($stmtIn = $connect->prepare($sqlIn))) throw new Exception('Gagal prepare (in fallback): '.$connect->error);
        $stmtIn->bind_param('s', $id_tabungan);
        if (!$stmtIn->execute()) throw new Exception('Gagal execute (in fallback): '.$stmtIn->error);
        $resIn = $stmtIn->get_result();
        $rowIn = $resIn->fetch_assoc();
        $stmtIn->close();
        $totalIn = intval($rowIn['total_in'] ?? 0);
    }

    // Resolve user id for outgoing lookup
    $user_id = null;
    $id_safe = $connect->real_escape_string($id_tabungan);
    $possibleCols = ['id_tabungan','username','no_hp','id','id_pengguna'];
    $whereParts = [];
    foreach ($possibleCols as $col) {
        $chk = $connect->query("SHOW COLUMNS FROM pengguna LIKE '$col'");
        if ($chk && $chk->num_rows > 0) $whereParts[] = "$col = '$id_safe'";
    }
    if (!empty($whereParts)) {
        $where = implode(' OR ', $whereParts);
        $q = $connect->query("SELECT id FROM pengguna WHERE ($where) LIMIT 1");
        if ($q && $q->num_rows > 0) $user_id = intval($q->fetch_assoc()['id']);
    }

    $totalOut = 0;
    $ct = $connect->query("SHOW TABLES LIKE 'tabungan_keluar'");
    if ($ct && $ct->num_rows > 0) {
        if ($user_id !== null) {
            $has_status_col = false; $chkS = $connect->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'status'"); if ($chkS && $chkS->num_rows > 0) $has_status_col = true;
            $statusClause = $has_status_col ? " AND status = 'approved'" : "";
            $sqlOut = "SELECT COALESCE(SUM(jumlah),0) AS total_out FROM tabungan_keluar WHERE id_pengguna = ?" . $statusClause;
            $stmtOut = $connect->prepare($sqlOut);
            if ($stmtOut) {
                $stmtOut->bind_param('i', $user_id);
                $stmtOut->execute(); $rOut = $stmtOut->get_result()->fetch_assoc(); $totalOut = intval($rOut['total_out'] ?? 0); $stmtOut->close();
            }
        } else {
            $has_status_col = false; $chkS = $connect->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'status'"); if ($chkS && $chkS->num_rows > 0) $has_status_col = true;
            $statusClause = $has_status_col ? " AND status = 'approved'" : "";
            $sqlOut = "SELECT COALESCE(SUM(jumlah),0) AS total_out FROM tabungan_keluar WHERE id_pengguna IN (SELECT id FROM pengguna WHERE id_tabungan = ? OR username = ? OR no_hp = ? LIMIT 1)" . $statusClause;
            $stmtOut2 = $connect->prepare($sqlOut);
            if ($stmtOut2) {
                $stmtOut2->bind_param('sss', $id_tabungan, $id_tabungan, $id_tabungan);
                $stmtOut2->execute(); $rOut = $stmtOut2->get_result()->fetch_assoc(); $totalOut = intval($rOut['total_out'] ?? 0); $stmtOut2->close();
            }
        }
    }

    $saldo = $totalIn;
    // Do NOT subtract $totalOut because approved withdrawals are already deducted from tabungan_masuk
    // during the approval process. We only need to show what remains in tabungan_masuk.
    if ($saldo < 0) $saldo = 0;
    echo json_encode(["success" => true, "saldo" => $saldo]);
    exit();
} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [get_saldo_tabungan] Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Internal server error"]);
    exit();
}

