<?php
// Suppress PHP warnings/notices that would break JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

/**
 * API: Buat Permintaan Top-up Tunai (mulai_nabung)
 * Method: POST
 * Params: id_tabungan, nomor_hp, nama_pengguna, jumlah, tanggal (optional), jenis_tabungan (optional)
 * - status awal = 'menunggu_penyerahan'
 * Output: JSON
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'connection.php';

// AUTO-FIX: If the `jenis_tabungan` column is still an INT (which causes
// string values to be stored as 0), try to convert it to VARCHAR and clean
// up existing 0/NULL/empty values. This runs only when the column type is
// detected as integer to avoid unnecessary ALTER calls.
try {
    $desc = $connect->query("DESCRIBE `mulai_nabung`");
    if ($desc) {
        while ($r = $desc->fetch_assoc()) {
            if ($r['Field'] === 'jenis_tabungan') {
                $curType = $r['Type'];
                if (stripos($curType, 'int') !== false) {
                    // change to varchar and update existing rows
                    $alter = "ALTER TABLE `mulai_nabung` MODIFY COLUMN `jenis_tabungan` VARCHAR(100) DEFAULT 'Tabungan Reguler'";
                    @$connect->query($alter);
                    @$connect->query("UPDATE `mulai_nabung` SET jenis_tabungan = 'Tabungan Reguler' WHERE jenis_tabungan = '0' OR jenis_tabungan = 0 OR jenis_tabungan IS NULL OR jenis_tabungan = ''");
                }
                break;
            }
        }
    }
} catch (Throwable $e) {
    // best-effort: don't block normal execution if auto-fix fails
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

function getPostData($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$id_tabungan = getPostData('id_tabungan');
$nomor_hp = getPostData('nomor_hp');
$nama_pengguna = getPostData('nama_pengguna');
$jumlah_raw = getPostData('jumlah');
$tanggal = getPostData('tanggal') ?? date('Y-m-d');
$jenis_tabungan = getPostData('jenis_tabungan') ?? 'Tabungan Reguler';

if (empty($id_tabungan) || empty($nomor_hp) || empty($nama_pengguna) || empty($jumlah_raw)) {
    echo json_encode(["success" => false, "message" => "Parameter tidak lengkap"]);
    exit();
}

$jumlah = floatval($jumlah_raw);
if ($jumlah <= 0) {
    echo json_encode(["success" => false, "message" => "Jumlah tidak valid"]);
    exit();
}

$status = 'menunggu_penyerahan';
$id_tabungan_safe = $connect->real_escape_string($id_tabungan);
$nomor_hp_safe = $connect->real_escape_string($nomor_hp);
$nama_pengguna_safe = $connect->real_escape_string($nama_pengguna);
$tanggal_safe = $connect->real_escape_string($tanggal);
$jenis_tabungan_safe = $connect->real_escape_string($jenis_tabungan);

$created_at = date('Y-m-d H:i:s');

if ($stmt = $connect->prepare("INSERT INTO mulai_nabung (id_tabungan, nomor_hp, nama_pengguna, tanggal, jumlah, jenis_tabungan, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")) {
    $stmt->bind_param('ssssdsss', $id_tabungan_safe, $nomor_hp_safe, $nama_pengguna_safe, $tanggal_safe, $jumlah, $jenis_tabungan_safe, $status, $created_at);
    if ($stmt->execute()) {
        $id = $connect->insert_id;
        echo json_encode(["success" => true, "message" => "Permintaan mulai nabung berhasil dibuat", "id_mulai_nabung" => $id]);
        $stmt->close();

        // Create initial transaction record with status='pending' so it shows up in riwayat transaksi
        // This allows tracking the entire lifecycle: pending -> approved/rejected
        try {
            $resolve_user_stmt = $connect->prepare("SELECT id FROM pengguna WHERE no_hp = ? LIMIT 1");
            if ($resolve_user_stmt) {
                $resolve_user_stmt->bind_param('s', $nomor_hp_safe);
                if ($resolve_user_stmt->execute()) {
                    $resolve_res = $resolve_user_stmt->get_result();
                    if ($resolve_res && $resolve_res->num_rows > 0) {
                        $user_row = $resolve_res->fetch_assoc();
                        $user_id = intval($user_row['id']);
                        
                        // Get current saldo
                        $saldo_stmt = $connect->prepare("SELECT saldo FROM pengguna WHERE id = ? LIMIT 1");
                        if ($saldo_stmt) {
                            $saldo_stmt->bind_param('i', $user_id);
                            $saldo_stmt->execute();
                            $saldo_res = $saldo_stmt->get_result();
                            $saldo_row = $saldo_res->fetch_assoc();
                            $current_saldo = floatval($saldo_row['saldo']);
                            $saldo_stmt->close();
                            
                            // Insert pending transaction
                            $trans_table_check = @$connect->query("DESCRIBE transaksi");
                            if ($trans_table_check) {
                                $trans_stmt = $connect->prepare("INSERT INTO transaksi (id_pengguna, jenis_transaksi, jumlah, saldo_sebelum, saldo_sesudah, keterangan, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
                                if ($trans_stmt) {
                                    $jenis_trans = 'setoran';
                                    // Include mulai_nabung ID in keterangan so we can identify unique submissions
                                    $keterangan_trans = 'Mulai nabung tunai (mulai_nabung ' . $id . ')';
                                    $status_trans = 'pending';
                                    $trans_stmt->bind_param('isddsss', $user_id, $jenis_trans, $jumlah, $current_saldo, $current_saldo, $keterangan_trans, $status_trans);
                                    if ($trans_stmt->execute()) {
                                        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [buat_mulai_nabung] INITIAL_TRANSAKSI_CREATED insert_id=".$connect->insert_id." user={$user_id} status=pending amt={$jumlah} mulai_id={$id}\n", FILE_APPEND);
                                    }
                                    $trans_stmt->close();
                                }
                            }
                        }
                    }
                }
                $resolve_user_stmt->close();
            }
        } catch (Exception $e) {
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [buat_mulai_nabung] Exception creating initial transaction: " . $e->getMessage() . "\n", FILE_APPEND);
            // Non-fatal: continue
        }

        // NOTE: We intentionally do NOT create a server notification here to avoid duplicate
        // notifications when the client immediately updates the status to 'menunggu_admin'.
        // The canonical place to create the user-facing submission notification is in
        // `update_status_mulai_nabung.php` (create_setoran_diproses_notification) where the
        // user confirms they have handed over cash.
        @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [buat_mulai_nabung] Created mulai_nabung id={$id} (no server notif created here)\n", FILE_APPEND);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal membuat permintaan"]);
        $stmt->close();
    }
} else {
    echo json_encode(["success" => false, "message" => "Gagal membuat permintaan"]);
}


