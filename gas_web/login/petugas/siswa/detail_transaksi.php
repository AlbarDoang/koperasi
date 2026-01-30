<?php
        //koneksi
        include "../../koneksi/config.php";
        if($_REQUEST['no_transaksi']) {
        $no_transaksi = $_POST['no_transaksi'];
        $id_tabungan  = $_GET['id_tabungan'];

        // mengambil data berdasarkan id        
        $sql = $con->query("SELECT * FROM transaksi WHERE id_tabungan='$id_tabungan' AND no_masuk = '$no_transaksi' OR id_tabungan='$id_tabungan' AND no_keluar = '$no_transaksi'");

        while($row = $sql->fetch_assoc()){
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
        <font color=black>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>No Transaksi</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $no_transaksi; ?>" readonly>          
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>ID Tabungan</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $row['id_tabungan']; ?>" readonly>          
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Nama</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $row['nama']; ?>" readonly>          
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Kelas</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $row['kelas']; ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Kegiatan</label></td>
                <td>&nbsp;</td>
                <td><label>Jumlah</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $row['kegiatan']; ?>" readonly>                
                </td><td>&nbsp;</td><td>
                <input class="form-control" value="Rp <?php 
                if (empty($row['no_masuk'])) {
                echo number_format($row['nominal_k']); 
                } else {
                echo number_format($row['nominal_m']); 
                }  
                ?>" readonly>                      
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Keterangan</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php 
                $ket = $row['keterangan'];
                if ($ket=="Tabungan Masuk") {
                    echo "-"; 
                } else {
                    echo $row['keterangan'];
                }
                ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
        </font>
        </form>
                      <?php } ?>
                      <?php } ?>