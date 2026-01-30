<?php

   /************* Ini untuk koneksi kedatabase nya **********/
   include "../../../koneksi/koneksi_cari.php";
   /*********************************************************/
   
   $option = '<option value=""> - Saldo Anda Adalah - </option>';
   
   $sal = isset($_GET['sal']) ?  $_GET['sal'] :'';
   $sql = "select * from pengguna where id_tabungan='".$sal."'";
   if($res = $database->query($sql)) {
      while ($row = $res->fetch_assoc()) {
      $harga  = "Rp. " . number_format($row['saldo']);
       $option = "<option id='txt2' onkeyup='sum()' value='".$row['saldo']."'>".$harga."</option>";
      }
   }
   echo $option;
?>