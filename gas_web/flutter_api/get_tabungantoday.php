<?php 
    include 'connection.php';
    
    $id_tabungan = $_POST['id_tabungan'];
    //$tanggal = $_POST['tanggal'];
    
    $sql = "SELECT * FROM transaksi WHERE id_tabungan='$id_tabungan' AND tanggal=DATE(NOW()) ORDER BY id_transaksi DESC";
    $result = $connect->query($sql);
    
    if ($result->num_rows > 0) {
        $user = array();
        while ($row = $result->fetch_assoc()) {
            $user[] = $row;
        }
        echo json_encode(array(
            "success"=> true,
            "tabungan"=>$user,
            ));
        } else {
        echo json_encode(array(
            "success"=> false,
        ));
    }
