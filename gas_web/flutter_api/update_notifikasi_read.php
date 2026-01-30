<?php
/**
 * API: Mark a notification as read
 * POST parameters: id_notifikasi, id_pengguna
 */
include 'connection.php';
header('Content-Type: application/json');

// Accept only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$id_notif = isset($_POST['id_notifikasi']) ? intval($_POST['id_notifikasi']) : 0;
$id_pengguna = isset($_POST['id_pengguna']) ? intval($_POST['id_pengguna']) : 0;

if ($id_notif <= 0 || $id_pengguna <= 0) {
    echo json_encode(['success' => false, 'message' => 'id_notifikasi dan id_pengguna diperlukan dan harus numerik']);
    exit();
}

try {
    // Verify ownership
    $s = $connect->prepare("SELECT id_pengguna FROM notifikasi WHERE id = ? LIMIT 1");
    if (!$s) throw new Exception('Prepare failed: ' . $connect->error);
    $s->bind_param('i', $id_notif);
    $s->execute();
    $r = $s->get_result();
    if (!$r || $r->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Notifikasi tidak ditemukan']);
        $s->close();
        exit();
    }
    $row = $r->fetch_assoc();
    $owner = intval($row['id_pengguna']);
    $s->close();

    if ($owner !== $id_pengguna) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak: notifikasi bukan milik pengguna']);
        exit();
    }

    // Update read_status to 1
    $u = $connect->prepare("UPDATE notifikasi SET read_status = 1 WHERE id = ? LIMIT 1");
    if (!$u) throw new Exception('Prepare failed: ' . $connect->error);
    $u->bind_param('i', $id_notif);
    $ok = $u->execute();
    $u->close();

    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status']);
    }
} catch (Exception $e) {
    error_log('update_notifikasi_read.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server']);
}

