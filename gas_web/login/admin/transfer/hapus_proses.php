<?php
include('../../koneksi/config.php');

if(isset($_GET['id'])){
$id = $_GET['id'];
$query3             = mysqli_query($con,'select * from t_transfer where no_transfer = "'.$id.'"');
$row                = mysqli_fetch_array($query3);
$saldo_tabungan      = $row['nominal'];
$id_pengirim         = $row['id_pengirim'];
$id_penerima         = $row['id_penerima'];
}
else{
$saldo_tabungan      = '';
$id_pengirim         = '';
$id_penerima         = '';
} 

$query1 = "select * from siswa where id_tabungan='".$id_pengirim."'";
$hasil = mysqli_query($con,$query1);
while($data=mysqli_fetch_array($hasil)){ 

$saldo 	=  $data['saldo'];

$total = $saldo + $saldo_tabungan;

$sql2    = 'update siswa set saldo="'.$total.'" where id_tabungan="'.$id_pengirim.'"';
$query2  = mysqli_query($con,$sql2);

        }

$query1x = "select * from siswa where id_tabungan='".$id_penerima."'";
$hasilx = mysqli_query($con,$query1x);
while($datax=mysqli_fetch_array($hasilx)){ 

$saldox 	=  $datax['saldo'];

$totalx = $saldox - $saldo_tabungan;

$sql2x    = 'update siswa set saldo="'.$totalx.'" where id_tabungan="'.$id_penerima.'"';
$query2x  = mysqli_query($con,$sql2x);

        }

$sql  = 'delete from t_transfer where no_transfer="'.$id.'"';
$sql1  = 'delete from transaksi where no_masuk="'.$id.'"';
$sql3  = 'delete from transaksi where no_keluar="'.$id.'"';
$query  = mysqli_query($con,$sql);
$query4  = mysqli_query($con,$sql1);
$query5  = mysqli_query($con,$sql3);
header('location: ../transfer/');
?>