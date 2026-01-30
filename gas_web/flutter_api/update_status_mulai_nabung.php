<?php
/**
 * API: Update Status Mulai Nabung
 * Method: POST
 * Params: id_mulai_nabung
 * Action: Set status = 'menunggu_admin'
 * Note: Do NOT modify user balance here.
 */

// Suppress PHP warnings/notices that would break JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'connection.php';

// Debug: log incoming request & responses
@file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] CALL POST=" . json_encode($_POST) . "\n", FILE_APPEND);

if (empty($connect)) {
    @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] ERROR missing DB connection\n", FILE_APPEND);
    echo json_encode(["success" => false, "message" => "Internal server error"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit();
}

// Helper to get POST data safely
function getPostData($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : null;
}

$id_raw = getPostData('id_mulai_nabung');
if (empty($id_raw)) {
    echo json_encode(["success" => false, "message" => "ID tidak boleh kosong"]);
    exit();
}

$id = intval($id_raw);

// Prepare statement to update status only
$status_new = 'menunggu_admin';

if ($stmt = $connect->prepare("UPDATE mulai_nabung SET status = ?, updated_at = NOW() WHERE id_mulai_nabung = ?")) {
    $stmt->bind_param('si', $status_new, $id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Fetch mulai_nabung row to resolve user and create notification
            $stmt->close();
            try {
                $s2 = $connect->prepare("SELECT id_tabungan, nomor_hp, nama_pengguna, created_at, jumlah FROM mulai_nabung WHERE id_mulai_nabung = ? LIMIT 1");
                if ($s2) {
                    $s2->bind_param('i', $id);
                    $s2->execute();
                    $r2 = $s2->get_result();
                    if ($r2 && $r2->num_rows > 0) {
                        $row = $r2->fetch_assoc();
                        $id_tab = $row['id_tabungan'] ?? null;
                        $nohp = $row['nomor_hp'] ?? null;
                        $nama = $row['nama_pengguna'] ?? null;
                        $created = $row['created_at'] ?? null;
                        $jumlah = $row['jumlah'] ?? null;

                        // Use shared helper to resolve user id and create notification
                        require_once __DIR__ . '/notif_helper.php';
                        $uid = resolve_pengguna_id_from_mulai($connect, $id_tab, $nohp, $nama);
                        if ($uid !== null) {
                            $nid = create_setoran_diproses_notification($connect, $uid, $id, date('Y-m-d H:i:s'), $jumlah);
                            if ($nid !== false) {
                                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] NOTIF_CREATED id={$nid} user={$uid} mulai_id={$id}\n", FILE_APPEND);
                            } else {
                                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] NOTIF_SKIPPED user={$uid} mulai_id={$id}\n", FILE_APPEND);
                            }
                        } else {
                            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] WARN could not resolve user for mulai_id={$id} id_tab={$id_tab} nohp={$nohp} nama={$nama}\n", FILE_APPEND);
                        }
                    }
                    $s2->close();
                }
            } catch (Exception $e) {
                @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] NOTIF_EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            $out = json_encode(["success" => true, "message" => "Status berhasil diperbarui"]);
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] OK " . $out . "\n", FILE_APPEND);
            echo $out;
            exit();
        } else {
            // no rows updated: id not found or status already same
            $out = json_encode(["success" => false, "message" => "Gagal memperbarui status"]);
            @file_put_contents(__DIR__ . '/api_debug.log', date('c') . " [update_status_mulai_nabung] OK " . $out . "\n", FILE_APPEND);
            echo $out;
            exit();
        }
    } else {
        echo json_encode(["success" => false, "message" => "Gagal memperbarui status"]);
        exit();
    }
} else {
    // prepare failed
    echo json_encode(["success" => false, "message" => "Gagal memperbarui status"]);
    exit();
}

