<?php
include "../../../koneksi/koneksi_export.php";
include "../../../koneksi/fungsi_indotgl.php";
//koneksi
include "../../../koneksi/config.php";
include "../../../koneksi/pengaturan.php";
include "../../../koneksi/fungsi_waktu.php";

session_start();
if( !isset($_SESSION['saya_petugas']) )
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
$id_tabungan = $_GET['id_tabungan'];

$sql = 'SELECT *, SUM(nominal_m) AS nominal_m, SUM(nominal_k) AS nominal_k FROM transaksi WHERE id_tabungan='.$id_tabungan.' GROUP BY tanggal, no_masuk, no_keluar, nama, id_tabungan, kelas, kegiatan';
$stmt = $pdo->prepare($sql);
$stmt->execute();

echo '<script>
		window.print();
	  </script>
	<html>
		<head>
			<title>Transaksi Tabungan Siswa</title>
		<link rel="shortcut icon" type="image/png" href="../../../../assets/brand/logo.png" />
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
        $queryx = "SELECT * FROM pengguna WHERE id_tabungan ='$id_tabungan'";
        $sqlx   = mysqli_query($con, $queryx); // Eksekusi Jalankan query dari variabel $query
        while($rowx = mysqli_fetch_array($sqlx)){
echo "<center><tr><div align='center'><h2>Laporan Transaksi Tabungan Siswa</h2>
			</div><b>&nbsp;</b></tr>";
echo '<div align="center">
    <table>
		<thead>
			<tr>
                <th width="15%"></th>
                <th width="10%"></th>
                <th width="20%"></th>
                <th width="30%"></th>
			</tr>
		</thead>
		<tbody>
            <tr>
                <td>
                <strong class="text-uppercase">ID Tabungan</strong>
                </td>
                <td align="center">:</td>
                <td class="text-left"><b>'.$rowx['id_tabungan'].'</b></td>
                <td align="center"></td>
            </tr>
            <tr>
                <td>
                <strong class="text-uppercase">Nama</strong>
                </td>
                <td align="center">:</td>
                <td class="text-left"><b>'.$rowx['nama'].'</b></td>
                <td align="center"></td>
            </tr>
            <tr>
                <td>
                <strong class="text-uppercase">Kelas</strong>
                </td>
                <td align="center">:</td>
                <td class="text-left"><b>'.$rowx['kelas'].'</b></td>
                <td align="center"></td>
            </tr>
            <tr>
                <td>
                <strong class="text-uppercase">L/P</strong>
                </td>
                <td align="center">:</td>
                <td class="text-left"><b>'.$rowx['jk'].'</b></td>
                <td align="center"></td>
            </tr>
            <tr>
                <td>
                <strong class="text-uppercase">Saldo</strong>
                </td>
                <td align="center">:</td>
	        	<td class="text-left"><b>Rp '.format_ribuan($rowx['saldo']).'</b></td>
                <td align="center"></td>
            </tr>
		</tbody>
	</table><br>';
	}
echo "<tr><b>&nbsp;</b></tr>";
echo '<table border="1px" >
		<thead style="background: #CCCCCC; font-size: 12px; border-color:#B0B0B0">
			<tr>
			    <th>Tanggal</th>
			    <th>No Transaksi</th>
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
		// echo '<tr class="subtotal">
		// 	<td colspan="3" align="center">Total Pertanggal ' . tgl_indo($row['tanggal']) . '</td>
		// 	<td class="right">Rp '.format_ribuan($subtotal_thn).'</td>
		// 	<td class="right">Rp '.format_ribuan($subtotal_thn2).'</td>
		// </tr>';
		$subtotal_thn = 0;
		$subtotal_thn2 = 0;
	} 
	$total += $row['nominal_m'];
	$total2 += $row['nominal_k'];
}

echo '<tr class="total">
		<td colspan="3" align="center">Total Tabungan</td>
		<td align="center">Rp ' . format_ribuan($total) . '</td>
		<td align="center">Rp ' . format_ribuan($total2) . '</td>
	</tr>
	<tr class="subtotal">
		<td colspan="3" align="center">Total Saldo Tabungan Siswa</td>
		<td colspan="2" align="center"><b>Rp ' . format_ribuan($total-$total2) . '</b></td>
	</tr>
	</tbody>
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