<?php
    include 'connection.php';

    // Read inputs safely (fall back to empty strings)
    $id_user        = isset($_POST['id_tabungan']) ? $connect->real_escape_string($_POST['id_tabungan']) : '';
    $name           = isset($_POST['nama']) ? $connect->real_escape_string($_POST['nama']) : '';
    $jk             = isset($_POST['jk']) ? $connect->real_escape_string($_POST['jk']) : '';
    $tgl_lahir      = isset($_POST['tanggal_lahir']) ? $connect->real_escape_string($_POST['tanggal_lahir']) : '';
    $tmpt_lahir     = isset($_POST['tempat_lahir']) ? $connect->real_escape_string($_POST['tempat_lahir']) : '';
    $alamat         = isset($_POST['alamat']) ? $connect->real_escape_string($_POST['alamat']) : '';
    $nohp           = isset($_POST['no_wa']) ? $connect->real_escape_string($_POST['no_wa']) : '';
    $nama_kelas     = isset($_POST['kelas']) ? $connect->real_escape_string($_POST['kelas']) : '';
    $tanda_pengenal = isset($_POST['tanda_pengenal']) ? $connect->real_escape_string($_POST['tanda_pengenal']) : '';
    $no_pengenal    = isset($_POST['no_pengenal']) ? $connect->real_escape_string($_POST['no_pengenal']) : '';
    $email          = isset($_POST['email']) ? $connect->real_escape_string($_POST['email']) : '';
    $nama_ibu       = isset($_POST['nama_ibu']) ? $connect->real_escape_string($_POST['nama_ibu']) : '';
    $nama_ayah      = isset($_POST['nama_ayah']) ? $connect->real_escape_string($_POST['nama_ayah']) : '';
    $no_ortu        = isset($_POST['no_ortu']) ? $connect->real_escape_string($_POST['no_ortu']) : '';

    $response = array('success' => false, 'message' => 'No update performed');

    // Prefer updating `pengguna` table when id matches a pengguna.id
    $res = $connect->query("SHOW TABLES LIKE 'pengguna'");
    if ($res && $res->num_rows > 0) {
        // Check if there's a pengguna with this id
        $check = $connect->query("SELECT id FROM pengguna WHERE id='".$id_user."' LIMIT 1");
        if ($check && $check->num_rows > 0) {
            // Normalize phone and validate (do not allow changing status_akun here)
            require_once __DIR__ . '/helpers.php';
            $nohp_norm = '';
            if ($nohp !== '') {
                $nohp_norm = sanitizePhone($nohp);
                if (empty($nohp_norm)) {
                    echo json_encode(array('success' => false, 'message' => 'Format nomor HP tidak valid')); exit();
                }
            }

            // Build prepared statement for allowed columns only
            $cols = array();
            $params = array();
            $types = '';
            if ($name !== '') { $cols[] = 'nama_lengkap = ?'; $params[] = $name; $types .= 's'; }
            if ($alamat !== '') { $cols[] = 'alamat_domisili = ?'; $params[] = $alamat; $types .= 's'; }
            if ($tgl_lahir !== '') { $cols[] = 'tanggal_lahir = ?'; $params[] = $tgl_lahir; $types .= 's'; }
            if ($nohp_norm !== '') { $cols[] = 'no_hp = ?'; $params[] = $nohp_norm; $types .= 's'; }

            if (count($cols) > 0) {
                // Check unique phone if updating phone
                if ($nohp_norm !== '') {
                    $chk = $connect->prepare('SELECT id FROM pengguna WHERE no_hp = ? AND id != ? LIMIT 1');
                    if ($chk) {
                        $chk->bind_param('si', $nohp_norm, $id_user);
                        $chk->execute();
                        $reschk = $chk->get_result();
                        if ($reschk && $reschk->num_rows > 0) {
                            echo json_encode(array('success' => false, 'message' => 'Nomor HP sudah digunakan oleh pengguna lain')); exit();
                        }
                        $chk->close();
                    }
                }

                $sql = "UPDATE pengguna SET " . implode(', ', $cols) . ", updated_at = NOW() WHERE id = ? LIMIT 1";
                $stmt = $connect->prepare($sql);
                if ($stmt) {
                    // bind params dynamically
                    $types .= 'i';
                    $params[] = intval($id_user);
                    $bind_names[] = $types;
                    for ($i = 0; $i < count($params); $i++) { $bind_name = 'bind' . $i; $$bind_name = $params[$i]; $bind_names[] = &$$bind_name; }
                    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
                    $ok = $stmt->execute();
                    if ($ok) {
                        $response = array('success' => true, 'message' => 'pengguna updated');
                    } else {
                        $response = array('success' => false, 'message' => 'Failed to update pengguna: ' . $stmt->error);
                    }
                    $stmt->close();
                } else {
                    $response = array('success' => false, 'message' => 'Failed to prepare update: ' . $connect->error);
                }
            } else {
                $response = array('success' => false, 'message' => 'No fields to update for pengguna');
            }
            echo json_encode($response);
            exit();
        }
    }

    // Fallback: update pengguna table (legacy flow)
    $sql = "UPDATE pengguna
            SET
            nama='".$name."',
            jk='".$jk."',
            tanggal_lahir='".$tgl_lahir."',
            tempat_lahir='".$tmpt_lahir."',
            alamat='".$alamat."',
            no_wa='".$nohp."',
            kelas='".$nama_kelas."',
            no_pengenal='".$no_pengenal."',
            email='".$email."',
            nama_ibu='".$nama_ibu."',
            nama_ayah='".$nama_ayah."',
            no_ortu='".$no_ortu."'
            WHERE
            id_tabungan='".$id_user."'
            ";
    $result = $connect->query($sql);
    echo json_encode(array("success" => (bool)$result, "message" => $result ? 'siswa updated' : 'failed to update siswa'));
