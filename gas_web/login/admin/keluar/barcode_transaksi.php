<?php
//koneksi
include "../../koneksi/config.php";
if($_REQUEST['no_keluar']) {
$no_keluar = $_POST['no_keluar'];
// mengambil data berdasarkan id
$sql = $con->query("SELECT * FROM t_keluar WHERE no_keluar = '$no_keluar'");
while($row = $sql->fetch_assoc()){
$uang       =   number_format($row['jumlah']);
?>

<!-- MEMBUAT FORM -->
<form class="text-center">
    <input type="hidden" name="no_keluar" value="<?php echo $row['no_keluar']; ?>">
    <img src="../../../Assets/barcode/barcode.php?s=qr&d=<?php echo "$row[no_keluar]"?>&p=-8&h=400&w=400"><br>
    <h3><font color=black>Scan Barcode Penarikan Tabungan</font></h3>
</form>
                <?php } ?>
                <?php } ?>