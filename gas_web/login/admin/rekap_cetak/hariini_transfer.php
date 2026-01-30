<?php
include "../../koneksi/koneksi_export.php";
include "../../koneksi/fungsi_indotgl.php";

//hari ini
$hariini = date('Y-m-d');


$filename = "Transaksi-Transfer-Tabungan-(".tgl_indo($hariini).").xls";
// Fungsi header dengan mengirimkan raw data excel
header("Content-type: application/vnd-ms-excel");
 
// Mendefinisikan nama file ekspor "hasil-export.xls"
header("Content-Disposition: attachment; filename=$filename");

try {
    $pdo = new PDO( 'mysql:host='.$db_host.';port='.$db_port.';dbname='.$db_name , $db_user, $db_pass, array(PDO::MYSQL_ATTR_LOCAL_INFILE => 1) );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
    $errMessage = 'Gagal terhubung dengan MySQL' . ' # MYSQL ERROR:' . $e->getMessage();
    die($errMessage);
}

$sql = 'SELECT *, SUM(nominal) AS nominal FROM t_transfer WHERE tanggal=DATE(NOW()) GROUP BY tanggal, no_transfer, nama_pengirim, id_pengirim, kelas_pengirim, nama_penerima, id_penerima, kelas_penerima';
$stmt = $pdo->prepare($sql);
$stmt->execute();

echo '<html>
        <body>';

function format_ribuan ($nilai){
    return number_format ($nilai, 0, ',', '.');
}

// Ubah hasil query menjadi associative array dan simpan kedalam variabel result
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<tr><b>Laporan Transaksi Transfer Tabungan Hari Ini</b></tr><br>";
echo "<tr><b>&nbsp;</b></tr>";
echo '<table border="1">
		<thead>
			<tr>
			    <th>Tanggal</th>
			    <th>No Transaksi</th>
			    <th>ID Pengirim</th>
			    <th>Nama Pengirim</th>
			    <th>Kelas Pengirim</th>
			    <th>ID Penerima</th>
			    <th>Nama Penerima</th>
			    <th>Kelas Penerima</th>
			    <th>Nominal</th>
			</tr>
		</thead>
		<tbody>';
		
$subtotal_plg = $subtotal_thn = $total = 0;
foreach ($result as $key => $row)
{
	$subtotal_plg += $row['nominal'];
	$subtotal_thn += $row['nominal'];
	echo '<tr>
			<td>'.tgl_indo($row['tanggal']).'</td>
			<td>'.$row['no_transfer'].'</td>
			<td>'.$row['id_pengirim'].'</td>
			<td>'.$row['nama_pengirim'].'</td>
			<td>'.$row['kelas_pengirim'].'</td>
			<td>'.$row['id_penerima'].'</td>
			<td>'.$row['nama_penerima'].'</td>
			<td>'.$row['kelas_penerima'].'</td>
			<td class="right">Rp '.format_ribuan($row['nominal']).'</td>
		</tr>';
	
	// SUB TOTAL per thn_br
	if (@$result[$key+1]['tanggal'] != $row['tanggal']) {
		echo '<tr class="subtotal">
			<td colspan="8" align="center">Total Hari Ini ' . tgl_indo($row['tanggal']) . '</td>
			<td class="right">Rp '.format_ribuan($subtotal_thn).'</td>
		</tr>';
		$subtotal_thn = 0;
	} 
	$total += $row['nominal'];
}

echo '</tbody>
</table>
</body>
</html>';