<?php 
    include 'connection.php';
    
    $id_tabungan = $_POST['id_tabungan'];
    
    $sql = "SELECT SUM(nominal_m) AS nominal_m,SUM(nominal_k) AS nominal_k FROM transaksi WHERE id_tabungan='$id_tabungan'";
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
