<?php
//koneksi
include "../../koneksi/config.php";
if($_REQUEST['no_masuk']) {
$no_masuk = $_POST['no_masuk'];
// mengambil data berdasarkan id
$sql = $con->query("SELECT * FROM t_masuk WHERE no_masuk = '$no_masuk'");
while($row = $sql->fetch_assoc()){
$uang       =   number_format($row['jumlah']);
?>

<!-- MEMBUAT FORM -->
<form class="text-center">
    <input type="hidden" name="no_masuk" value="<?php echo $row['no_masuk']; ?>">
    <img src="../../../Assets/barcode/barcode.php?s=qr&d=<?php echo "$row[no_masuk]"?>&p=-8&h=400&w=400"><br>
    <h3><font color=black>Scan Barcode Tabungan Masuk</font></h3>
</form>
                <?php } ?>
                <?php } ?>