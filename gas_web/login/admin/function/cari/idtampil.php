<?php

   /************* Ini untuk koneksi kedatabase nya **********/
   include "../../../koneksi/koneksi_cari.php";
   /*********************************************************/
   
   $option = '<input type="text" class="form-control" name="nama_siswa" value="ID Tabungan" readonly="readonly"/>';
   
   $id = isset($_GET['idt']) ?  $_GET['idt'] :'';
   $sql = "select * from pengguna where id_tabungan='".$id."'";
   if($res = $database->query($sql)) {
      while ($row = $res->fetch_assoc()) {
       $option = "<input class='form-control' type='text' name='nama_siswa' value='".$row['id_tabungan']."' readonly='readonly'/>";
      }
   }

   echo $option;
?>