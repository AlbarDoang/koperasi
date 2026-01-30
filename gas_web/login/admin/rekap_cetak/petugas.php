<?php
include "../../koneksi/config.php";
include "../../koneksi/fungsi_indotgl.php";
//fungsi tanggal
include "../../koneksi/fungsi_waktu.php";

include "../../koneksi/pengaturan.php";

session_start();
if( !isset($_SESSION['saya_admin']) )
{
header('location:./../'.$_SESSION['akses']);
exit();
}  
	$id   = ( isset($_SESSION['id_user']) ) ? $_SESSION['id_user'] : '';
	$foto = ( isset($_SESSION['nama_foto']) ) ? $_SESSION['nama_foto'] : '';
	$nama = ( isset($_SESSION['nama_user']) ) ? $_SESSION['nama_user'] : '';

$hariini = date('Y-m-d');

echo '<script>
		window.print();
	  </script>
	<html>
        <body>';
// $filename = "Data-Petugas.xls";

// // Fungsi header dengan mengirimkan raw data excel
// header("Content-type: application/vnd-ms-excel");
 
// // Mendefinisikan nama file ekspor "hasil-export.xls"
// header("Content-Disposition: attachment; filename=$filename");
  if ($_GET['id']=="") {
    $query = mysqli_query($con, "SELECT * FROM user WHERE hak_akses='petugas' ORDER BY id ASC");
   }
    $no=1;?>

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
			<td style="padding: 4px"><img src="../../../assets/brand/logonama.png" height="90"></td>
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
			<td style="padding: 4px"><img src="../../../assets/images/<?php echo  $logo_sekolah; ?>" height="75"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
			<td style="padding: 4px"></td>
		</tr>
	</table>
	<hr  style="border:solid 1px black">
	<br>

<?php
echo "<center><tr colspan='9'><b>Data Petugas Tabungan</b></tr><br>";
echo "<tr><b>&nbsp;</b><br></tr>";
    echo "<table border='1'><thead><tr>
    <th>No</th>
    <th>Nama</th>
    <th>Username</th>
    <th>Transaksi</th>
    <th>Last Login</th>
    </tr></thead>";    
    echo "<tbody>";   
    while ($r = mysqli_fetch_array($query)){
        $tanggalku = indonesian_date_full($r['last_login']);
        $userku = $r['nama'];
       echo "<tr><td>$no</td>
             <td>$r[nama]</td>
             <td>$r[username]</td>";
             
    // Hitung Jumlah Transaksi
    $trans = "SELECT * FROM transaksi WHERE namauser='$userku'";
    $qtrans = mysqli_query($con,$trans);
    $tottrans = mysqli_num_rows($qtrans);

       echo "<td>$tottrans Kali</td>
            <td>$tanggalku</td>";
      $no++;
    }
    echo '</tbody></table></center>'; ?>

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
			<td style="padding: 4px">Bogor, <?php echo tgl_indo($hariini); ?></td>
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