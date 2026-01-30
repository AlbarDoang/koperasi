<?php
include 'connection.php';

$id_pengirim = isset($_POST['id_pengirim']) ? trim($_POST['id_pengirim']) : '';
$limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

if ($id_pengirim === '') {
    echo json_encode(['success' => false, 'message' => 'Missing id_pengirim']);
    exit;
}

// Aggregate transfers to find most frequently used recipients by this sender
$sql = "SELECT t.id_penerima AS id, p.nama AS nama, p.no_hp AS no_hp, COUNT(*) AS transfers, MAX(t.tanggal) AS last_transfer
        FROM t_transfer t
        LEFT JOIN pengguna p ON t.id_penerima = p.id_anggota
        WHERE t.id_pengirim = '" . $connect->real_escape_string($id_pengirim) . "'
          AND t.id_penerima IS NOT NULL
          AND t.id_penerima != ''
        GROUP BY t.id_penerima
        ORDER BY transfers DESC, last_transfer DESC
        LIMIT " . intval($limit);

$result = $connect->query($sql);

$recipients = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row;
    }
}

echo json_encode(['success' => true, 'recipients' => $recipients]);

