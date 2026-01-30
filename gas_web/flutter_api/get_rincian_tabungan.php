<?php
// API: Get rincian tabungan per jenis
// Params: id_tabungan (GET/POST)
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
include 'connection.php';
$id_tabungan = isset($_REQUEST['id_tabungan']) ? trim($_REQUEST['id_tabungan']) : '';
if ($id_tabungan === '') { echo json_encode(["success"=>false,"message"=>"id_tabungan wajib diisi"]); exit(); }
if (empty($connect)) { echo json_encode(["success"=>false,"message"=>"Internal server error"]); exit(); }
try {
    // Static list of types to ensure all appear even if zero
    $jenisList = [
        'Tabungan Reguler',
        'Tabungan Pelajar',
        'Tabungan Lebaran',
        'Tabungan Qurban',
        'Tabungan Aqiqah',
        'Tabungan Umroh',
        'Tabungan Investasi'
    ];

    // Try to resolve internal pengguna.id so we can compute outgoing (tabungan_keluar) correctly
    $user_id = null;
    $id_safe = $connect->real_escape_string($id_tabungan);
    $possibleCols = ['id_tabungan','username','no_hp','id','id_anggota'];
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

    // detect which column stores jenis name to avoid schema errors
    $jenisNameCol = null;
    $chkNama = $connect->query("SHOW COLUMNS FROM jenis_tabungan LIKE 'nama'");
    $chkNamaJenis = $connect->query("SHOW COLUMNS FROM jenis_tabungan LIKE 'nama_jenis'");
    if ($chkNama && $chkNama->num_rows > 0) $jenisNameCol = 'nama';
    elseif ($chkNamaJenis && $chkNamaJenis->num_rows > 0) $jenisNameCol = 'nama_jenis';

    if ($jenisNameCol === null) {
        @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_rincian_tabungan] WARNING: jenis name column not found, outgoing mapping will be skipped\n", FILE_APPEND);
    }

    $data = [];
    foreach ($jenisList as $jname) {
        // incoming: sum of tabungan_masuk.jumlah per jenis (preferred). Fall back to mulai_nabung if needed.
        $totalIn = 0; $totalOut = 0;
        $ct_in = $connect->query("SHOW TABLES LIKE 'tabungan_masuk'");
        if ($ct_in && $ct_in->num_rows > 0) {
            // detect status column in tabungan_masuk
            $has_status = false;
            $c = $connect->query("SHOW COLUMNS FROM tabungan_masuk LIKE 'status'"); if ($c && $c->num_rows > 0) $has_status = true;
            $statusClause = $has_status ? " AND status = 'berhasil'" : '';

            // Try resolve by jenis id when possible
            $jenisId = null;
            if ($jenisNameCol !== null) {
                $jr = $connect->prepare("SELECT id FROM jenis_tabungan WHERE $jenisNameCol = ? LIMIT 1");
                if ($jr) { $jr->bind_param('s', $jname); $jr->execute(); $rjr = $jr->get_result(); if ($rjr && $rjr->num_rows>0) $jenisId = intval($rjr->fetch_assoc()['id']); $jr->close(); }

                // If exact match failed, try normalized candidate strings (strip 'Tabungan', split on ':' and '-')
                if ($jenisId === null) {
                    $candidates = [$jname, trim(preg_replace('/\btabungan\b/i','',$jname))];
                    if (strpos($jname, ':') !== false) {
                        foreach (explode(':', $jname) as $p) $candidates[] = trim($p);
                    }
                    if (strpos($jname, '-') !== false) {
                        foreach (explode('-', $jname) as $p) $candidates[] = trim($p);
                    }
                    foreach (array_values(array_unique(array_filter($candidates))) as $cand) {
                        if ($cand === '') continue;
                        $jr2 = $connect->prepare("SELECT id FROM jenis_tabungan WHERE $jenisNameCol = ? LIMIT 1");
                        if (!$jr2) continue;
                        $jr2->bind_param('s', $cand);
                        $jr2->execute();
                        $rjr2 = $jr2->get_result();
                        if ($rjr2 && $rjr2->num_rows > 0) {
                            $jenisId = intval($rjr2->fetch_assoc()['id']);
                            $jr2->close();
                            break;
                        }
                        $jr2->close();
                    }
                }
            }

            // prefer resolved pengguna.id for accuracy
            if ($user_id !== null) {
                if ($jenisId !== null) {
                    $stmtIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClause);
                    $stmtIn->bind_param('ii', $user_id, $jenisId);
                    if ($stmtIn) { $stmtIn->execute(); $rIn = $stmtIn->get_result()->fetch_assoc(); $totalIn = intval($rIn['total'] ?? 0); $stmtIn->close(); }
                } else {
                    // Could not resolve jenis id; do not aggregate all tabungan_masuk into this jenis to avoid duplicate totals
                    $totalIn = 0;
                }
            } else {
                // best-effort mapping by id_tabungan/username/no_hp
                if ($jenisId !== null) {
                    $sql = "SELECT COALESCE(SUM(m.jumlah),0) AS total FROM tabungan_masuk m WHERE m.id_pengguna IN (SELECT id FROM pengguna WHERE id_tabungan = ? OR username = ? OR no_hp = ? LIMIT 1) AND m.id_jenis_tabungan = ?" . $statusClause;
                    $q = $connect->prepare($sql);
                    if ($q) { $q->bind_param('sssi', $id_tabungan, $id_tabungan, $id_tabungan, $jenisId); $q->execute(); $rIn = $q->get_result()->fetch_assoc(); $totalIn = intval($rIn['total'] ?? 0); $q->close(); }
                } else {
                    // No jenis id resolved - do not query without jenis filter to avoid repeating totals
                    $totalIn = 0;
                }
            }
        } else {
            // legacy fallback to mulai_nabung (use status if present)
            $has_status = false; $c = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'status'"); if ($c && $c->num_rows>0) $has_status = true; $statusClause = $has_status ? " AND status = 'berhasil'" : '';
            $stmtIn = $connect->prepare("SELECT COALESCE(SUM(jumlah),0) AS total FROM mulai_nabung WHERE id_tabungan = ?" . $statusClause . " AND jenis_tabungan = ?");
            if ($stmtIn) { $stmtIn->bind_param('ss', $id_tabungan, $jname); $stmtIn->execute(); $rIn = $stmtIn->get_result()->fetch_assoc(); $totalIn = intval($rIn['total'] ?? 0); $stmtIn->close(); }
        }

        // outgoing: prefer mapping to internal user id and id_jenis_tabungan, else best-effort mapping
        if ($user_id !== null) {
            // If the `tabungan_keluar` table has a `status` column we only count APPROVED withdrawals
            $has_status_out = false;
            $chkStatusOut = $connect->query("SHOW COLUMNS FROM tabungan_keluar LIKE 'status'"); if ($chkStatusOut && $chkStatusOut->num_rows > 0) $has_status_out = true;
            $statusClauseOut = $has_status_out ? " AND status = 'approved'" : "";

            $sqlOut = "SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_keluar WHERE id_pengguna = ? AND id_jenis_tabungan = ?" . $statusClauseOut;
            $qOut = $connect->prepare($sqlOut);
            if ($qOut) {
                    // try find jenis id for this label using the detected name column
                    $jenisId = null;
                    if ($jenisNameCol !== null) {
                        $candidates = [$jname, trim(preg_replace('/\btabungan\b/i','',$jname))];
                        if (strpos($jname, ':') !== false) {
                            foreach (explode(':', $jname) as $p) $candidates[] = trim($p);
                        }
                        if (strpos($jname, '-') !== false) {
                            foreach (explode('-', $jname) as $p) $candidates[] = trim($p);
                        }
                        foreach (array_values(array_unique(array_filter($candidates))) as $cand) {
                            if ($cand === '') continue;
                            $jr = $connect->prepare("SELECT id FROM jenis_tabungan WHERE $jenisNameCol = ? LIMIT 1");
                            if (!$jr) continue;
                            $jr->bind_param('s', $cand);
                            $jr->execute(); $rjr = $jr->get_result(); if ($rjr && $rjr->num_rows > 0) { $jenisId = intval($rjr->fetch_assoc()['id']); $jr->close(); break; }
                            $jr->close();
                        }
                    }
                    if ($jenisId !== null) {
                        $qOut->bind_param('ii', $user_id, $jenisId);
                        $qOut->execute(); $rOut = $qOut->get_result()->fetch_assoc(); $totalOut = intval($rOut['total'] ?? 0); $qOut->close();
                    }
                }
        } else {
            // best-effort: if we know the jenis name column, use it in the subquery, else skip outgoing mapping
            if ($jenisNameCol !== null) {
                $sql = "SELECT COALESCE(SUM(jumlah),0) AS total FROM tabungan_keluar WHERE id_pengguna IN (SELECT id FROM pengguna WHERE id_tabungan = ? OR username = ? OR no_hp = ? LIMIT 1) AND id_jenis_tabungan IN (SELECT id FROM jenis_tabungan WHERE $jenisNameCol = ? LIMIT 1)";
                $qOut2 = $connect->prepare($sql);
                if ($qOut2) {
                    $qOut2->bind_param('ssss', $id_tabungan, $id_tabungan, $id_tabungan, $jname);
                    $qOut2->execute(); $rOut = $qOut2->get_result()->fetch_assoc(); $totalOut = intval($rOut['total'] ?? 0); $qOut2->close();
                }
            } else {
                // no reliable jenis name column; skip mapping outgoing (default to 0)
                $totalOut = 0;
            }
        }

        $balance = $totalIn;
        // Do NOT subtract $totalOut because approved withdrawals are already deducted from tabungan_masuk
        // during the approval process.
        if ($balance < 0) $balance = 0;
        // Try to find jenis id if possible. Use normalization (strip 'Tabungan') and try several candidates
        $jenisId = null;
        if ($jenisNameCol !== null) {
            $candidates = [$jname, trim(preg_replace('/\btabungan\b/i','',$jname))];
            if (strpos($jname, ':') !== false) {
                foreach (explode(':', $jname) as $p) $candidates[] = trim($p);
            }
            if (strpos($jname, '-') !== false) {
                foreach (explode('-', $jname) as $p) $candidates[] = trim($p);
            }
            // try each candidate until a match is found
            foreach (array_values(array_unique(array_filter($candidates))) as $cand) {
                if ($cand === '') continue;
                $jr = $connect->prepare("SELECT id FROM jenis_tabungan WHERE $jenisNameCol = ? LIMIT 1");
                if (!$jr) continue;
                $jr->bind_param('s', $cand);
                $jr->execute();
                $rjr = $jr->get_result();
                if ($rjr && $rjr->num_rows > 0) {
                    $jenisId = intval($rjr->fetch_assoc()['id']);
                    $jr->close();
                    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_rincian_tabungan] RESOLVED jenis id for '$jname' using candidate '$cand' => $jenisId\n", FILE_APPEND);
                    break;
                }
                $jr->close();
            }
        }
        $data[] = [
            'id' => $jenisId,
            // New explicit key to satisfy mobile client expectations
            'id_jenis_tabungan' => $jenisId,
            'jenis' => $jname,
            'total' => $balance,
            'total_masuk' => $totalIn,
            'total_keluar' => $totalOut
        ];
        if ($jenisId === null) {
            @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_rincian_tabungan] WARNING: jenis id for '$jname' not found\n", FILE_APPEND);
        } else {
            @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_rincian_tabungan] INFO: jenis id for '$jname' => $jenisId\n", FILE_APPEND);
        }
    }

    echo json_encode(["success"=>true, "data"=>$data]);
    exit();
} catch (Exception $e) {
    @file_put_contents(__DIR__.'/api_debug.log', date('c')." [get_rincian_tabungan] Error: ". $e->getMessage()."\n", FILE_APPEND);
    echo json_encode(["success"=>false,"message"=>"Internal server error"]);
    exit();
}
