<?php
//koneksi
include "../../koneksi/config.php";
if($_REQUEST['no_transfer']) {
$no_transfer = $_POST['no_transfer'];
// mengambil data berdasarkan id
$sql = $con->query("SELECT * FROM t_transfer WHERE no_transfer = '$no_transfer'");
while($row = $sql->fetch_assoc()){
$uang       =   number_format($row['nominal']);
?>

<!-- MEMBUAT FORM -->
<form class="text-center">
    <input type="hidden" name="no_transfer" value="<?php echo $row['no_transfer']; ?>">
    <img src="../../../Assets/barcode/barcode.php?s=qr&d=<?php echo "$row[no_transfer]"?>&p=-8&h=400&w=400"><br>
    <h3><font color=black>Scan Barcode Tabungan Transfer</font></h3>
</form>
                <?php } ?>
                <?php } ?>