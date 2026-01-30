<?php 
    include 'connection.php';
    
    $id_tabungan = $_POST['id_tabungan'];
    
    // Select relevant fields and map petugas to namauser for mobile consistency
    // Include kegiatan as it's used in the transaction type display
    $sql = "SELECT 
                id_transaksi,
                id_tabungan,
                no_masuk,
                no_keluar,
                nama,
                kelas,
                kegiatan,
                jumlah_masuk AS nominal_m,
                jumlah_keluar AS nominal_k,
                tanggal,
                petugas AS namauser,
                created_at
            FROM transaksi 
            WHERE id_tabungan='$id_tabungan' 
            ORDER BY id_transaksi DESC";
    $result = $connect->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = array();
        while ($row = $result->fetch_assoc()) {
            $user[] = $row;
        }
        echo json_encode(array(
            "success" => true,
            "tabungan" => $user,
            ));
        } else {
        echo json_encode(array(
            "success" => false,
        ));
    }
