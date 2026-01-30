<?php 
/**
 * API: Get Saldo Realtime
 * Mengambil saldo terkini anggota berdasarkan id_anggota atau username
 * Updated: Support for id, id_tabungan, id_anggota columns
 */
include 'connection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept several keys for compatibility: id_pengguna / id_tabungan / id_anggota / username
    $id_pengguna = isset($_POST['id_pengguna']) ? trim($_POST['id_pengguna']) : '';
    $id_tabungan = isset($_POST['id_tabungan']) ? trim($_POST['id_tabungan']) : '';
    $id_anggota = isset($_POST['id_anggota']) ? trim($_POST['id_anggota']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';

    if (empty($id_pengguna) && empty($id_tabungan) && empty($id_anggota) && empty($username)) {
        echo json_encode(array(
            "success" => false,
            "message" => "ID anggota, ID tabungan, atau username harus diisi"
        ));
        exit();
    }

    // Find the user row (prefer direct id, then id_tabungan, id_anggota, username)
    $data = null;
    if (!empty($id_pengguna) && ctype_digit($id_pengguna)) {
        $sql = "SELECT id, saldo FROM pengguna WHERE id = " . intval($id_pengguna) . " LIMIT 1";
        $res = $connect->query($sql);
        if ($res && $res->num_rows > 0) $data = $res->fetch_assoc();
    }
    if (!$data && !empty($id_tabungan)) {
        $sql = "SELECT id, saldo FROM pengguna WHERE id_tabungan='" . $connect->real_escape_string($id_tabungan) . "' LIMIT 1";
        $res = $connect->query($sql);
        if ($res && $res->num_rows > 0) $data = $res->fetch_assoc();
    }
    if (!$data && !empty($id_anggota)) {
        $sql = "SELECT id, saldo FROM pengguna WHERE id_anggota='" . $connect->real_escape_string($id_anggota) . "' LIMIT 1";
        $res = $connect->query($sql);
        if ($res && $res->num_rows > 0) $data = $res->fetch_assoc();
    }
    if (!$data && !empty($username)) {
        $sql = "SELECT id, saldo FROM pengguna WHERE username='" . $connect->real_escape_string($username) . "' LIMIT 1";
        $res = $connect->query($sql);
        if ($res && $res->num_rows > 0) $data = $res->fetch_assoc();
    }

    if (!$data) {
        echo json_encode(array("success" => false, "message" => "Pengguna tidak ditemukan"));
        exit();
    }

    // Return ONLY the authoritative saldo from pengguna.saldo
    $saldo = intval($data['saldo'] ?? 0);
    $out = array(
        "success" => true,
        "saldo" => $saldo,
        // Backwards compatibility: include minimal data object with saldo
        "data" => array(
            "id" => intval($data['id'] ?? 0),
            "saldo" => $saldo
        )
    );
    echo json_encode($out);
    exit();
} else {
    echo json_encode(array(
        "success" => false,
        "message" => "Method not allowed. Use POST"
    ));
}
