<?php
include('../../koneksi/config.php');

$id = $_GET['id'];
$sql1  = 'delete from user where id="'.$id.'"';
$query4  = mysqli_query($con,$sql1);
header('location: ../user/petugas');
?>