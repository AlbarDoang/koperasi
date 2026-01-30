<?php

   /************* Ini untuk koneksi kedatabase nya **********/
   include "../../../koneksi/koneksi_cari.php";
   /*********************************************************/
   
   $option = '<option value=""> - Pilih Kelas - </option>';
   
   $jur = isset($_GET['jur']) ?  $_GET['jur'] :'';
   $sql = "select * from kelas where id_jur='".$jur."'";
   if($res = $database->query($sql)) {
      while ($row = $res->fetch_assoc()) {
       $option .= "<option value='".$row['tingkat']." ".$row['singkatan']." ".$row['rombel']."'>".$row['tingkat']." ".$row['singkatan']." ".$row['rombel']."</option>";
      }
   }
   echo $option;
?>