<?php 
    include 'connection.php';
    
    $id_tabungan = $_POST['id_tabungan'];
    
    // Select all relevant fields including approval info
    // status field shows pending/approved/rejected status
    $sql = "SELECT 
                id_keluar,
                no_keluar,
                nama,
                id_tabungan,
                kelas,
                tanggal,
                jumlah,
                keterangan,
                status,
                approved_by,
                approved_at,
                created_at
            FROM t_keluar 
            WHERE id_tabungan='$id_tabungan' 
            ORDER BY id_keluar DESC";
    $result = $connect->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = array();
        while ($row = $result->fetch_assoc()) {
            $user[] = $row;
        }
        echo json_encode(array(
            "success" => true,
            "penarikan" => $user,
            ));
        } else {
        echo json_encode(array(
            "success" => false,
        ));
    }
