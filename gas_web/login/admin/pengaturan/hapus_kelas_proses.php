<?php
include('../../koneksi/config.php');

$id = $_GET['id'];

$sql  = 'delete from kelas where id_kel="'.$id.'"';
$query  = mysqli_query($con,$sql);
header('location: ../pengaturan/');
?>