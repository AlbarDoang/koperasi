<?php 
    include 'connection.php';
    
    $id_tabungan = $_POST['id_tabungan'];
    
    $sql = "SELECT * FROM transaksi WHERE id_tabungan='$id_tabungan' ORDER BY id_transaksi DESC";
    $result = $connect->query($sql);
    
    if ($result->num_rows > 0) {
        $user = array();
        while ($row = $result->fetch_assoc()) {
            $user[] = $row;
        }
        echo json_encode(array(
            "success"=> true,
            "tabungan2"=>$user,
            ));
        } else {
        echo json_encode(array(
            "success"=> false,
        ));
    }
