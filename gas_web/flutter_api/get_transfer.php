<?php 
    include 'connection.php';
    
    $id_tabungan = $_POST['id_tabungan'];
    
    // JOIN with siswa table to get nama and kelas for both sender and receiver
    // Map jumlah to nominal for mobile consistency
    $sql = "SELECT 
                t.id_transfer,
                t.no_transfer,
                t.id_pengirim,
                s1.nama AS nama_pengirim,
                s1.kelas AS kelas_pengirim,
                t.id_penerima,
                s2.nama AS nama_penerima,
                s2.kelas AS kelas_penerima,
                t.jumlah AS nominal,
                t.keterangan,
                t.tanggal,
                t.tanggal AS waktu,
                t.created_at
            FROM t_transfer t
            LEFT JOIN pengguna s1 ON t.id_pengirim = s1.id_pengguna
            LEFT JOIN pengguna s2 ON t.id_penerima = s2.id_pengguna
            WHERE (t.id_pengirim='$id_tabungan' OR t.id_penerima='$id_tabungan')
            ORDER BY t.id_transfer DESC";
    $result = $connect->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $user = array();
        while ($row = $result->fetch_assoc()) {
            $user[] = $row;
        }
        echo json_encode(array(
            "success" => true,
            "transfer" => $user,
            ));
        } else {
        echo json_encode(array(
            "success" => false,
        ));
    }

