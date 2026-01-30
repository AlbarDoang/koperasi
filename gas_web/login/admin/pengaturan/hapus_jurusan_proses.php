<?php
include('../../koneksi/config.php');

$id = $_GET['id'];

$sql  = 'delete from jurusan where id_jur="'.$id.'"';
$query  = mysqli_query($con,$sql);
header('location: ../pengaturan/');
?>