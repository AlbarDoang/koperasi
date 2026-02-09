<?php
/**
 * Repair script: Backfill tabungan_masuk for mulai_nabung rows with status='berhasil'
 * Usage: php repair_backfill_mulai_nabung.php [--apply]
 * - Default: dry-run, just list candidates
 * - --apply: perform inserts
 */

include 'connection.php';

$apply = in_array('--apply', $argv ?? []);
$limit = 500; // safe limit

function logit($msg) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [repair_backfill] " . $msg . "\n", FILE_APPEND);
}

if (empty($connect)) {
    echo "Missing DB connection\n";
    exit(1);
}

// Find candidates: mulai_nabung with status 'berhasil' and without tabungan_masuk
$has_id_tabungan_col = false;
$colchk = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'id_tabungan'");
if ($colchk && $colchk->num_rows > 0) $has_id_tabungan_col = true;

$sql = "SELECT id_mulai_nabung, id_tabungan, jenis_tabungan, jumlah, created_at FROM mulai_nabung WHERE status = 'berhasil' ORDER BY created_at ASC LIMIT ?";
$stmt = $connect->prepare($sql);
$stmt->bind_param('i', $limit);
$stmt->execute();
$res = $stmt->get_result();
$candidates = [];
while ($r = $res->fetch_assoc()) {
    $mid = intval($r['id_mulai_nabung']);
    $id_tabungan = $r['id_tabungan'];
    // find user id
    if ($has_id_tabungan_col) {
        $s = $connect->prepare("SELECT id FROM pengguna WHERE id_tabungan = ? LIMIT 1");
        $s->bind_param('s', $id_tabungan);
    } else {
        $s = $connect->prepare("SELECT id FROM pengguna WHERE id = ? LIMIT 1");
        $id_tabungan_int = intval($id_tabungan);
        $s->bind_param('i', $id_tabungan_int);
    }
    $s->execute();
    $ur = $s->get_result();
    $user_id = null;
    if ($ur && $ur->num_rows > 0) $user_id = intval($ur->fetch_assoc()['id']);
    $s->close();

    // find jenis
    $name = $r['jenis_tabungan'];
    $norm = preg_replace('/\btabungan\b/i', '', $name);
    $norm = trim($norm);
    $like1 = '%' . $name . '%';
    $like2 = '%' . $norm . '%';
    $stmtj = $connect->prepare("SELECT id FROM jenis_tabungan WHERE nama_jenis = ? OR nama_jenis LIKE ? OR nama_jenis LIKE ? LIMIT 1");
    $stmtj->bind_param('sss', $name, $like1, $like2);
    $stmtj->execute();
    $jr = $stmtj->get_result();
    $jenis_id = null;
    if ($jr && $jr->num_rows > 0) $jenis_id = intval($jr->fetch_assoc()['id']);
    $stmtj->close();

    // check if tabungan_masuk exists
    $has_tm = false;
    if ($user_id && $jenis_id) {
        $stmttm = $connect->prepare("SELECT id FROM tabungan_masuk WHERE id_pengguna = ? AND id_jenis_tabungan = ? LIMIT 1");
        $stmttm->bind_param('ii', $user_id, $jenis_id);
        $stmttm->execute();
        $tr = $stmttm->get_result();
        if ($tr && $tr->num_rows > 0) $has_tm = true;
        $stmttm->close();
    }
    if (!$has_tm) {
        $r['pengguna_id'] = $user_id;
        $r['jenis_id'] = $jenis_id;
        $candidates[] = $r;
    }
}

if (count($candidates) == 0) {
    echo "No candidates found.\n";
    logit("No candidates found");
    exit(0);
}

echo "Found " . count($candidates) . " candidates.\n";
foreach ($candidates as $c) {
    echo json_encode($c) . "\n";
}

if (!$apply) {
    echo "Dry run. To apply changes run: php repair_backfill_mulai_nabung.php --apply\n";
    exit(0);
}

