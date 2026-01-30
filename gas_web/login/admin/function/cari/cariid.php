<?php

   /************* Ini untuk koneksi kedatabase nya **********/
   include "../../../koneksi/koneksi_cari.php";
   /*********************************************************/
   
   $option = '<input type="hidden" id="demo-text-input" class="form-control" name="nama_siswa"  placeholder=" - Nama Siswa - " readonly>';
   
   $id = isset($_GET['id']) ?  $_GET['id'] :'';
   $sql = "select * from pengguna where id_tabungan='".$id."'";
   if($res = $database->query($sql)) {
      while ($row = $res->fetch_assoc()) {
       $option = "<input class='form-control' type='hidden' name='nama_siswa' value='".$row['nama']."'/>";
      }
   }

   echo $option;
?>