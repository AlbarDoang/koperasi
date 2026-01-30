<?php
include('../koneksi/db_auto.php');
include('../koneksi/fungsi_indotgl.php');
include('function_all.php');

	// Turn off error reporting
	error_reporting(0);

$query = '';
$output = array();
$query .= "SELECT * FROM t_keluar ";
if(isset($_POST["search"]["value"]))
{
	$query .= 'WHERE no_keluar LIKE "%'.$_POST["search"]["value"].'%" ';
	$query .= 'OR nama LIKE "%'.$_POST["search"]["value"].'%" ';
}
if(isset($_POST["order"]))
{
	$query .= 'ORDER BY '.$_POST['order']['0']['column'].' '.$_POST['order']['0']['dir'].' ';
}
else
{
	$query .= 'ORDER BY id_keluar DESC ';
}
if($_POST["length"] != -1)
{
	$query .= 'LIMIT ' . $_POST['start'] . ', ' . $_POST['length'];
}
$statement = $connection->prepare($query);
$statement->execute();
$result = $statement->fetchAll();
$data = array();
$no = 1;
$filtered_rows = $statement->rowCount();
foreach($result as $row)
{
	$sub_array = array();
	$sub_array[] = '<div align="center">'.$no++.'</div>';
	$sub_array[] = '<div align="center">'.$row["no_keluar"].'</div>';
	$sub_array[] = '<div align="center">'. $row["id_tabungan"].'</div>';
	$sub_array[] = $row["nama"];
	$sub_array[] = '<div align="center">'.tgl_indo($row["tanggal"]) .'</div>';
	$sub_array[] = 'Rp '.number_format($row["jumlah"]);
	$sub_array[] = '<div align="center"> 

	<a href="#myModal" id="custId" data-bs-toggle="modal" data-id="'.$row["no_keluar"].'">
	<button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Transaksi"><i class="fe fe-alert-octagon icon-lg"></i></button></a>
	
	<a href="kwitansi.php?no_keluar='.$row["no_keluar"].'"><button type="button" class="btn btn-success btn-sm btn-icon" title="Cetak Kwitansi Penarikan Tabungan"><i class="fe fe-file-text"></i></button></a>
	
	</div>';
	$data[] = $sub_array;
}
$output = array(
	"draw"				=>	intval($_POST["draw"]),
	"recordsTotal"		=> 	$filtered_rows,
	"recordsFiltered"	=>	get_total_all_records_keluar(),
	"data"				=>	$data
);
echo json_encode($output);
?>