<?php

   /************* Ini untuk koneksi kedatabase nya **********/
   include "../../../koneksi/koneksi_cari.php";
   /*********************************************************/
   
   $option = '<input type="text" name="nama_kelas" class="form-control" placeholder=" - Kelas - " readonly>';
   
   $kls = isset($_GET['kls']) ?  $_GET['kls'] :'';
   $sql = "select * from pengguna where id_tabungan='".$kls."'";
   if($res = $database->query($sql)) {
      while ($row = $res->fetch_assoc()) {
       $option = "<input class='form-control' type='text' name='nama_kelas' value='".$row['kelas']."' readonly>";
      }
   }
   echo $option;
?>