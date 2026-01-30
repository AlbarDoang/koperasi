<?php
    // Delete user helper - supports multiple entity types. This endpoint
    // historically targeted 'siswa' but mobile app expects it for 'pengguna'.
    include 'connection.php';

    // Priority: id_pengguna for pengguna table, otherwise id_anggota/id_tabungan for siswa
    $id_pengguna = isset($_POST['id_pengguna']) ? $connect->real_escape_string($_POST['id_pengguna']) : null;
    $id_user = $id_pengguna ?? (isset($_POST['id_anggota']) ? $connect->real_escape_string($_POST['id_anggota']) : (isset($_POST['id_tabungan']) ? $connect->real_escape_string($_POST['id_tabungan']) : null));

    if (empty($id_user)) {
        echo json_encode(array("success" => false, "message" => "id_pengguna/id_anggota/id_tabungan wajib"));
        exit();
    }

    // If caller requests soft-delete (mark NONAKTIF) and table is pengguna
    if (!empty($id_pengguna) && isset($_POST['soft']) && $_POST['soft'] == '1') {
        $stmt = $connect->prepare("UPDATE pengguna SET status_akun = ? WHERE id = ?");
        if ($stmt) {
            // use an allowed enum to disable the account; 'DITOLAK' blocks login
            $new = 'DITOLAK';
            $stmt->bind_param('ss', $new, $id_pengguna);
            $res = $stmt->execute();
            $stmt->close();
            echo json_encode(array("success" => (bool)$res));
            exit();
        } else {
            echo json_encode(array("success" => false, "message" => "Prepare failed: " . $connect->error));
            exit();
        }
    }

    // If id_pengguna provided, delete from pengguna table
    if (!empty($id_pengguna)) {
        $stmt = $connect->prepare("DELETE FROM pengguna WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('s', $id_pengguna);
            $res = $stmt->execute();
            $stmt->close();
            echo json_encode(array("success" => (bool)$res));
            exit();
        } else {
            echo json_encode(array("success" => false, "message" => "Prepare failed: " . $connect->error));
            exit();
        }
    }

    // Fallback to existing siswa logic
    if (isset($_POST['soft']) && $_POST['soft'] == '1') {
        $sql = "UPDATE pengguna SET status='nonaktif' WHERE id_anggota='$id_user'";
        $result = $connect->query($sql);
        echo json_encode(array("success" => (bool)$result));
        exit();
    }

    // Hard delete from pengguna
    $sql = "DELETE FROM pengguna WHERE id_anggota='$id_user'";
    $result = $connect->query($sql);
    echo json_encode(array("success" => (bool)$result));
