<?php
// admin/pinjaman_approval.php
// Admin page to approve/reject pinjaman (loan) requests
// - GET : renders HTML table of pending pinjaman
// - POST: handles approve/reject actions and returns JSON

declare(strict_types=1);

// Require auth and DB
require_once __DIR__ . '/../login/middleware/Auth.php';
if (!Auth::isAdmin()) {
    // Not authorized - redirect to login or show error
    header('Location: /tabungan_gas/login/');
    exit;
}

// Don't set JSON header globally; only send JSON for POST actions. For GET we'll render HTML.

// DB
require_once __DIR__ . '/../config/db.php';
if (!isset($con) || !($con instanceof mysqli)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Gagal', 'error' => 'Koneksi database gagal']);
        exit;
    } else {
        // Render page with message
        $dbError = true;
    }
}

// Debug log helper (optional)
function admin_log($msg) {
    $path = __DIR__ . '/debug.log';
    $line = date('Y-m-d H:i:s') . ' ' . $msg . "\n";
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

// If this is a POST action (approve/reject), handle and return JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure admin role (extra safety)
    if (!Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Gagal', 'error' => 'Akses ditolak']);
        exit;
    }

    // Read JSON body if Content-Type is application/json, else use POST form
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $data = [];
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $data = $decoded;
    } else {
        $data = $_POST;
    }

    $action = $data['action'] ?? null; // 'approve' or 'reject'
    $id = $data['id'] ?? null;
    $note = isset($data['note']) ? trim((string)$data['note']) : '';

    // Log action input
    admin_log("Action request: action={$action} id={$id} note=" . substr($note,0,200));

    if (!in_array($action, ['approve', 'reject'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Gagal', 'error' => 'Action tidak valid']);
        exit;
    }
    if (!is_numeric($id) || (int)$id <= 0) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Gagal', 'error' => 'ID tidak valid']);
        exit;
    }
    $id = (int)$id;

    // Check if approved_at column exists (optional)
    $hasApprovedAt = false;
    $colSql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pinjaman' AND COLUMN_NAME = 'approved_at'";
    $colRes = mysqli_query($con, $colSql);
    if ($colRes) {
        $colRow = mysqli_fetch_assoc($colRes);
        if ($colRow && (int)$colRow['cnt'] > 0) $hasApprovedAt = true;
    }

    // Build update SQL using prepared parameters (status, catatan_admin, id)
    $statusVal = ($action === 'approve') ? 'approved' : 'rejected';
    if ($action === 'approve' && $hasApprovedAt) {
        $sql = "UPDATE pinjaman SET status = ?, approved_at = NOW(), catatan_admin = ? WHERE id = ? AND status = 'pending'";
    } else {
        // approve without approved_at or reject
        $sql = "UPDATE pinjaman SET status = ?, catatan_admin = ? WHERE id = ? AND status = 'pending'";
    }

    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        $err = mysqli_error($con);
        admin_log("Prepare failed: {$err}");
        echo json_encode(['status' => false, 'message' => 'Gagal', 'error' => 'Prepare statement gagal: ' . $err]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'ssi', $statusVal, $note, $id);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        header('Content-Type: application/json; charset=utf-8');
        admin_log("Execute failed: {$err}");
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Gagal', 'error' => $err]);
        exit;
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected > 0) {
        // Try to fetch id_pengguna and amount for the pinjaman to notify the user
        $notified = false;
        $stmt2 = mysqli_prepare($con, "SELECT id_pengguna, COALESCE(jumlah_pinjaman, jumlah) AS amount FROM pinjaman WHERE id = ? LIMIT 1");
        if ($stmt2) {
            mysqli_stmt_bind_param($stmt2, 'i', $id);
            if (mysqli_stmt_execute($stmt2)) {
                mysqli_stmt_bind_result($stmt2, $pid_user, $pamount);
                if (mysqli_stmt_fetch($stmt2)) {
                    $pid_user = (int)$pid_user;
                    $pamount = (int)$pamount;
                    $title = ($action === 'approve') ? 'Pengajuan pinjaman disetujui' : 'Pengajuan pinjaman ditolak';
                    $amountStr = 'Rp ' . number_format($pamount, 0, ',', '.');
                    if ($action === 'approve') {
                        $message_n = 'Pengajuan pinjaman Anda sebesar ' . $amountStr . ' telah disetujui oleh admin.';
                    } else {
                        $message_n = 'Pengajuan pinjaman Anda sebesar ' . $amountStr . ' telah ditolak oleh admin.';
                        if (!empty($note)) $message_n .= ' Catatan: ' . trim($note);
                    }
                    if (file_exists(__DIR__ . '/../flutter_api/notif_helper.php')) {
                        require_once __DIR__ . '/../flutter_api/notif_helper.php';
                        if (function_exists('safe_create_notification')) {
                            @safe_create_notification($con, $pid_user, 'pinjaman', $title, $message_n, json_encode(['id' => $id, 'amount' => $pamount, 'action' => $action]));
                            $notified = true;
                        }
                    }
                }
            }
            mysqli_stmt_close($stmt2);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => true, 'message' => 'Pinjaman berhasil di' . ($action === 'approve' ? 'approve' : 'reject')]);
        admin_log("Action success: {$action} id={$id} affected={$affected} notified=" . ($notified?1:0));
        exit;
    }

    // Nothing changed (maybe ID not found or status not pending)
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    admin_log("Action no-change: {$action} id={$id}");
    echo json_encode(['status' => false, 'message' => 'Gagal', 'error' => 'Pinjaman tidak ditemukan atau bukan status pending']);
    exit;
}

