<?php
if($_SERVER['REQUEST_METHOD']=="GET"){
 require '../../koneksi/koneksi_select.php';
 daftarSiswa($_GET['search']);
}
 
function daftarSiswa($search){
 global $connect;
 
 if ($connect->connect_error) {
     die("koneksi Gagal: " . $conn->connect_error);
 }
 
 $sql = "SELECT * FROM pengguna WHERE nama LIKE '%$search%' ORDER BY nama ASC";
 $result = $connect->query($sql);
 
 if ($result->num_rows > 0) {
     $list = array();
     $key=0;
     while($row = $result->fetch_assoc()) {
         $list[$key]['id'] = $row['id_tabungan'];
         $list[$key]['text'] = $row['nama']. ' | ' . $row['kelas']; 
     $key++;
     }
     echo json_encode($list);
 } else {
     echo "Nama Tidak Ditemukan";
 }
 $connect->close();
}
 
?>