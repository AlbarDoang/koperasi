<?php
include "../../../koneksi/koneksi_export.php";
include "../../../koneksi/fungsi_indotgl.php";
include "../../../koneksi/pengaturan.php";
include "../../../koneksi/fungsi_waktu.php";

session_start();
if( !isset($_SESSION['saya_admin']) )
{
header('location:./../../'.$_SESSION['akses']);
exit();
} 
	$id   = ( isset($_SESSION['id_user']) ) ? $_SESSION['id_user'] : '';
	$foto = ( isset($_SESSION['nama_foto']) ) ? $_SESSION['nama_foto'] : '';
	$nama = ( isset($_SESSION['nama_user']) ) ? $_SESSION['nama_user'] : '';

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

$sql = 'SELECT *, SUM(jumlah) AS jumlah FROM t_masuk WHERE tanggal=DATE(NOW()) GROUP BY tanggal, no_masuk, nama, id_tabungan, kelas';
$stmt = $pdo->prepare($sql);
$stmt->execute();

echo '<script>
		window.print();
	  </script>
	<html>
		<head>
			<title>Transaksi Tabungan Masuk Hari Ini</title>
			<link rel="shortcut icon" type="image/png" href="../../../../assets/brand/logo.png" />
			<style>
				body {font-family:tahoma, arial}
				table {border-collapse: collapse}
				th, td {font-size: 13px; solid #DEDEDE; padding: 3px 5px; color: #303030}
				th {background: #CCCCCC; font-size: 12px; border-color:#B0B0B0}
				.subtotal td {background: #F8F8F8}
				.right{text-align: right}
			</style>
		</head>
		<body>';

function format_ribuan ($nilai){
	return number_format ($nilai, 0, ',', '.');
}

// Ubah hasil query menjadi associative array dan simpan kedalam variabel result
$result = $stmt->fetchAll(PDO::FETCH_ASSOC); ?>

	<table width="100%" >
		<tr>
			<td style="padding: 4px"></td>
		</tr>
	</table>
	<table width="100%" >
		<tr>
			<td style="padding: 4px"></td>
		</tr>
	</table>
	<table width="100%" >
		<tr>
			<td style="padding: 4px"></td>
		</tr>
	</table>
	<table width="100%" >
		<tr>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"><img src="../../../../assets/brand/logonama.png" height="90"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td align="center" style="font-weight:bold; padding: 4px;">
			<h2><font face="Times New Roman"><b>TABUNGAN SISWA</b></font></h2>
			<h3><font face="Times New Roman"><b> <?php echo $nama_sekolah . ' (' . $singkatan_sekolah . ')'; ?></font></b></h3>
			<h5><font face="Times New Roman"><?php echo $alamat_sekolah; ?>
			<br>Email : <?php echo $email_sekolah; ?> | Telepon : <?php echo $no_telp; ?></font></h5>
			</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"><img src="../../../../assets/images/<?php echo  $logo_sekolah; ?>" height="75"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
		</tr>
	</table>
	<hr  style="border:solid 1px black">
	<br>

<?php
echo "<center><tr><b>Laporan Transaksi Tabungan Masuk Hari Ini</b></tr><br>";
echo "<tr><b>&nbsp;</b></tr>";
echo '<table border=1>
		<thead>
			<tr>
			    <th>Tanggal</th>
			    <th>No Transaksi</th>
			    <th>ID Tabungan</th>
			    <th>Nama</th>
			    <th>Kelas</th>
			    <th>Jumlah</th>
			</tr>
		</thead>
		<tbody>';
		
$subtotal_plg = $subtotal_thn = $total = 0;
foreach ($result as $key => $row)
{
	$subtotal_plg += $row['jumlah'];
	$subtotal_thn += $row['jumlah'];
	echo '<tr>
			<td>'.tgl_indo($row['tanggal']).'</td>
			<td>'.$row['no_masuk'].'</td>
			<td>'.$row['id_tabungan'].'</td>
			<td>'.$row['nama'].'</td>
			<td>'.$row['kelas'].'</td>
			<td class="right">Rp '.format_ribuan($row['jumlah']).'</td>
		</tr>';
	
	// SUB TOTAL per thn_br
	if (@$result[$key+1]['tanggal'] != $row['tanggal']) {
		echo '<tr class="subtotal">
			<td colspan="5" align="center">Total Hari Ini ' . tgl_indo($row['tanggal']) . '</td>
			<td class="right">Rp '.format_ribuan($subtotal_thn).'</td>
		</tr>';
		$subtotal_thn = 0;
	} 
	$total += $row['jumlah'];
}

echo '</tbody>
</table></center>'; ?>

	<br>
	<table width="100%" >
		<tr>
			<td style="padding: 4px">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px">
			Mengetahui</td>
			<td align="center" style="font-weight:bold; padding: 4px;">
			</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td align="center" style="font-weight:bold; padding: 4px;">
			</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px">Bogor, <?php echo indonesian($hariini); ?></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px">Petugas</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td align="center" style="font-weight:bold; padding: 4px;">
			</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px">Kepala Sekolah</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
		</tr>
		<tr>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"><?php echo $nama; ?></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td align="center" style="font-weight:bold; padding: 4px;">
			</td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"><?php echo $nama_jawab; ?></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
		</tr>
	</table>
</body>
</html>