// Apply: for each candidate, insert into tabungan_masuk and create notification
foreach ($candidates as $c) {
    $mid = intval($c['id_mulai_nabung']);
    $uid = intval($c['pengguna_id']);
    $jenis_id = intval($c['jenis_id']);
    $jumlah = floatval($c['jumlah']);
    if (!$uid || !$jenis_id) {
        logit("Skipping $mid because user or jenis not resolved: user=$uid jenis=$jenis_id");
        continue;
    }

    // Get jenis_tabungan name for notification
    $jenis_name = 'Tabungan Reguler';  // default
    $jenis_stmt = $connect->prepare("SELECT nama_jenis FROM jenis_tabungan WHERE id = ? LIMIT 1");
    if ($jenis_stmt) {
        $jenis_stmt->bind_param('i', $jenis_id);
        $jenis_stmt->execute();
        $jres = $jenis_stmt->get_result();
        if ($jres && $jres->num_rows > 0) {
            $jrow = $jres->fetch_assoc();
            $jenis_name = $jrow['nama_jenis'] ?? 'Tabungan Reguler';
        }
        $jenis_stmt->close();
    }

    $connect->begin_transaction();
    try {
        $created = date('Y-m-d H:i:s');
        $keterangan = 'Backfill topup (mulai_nabung ' . $mid . ')';
        $stmt_ins = $connect->prepare("INSERT INTO tabungan_masuk (id_pengguna, id_jenis_tabungan, jumlah, keterangan, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param('iissss', $uid, $jenis_id, $jumlah, $keterangan, $created, $created);
        if (!$stmt_ins->execute()) {
            $stmt_ins->close();
            throw new Exception('Insert tabungan_masuk failed: ' . $connect->error);
        }
        $ins_id = $connect->insert_id;
        $stmt_ins->close();
        // create notification
        $notif_type = 'mulai_nabung';
        $notif_title = 'Setoran Berhasil (backfill)';
        $notif_message = 'Setoran tabungan Anda telah diterima dan ditambahkan ke saldo. (backfill)';
        $notif_data = json_encode(['mulai_id' => $mid, 'tabungan_masuk_id' => $ins_id, 'jenis' => $jenis_id, 'jumlah' => $jumlah]);
        $created_notif = date('Y-m-d H:i:s');
        // Create the standardized "Mulai Nabung" notification using the central helper only.
        // Avoid direct INSERT to notifikasi to prevent duplicates / legacy titles.
        try {
            require_once __DIR__ . '/notif_helper.php';
            $notif2_res = create_mulai_nabung_notification($connect, $uid, $mid, $ins_id, $created, 'berhasil', $jumlah, $jenis_name);
            if ($notif2_res !== false) {
                logit("NOTIF_CREATED formal id={$notif2_res} user={$uid} jenis={$jenis_name} amount={$jumlah}");
            } else {
                // If helper skipped due to dedupe or other rule, log and continue
                error_log("[repair_backfill] create_mulai_nabung_notification returned false mid={$mid} user={$uid} ins_id={$ins_id}");
                logit("NOTIF_SKIPPED formal mid={$mid} user={$uid}");
            }
        } catch (Exception $e) {
            logit("Failed to create formal mulai_nabung notif for mid={$mid}: " . $e->getMessage());
        }
        // log
        logit("INSERT_TABUNGAN_MASUK user={$uid} jenis={$jenis_id} amt={$jumlah} mulai_nabung={$mid} insert_id={$ins_id}");
        $connect->commit();
        echo "Backfilled mulai_nabung {$mid} -> tabungan_masuk id={$ins_id}\n";
    } catch (Exception $e) {
        $connect->rollback();
        logit("Error backfilling {$mid}: " . $e->getMessage());
        echo "Failed to backfill {$mid}: " . $e->getMessage() . "\n";
    }
}

echo "Done.\n";

