<?php
include('../../koneksi/config.php');

if(isset($_GET['id'])){
$id = $_GET['id'];
$query3             = mysqli_query($con,'select * from t_keluar where no_keluar = "'.$id.'"');
$row                = mysqli_fetch_array($query3);
$saldo_tabungan      = $row['jumlah'];
$id_tabungan         = $row['id_tabungan'];
}
else{
$saldo_tabungan      = '';
$id_tabungan		= '';
} 

$query1 = "select * from pengguna where id_tabungan='".$id_tabungan."'";
$hasil = mysqli_query($con,$query1);
while($data=mysqli_fetch_array($hasil)){ 
        // Reverse the withdrawal by creating a 'masuk' ledger entry
        $user_id_numeric = intval($data['id']);
        include_once __DIR__ . '/../../function/ledger_helpers.php';
        $ok = insert_ledger_masuk($con, $user_id_numeric, floatval($saldo_tabungan), 'Reverse penarikan (hapus t_keluar id=' . $id . ')');
        if (!$ok) {
                // fallback: keep original behavior
                $saldo 	=  $data['saldo'];
                $total = $saldo + $saldo_tabungan;
                $sql2    = 'update pengguna set saldo="'.$total.'" where id_tabungan="'.$id_tabungan.'"';
                $query2  = mysqli_query($con,$sql2);
        }

        }

$sql  = 'delete from t_keluar where no_keluar="'.$id.'"';
$sql1  = 'delete from transaksi where no_keluar="'.$id.'"';
$query  = mysqli_query($con,$sql);
$query4  = mysqli_query($con,$sql1);
header('location: ../keluar/');
?>