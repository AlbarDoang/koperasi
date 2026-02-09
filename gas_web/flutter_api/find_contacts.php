<?php
include 'connection.php';

$data = json_decode(file_get_contents('php://input'), true);
$phones = isset($data['phones']) && is_array($data['phones']) ? $data['phones'] : [];

// Normalize phones: keep only digits and unique
$norm = [];
foreach ($phones as $p) {
    $clean = preg_replace('/[^0-9]/', '', (string)$p);
    if ($clean !== '') $norm[$clean] = true;
}

$matched = [];
if (count($norm) > 0) {
    $placeholders = implode(',', array_fill(0, count($norm), '?'));
    $types = str_repeat('s', count($norm));
    $vals = array_keys($norm);

    $sql = "SELECT id_pengguna AS id, nama, no_hp FROM pengguna WHERE REPLACE(no_hp, '+', '') IN (" . $placeholders . ")";
    $stmt = $connect->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$vals);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $matched[] = $row;
        }
        $stmt->close();
    }
}

echo json_encode(['success' => true, 'matched' => $matched]);


