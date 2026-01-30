<?php

   /************* Ini untuk koneksi kedatabase nya **********/
   include "../../../koneksi/koneksi_cari.php";
   include "../../../koneksi/fungsi_indotgl.php";
   /*********************************************************/
   
   $option = '<option value=""> - Tanggal Terakhir Transaksi - </option>';
   
   $tgl = isset($_GET['tgl']) ?  $_GET['tgl'] :'';
   $sql = "select * from pengguna where id_tabungan='".$tgl."'";
   if($res = $database->query($sql)) {
      while ($row = $res->fetch_assoc()) {
        $tanggal = tgl_indo($row['transaksi_terakhir']);
       $option = "<option value='".$row['transaksi_terakhir']."'>".$tanggal."</option>";
      }
   }
   echo $option;
?>