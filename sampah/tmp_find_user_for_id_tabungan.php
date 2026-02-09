<?php
include __DIR__ . '/../gas_web/flutter_api/connection.php';
$id_tab = '97';
$stmt = $connect->prepare("SELECT * FROM pengguna WHERE id_tabungan = ? OR id = ? LIMIT 1");
$stmt->bind_param('ss', $id_tab, $id_tab);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode($row) . "\n";
} else {
    echo "not found\n";
}
?>