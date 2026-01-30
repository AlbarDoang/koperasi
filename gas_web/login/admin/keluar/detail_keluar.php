<?php
        //koneksi
        include "../../koneksi/config.php";
        if($_REQUEST['no_keluar']) {
        $no_keluar = $_POST['no_keluar'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM t_keluar WHERE no_keluar = '$no_keluar'");
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
                <input class="form-control" value="<?php echo $row['no_keluar']; ?>" readonly>             
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
                <td><label>Jumlah</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="Rp <?php echo number_format($row['jumlah']) ?>" readonly>                       
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
                <input class="form-control" value="<?php echo $row['keterangan']; ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Status</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo ucfirst($row['status']); ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
            <?php if (!empty($row['approved_by']) || !empty($row['approved_at'])) { ?>
            <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Disetujui Oleh / Waktu</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo htmlspecialchars($row['approved_by'] . ' / ' . ($row['approved_at'] ?? '')); ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
            <?php } ?>
        </font>
        </form>
                      <?php } ?>
                      <?php } ?>