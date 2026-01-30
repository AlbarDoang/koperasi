<?php
    // Return list of users (siswa).
    // If no filter provided, return all active siswa.
    include 'connection.php';

    // Prefer POST parameters but allow GET for quick checks
    $status = isset($_POST['status']) ? $connect->real_escape_string($_POST['status']) : (isset($_GET['status']) ? $connect->real_escape_string($_GET['status']) : 'aktif');

    $sql = "SELECT id_anggota, nis, nama, no_hp, alamat, foto, foto_ktp, foto_selfie, saldo, status, created_at FROM pengguna";
    if (!empty($status)) {
        $sql .= " WHERE status='$status'";
    }
    $result = $connect->query($sql);

    $users = array();
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(array(
            "success"=> true,
            "user"=> $users,
        ));
    } else {
        echo json_encode(array(
            "success"=> false,
            "user"=> [],
        ));
    }
