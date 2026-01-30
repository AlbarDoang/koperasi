<?php
include "../../koneksi/koneksi_export.php";
include "../../koneksi/fungsi_indotgl.php";
//koneksi
include "../../koneksi/config.php";

//hari ini
$hariini = date('Y-m-d');

try {
	$pdo = new PDO( 'mysql:host='.$db_host.';port='.$db_port.';dbname='.$db_name , $db_user, $db_pass, array(PDO::MYSQL_ATTR_LOCAL_INFILE => 1) );
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
	$errMessage = 'Gagal terhubung dengan MySQL' . ' # MYSQL ERROR:' . $e->getMessage();
	die($errMessage);
}

$sql = 'SELECT *, SUM(nominal_m) AS nominal_m, SUM(nominal_k) AS nominal_k FROM transaksi GROUP BY tanggal, no_masuk, no_keluar, nama, id_tabungan, kelas, kegiatan';
$stmt = $pdo->prepare($sql);
$stmt->execute();

echo '
	<html>
		<head>
			<title>Transaksi Tabungan Siswa</title>
		</head>
			<style>
				body {font-family:tahoma, arial}
				table {border-collapse: collapse}
				th, td {font-size: 13px; padding: 3px 5px; color: #303030}
				.right{text-align: right}
			</style>
<body>';

function format_ribuan ($nilai){
	return number_format ($nilai, 0, ',', '.');
}

// Ubah hasil query menjadi associative array dan simpan kedalam variabel result
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = "Transaksi-Tabungan-Siswa.xls";

// Fungsi header dengan mengirimkan raw data excel
header("Content-type: application/vnd-ms-excel");
 
// Mendefinisikan nama file ekspor "hasil-export.xls"
header("Content-Disposition: attachment; filename=$filename");

echo "<div align='center'><h2>Laporan Transaksi Tabungan Siswa</h2>";
echo "<tr><b>&nbsp;</b></tr>";
echo '<table border="1px" >
		<thead style="background: #CCCCCC; font-size: 12px; border-color:#B0B0B0">
			<tr>
			    <th>Tanggal</th>
			    <th>No Transaksi</th>
			    <th>Nama</th>
			    <th>ID Tabungan</th>
			    <th>Kegiatan</th>
			    <th>Masuk</th>
			    <th>Keluar</th>
			</tr>
		</thead>
		<tbody>';
		
$subtotal_plg = $subtotal_thn = $total = 0;
$subtotal_plg2 = $subtotal_thn2 = $total2 = 0;
foreach ($result as $key => $row)
{
	$subtotal_plg += $row['nominal_m'];
	$subtotal_thn += $row['nominal_m'];
	$subtotal_plg2 += $row['nominal_k'];
	$subtotal_thn2 += $row['nominal_k'];
	echo '<tr>
			<td>'.tgl_indo($row['tanggal']).'</td>
			<td>';
            if (empty($row['no_masuk'])) {
                echo $row['no_keluar']; 
            } else {
                echo $row['no_masuk']; 
            }
    echo '</td>
			<td>'.$row['nama'].'</td>
			<td>'.$row['id_tabungan'].'</td>
			<td>'.$row['kegiatan'].'</td>
			<td class="right">';
            if (empty($row['no_masuk'])) {    
				echo "-";          
            } else {  
                echo 'Rp ' . format_ribuan($row['nominal_m']); 
            }
    echo '</td>
			<td class="right">';
            if (empty($row['no_keluar'])) {      
				echo "-";          
            } else {  
                echo 'Rp ' . format_ribuan($row['nominal_k']); 
            }
    echo '</td>
		</tr>';
	
	// SUB TOTAL per thn_br
	if (@$result[$key+1]['tanggal'] != $row['tanggal']) {
		echo '<tr class="subtotal">
			<td colspan="5" align="center">Total Pertanggal ' . tgl_indo($row['tanggal']) . '</td>
			<td class="right">Rp '.format_ribuan($subtotal_thn).'</td>
			<td class="right">Rp '.format_ribuan($subtotal_thn2).'</td>
		</tr>';
		$subtotal_thn = 0;
		$subtotal_thn2 = 0;
	} 
	$total += $row['nominal_m'];
	$total2 += $row['nominal_k'];
}

echo '<tr class="total">
		<td colspan="5" align="center">Total Tabungan</td>
		<td align="center">Rp ' . format_ribuan($total) . '</td>
		<td align="center">Rp ' . format_ribuan($total2) . '</td>
	</tr>
	<tr class="subtotal">
		<td colspan="5" align="center">Total Saldo Tabungan Siswa</td>
		<td colspan="2" align="center"><b>Rp ' . format_ribuan($total-$total2) . '</b></td>
	</tr>
	</tbody>
</table>
</body>
</html>';