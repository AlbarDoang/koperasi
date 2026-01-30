<?php 
    include 'connection.php';
    
    $id_tabungan = $_POST['id_tabungan'];
    
    // Map kegiatan to keterangan for mobile consistency
    // Select relevant fields only
    $sql = "SELECT 
                id_masuk,
                no_masuk,
                nama,
                id_tabungan,
                kelas,
                tanggal,
                jumlah,
                kegiatan AS keterangan,
                created_at
            FROM t_masuk 
            WHERE id_tabungan='$id_tabungan' 
            ORDER BY id_masuk DESC";
    $result = $connect->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = array();
        while ($row = $result->fetch_assoc()) {
            $user[] = $row;
        }
        echo json_encode(array(
            "success" => true,
            "setoran" => $user,
            ));
        } else {
        echo json_encode(array(
            "success" => false,
        ));
    }
