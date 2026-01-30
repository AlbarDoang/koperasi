<?php
include "../../koneksi/config.php";
include "../../koneksi/fungsi_indotgl.php";
//fungsi tanggal
include "../../koneksi/fungsi_waktu.php";

$filename = "Data-Registrasi-Siswa-Baru.xls";
// Fungsi header dengan mengirimkan raw data excel
//header("Content-type: application/vnd-ms-excel");
 
// Mendefinisikan nama file ekspor "hasil-export.xls"
//header("Content-Disposition: attachment; filename=$filename");

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
  if ($_GET['id']=="") {
    $query = mysqli_query($con, "SELECT * FROM registrasi ORDER BY id_reg DESC");
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
echo "<center><tr colspan='9'><b>Data Registrasi Siswa Baru</b></tr>";
echo "<tr><b>&nbsp;</b><br></tr>";
    echo "<table border='1'><thead><tr>
    <th>No</th>
    <th>Nama</th>
    <th>No Whatsapp</th>
    <th>Email</th>
    <th>Nama Ayah</th>
    <th>Nama Ibu</th>
    <th>No Tanda Pengenal</th>
    <th>Tanggal Registrasi</th>
    </tr></thead>";    
    echo "<tbody>";   
    while ($r = mysqli_fetch_array($query)){
        $tanggalku = indonesian_date_full($r['waktu']);
       echo "<tr><td>$no</td>
             <td>$r[nama]</td>
             <td>'$r[no_wa]</td>
             <td>$r[email]</td>
             <td>$r[nama_ayah]</td>
             <td>$r[nama_ibu]</td>
             <td>'$r[no_tandapengenal]</td>
             <td>$tanggalku</td>";
      $no++;
    }
    echo "</tbody></table></center>"; ?>

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