// GET: render admin page (HTML)
// Fetch pending pinjaman
$rows = [];
if (!isset($dbError)) {
    $q = "SELECT id, id_pengguna, jumlah as jumlah_pinjaman, tenor, tujuan_penggunaan, created_at, catatan_admin FROM pinjaman WHERE status = 'pending' ORDER BY created_at ASC";
    $res = mysqli_query($con, $q);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
        mysqli_free_result($res);
    }
}

// Render simple HTML page
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Approval Pinjaman - Admin</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;margin:20px}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #ddd;padding:8px}
th{background:#f4f4f4}
.button{padding:6px 10px;border:0;border-radius:4px;cursor:pointer}
.btn-approve{background:#28a745;color:#fff}
.btn-reject{background:#dc3545;color:#fff}
.notice{margin:10px 0;padding:10px;border-radius:4px}
.notice.success{background:#e6ffed;border:1px solid #b7f0c1}
.notice.error{background:#ffe6e6;border:1px solid #f0b7b7}
</style>
</head>
<body>
<h1>Approval Pengajuan Pinjaman (Admin)</h1>
<?php if (isset($dbError) && $dbError): ?>
<div class="notice error">Koneksi database tidak tersedia.</div>
<?php endif; ?>
<table>
<thead>
<tr><th>ID</th><th>id_pengguna</th><th>jumlah_pinjaman</th><th>tenor</th><th>tujuan_penggunaan</th><th>created_at</th><th>aksi</th></tr>
</thead>
<tbody>
<?php if (empty($rows)): ?>
<tr><td colspan="7" style="text-align:center">Tidak ada pengajuan pending.</td></tr>
<?php endif; ?>
<?php foreach ($rows as $r): ?>
<tr data-id="<?php echo htmlspecialchars($r['id'], ENT_QUOTES); ?>">
<td><?php echo htmlspecialchars($r['id']); ?></td>
<td><?php echo htmlspecialchars($r['id_pengguna']); ?></td>
<td><?php echo htmlspecialchars($r['jumlah_pinjaman']); ?></td>
<td><?php echo htmlspecialchars($r['tenor']); ?></td>
<td><?php echo htmlspecialchars($r['tujuan_penggunaan']); ?></td>
<td><?php echo htmlspecialchars($r['created_at']); ?></td>
<td>
<button class="button btn-approve" data-action="approve">Approve</button>
<button class="button btn-reject" data-action="reject">Reject</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div id="msg"></div>

<script>
function showMsg(html, cls){
    var el = document.getElementById('msg');
    el.innerHTML = '<div class="notice '+(cls||'')+'">'+html+'</div>';
    setTimeout(function(){el.innerHTML='';}, 4000);
}

document.addEventListener('click', function(e){
    var btn = e.target.closest('button[data-action]');
    if (!btn) return;
    var tr = btn.closest('tr');
    var id = tr.getAttribute('data-id');
    var action = btn.getAttribute('data-action');
    var note = prompt('Catatan admin (opsional):');
    if (!confirm('Yakin ingin '+action+' pinjaman ID '+id+'?')) return;

    // Send POST via fetch (JSON)
    fetch('', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action: action, id: id, note: note})
    }).then(function(res){
        return res.json();
    }).then(function(json){
        if (json.status) {
            showMsg(json.message, 'success');
            // remove row
            tr.parentNode.removeChild(tr);
        } else {
            showMsg(json.error || json.message, 'error');
        }
    }).catch(function(err){
        showMsg('Request gagal: '+err.message, 'error');
    });
});
</script>
</body>
</html>