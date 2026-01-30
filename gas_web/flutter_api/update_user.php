<?php
    // Update user password / username. Accept either id_tabungan or id_anggota for compatibility.
    include 'connection.php';

    $id_user = isset($_POST['id_anggota']) ? $connect->real_escape_string($_POST['id_anggota']) : (isset($_POST['id_tabungan']) ? $connect->real_escape_string($_POST['id_tabungan']) : null);
    $username = isset($_POST['username']) ? $connect->real_escape_string($_POST['username']) : null;
    $password2 = isset($_POST['password2']) ? $_POST['password2'] : null;

    if (empty($id_user)) {
        echo json_encode(array("success" => false, "message" => "id_anggota/id_tabungan wajib"));
        exit();
    }

    $sets = array();
    if (!empty($username)) {
        $sets[] = "username='$username'";
    }
    if (!empty($password2)) {
        $p1 = sha1($password2);
        $p2 = $connect->real_escape_string($password2);
        $sets[] = "password1='$p1'";
        $sets[] = "password2='$p2'";
    }

    if (count($sets) == 0) {
        echo json_encode(array("success" => false, "message" => "Tidak ada data yang diupdate"));
        exit();
    }

    $sql = "UPDATE pengguna SET " . implode(', ', $sets) . " WHERE id_anggota='$id_user'";
    $result = $connect->query($sql);
    echo json_encode(array("success" => (bool)$result, "query" => $sql));
