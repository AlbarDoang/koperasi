<?php
        //koneksi
        include "../../koneksi/config.php";
        // fungsi tanggal
        include "../../koneksi/fungsi_indotgl.php";

        // prefer id_transaksi (numeric) or fallback to no_transfer
        if (isset($_POST['id_transaksi']) && is_numeric($_POST['id_transaksi'])) {
            $id_trans = intval($_POST['id_transaksi']);
            $sql = $con->query("SELECT id_transaksi, id_anggota, jenis_transaksi, jumlah, keterangan, tanggal FROM transaksi WHERE id_transaksi = $id_trans LIMIT 1");
            if ($sql && $sql->num_rows > 0) {
                $row = $sql->fetch_assoc();
                ?>
                <form>
                <font color=black>
                    <div class="form-group">
                        <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                        <tr>
                        <td><label>ID Transaksi</label></td>
                        <td>&nbsp;</td>
                        </tr>
                        <tr><td>
                        <input class="form-control" value="<?php echo htmlspecialchars($row['id_transaksi']); ?>" readonly>
                        </td>
                        </td><td>&nbsp;</td>
                        </tr>
                        </table>
                    </div>
                <hr/>
                    <div class="form-group">
                        <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                        <tr>
                        <td><label>ID Akun</label></td>
                        <td>&nbsp;</td>
                        <td><label>Jenis</label></td>
                        <td>&nbsp;</td>
                        </tr>
                        <tr><td>
                        <input class="form-control" value="<?php echo htmlspecialchars($row['id_anggota']); ?>" readonly>
                        </td>
                        <td>&nbsp;</td><td>
                        <input class="form-control" value="<?php echo ($row['jenis_transaksi'] === 'transfer_masuk' ? 'Transfer Masuk' : 'Transfer Keluar'); ?>" readonly>
                        </td>
                        <td>&nbsp;</td>
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
                        <input class="form-control" value="Rp <?php echo number_format($row['jumlah'],0,',','.'); ?>" readonly>
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
                        <td><label>Tanggal</label></td>
                        <td>&nbsp;</td>
                        </tr>
                        <tr><td>
                        <input class="form-control" value="<?php echo htmlspecialchars($row['keterangan']); ?>" readonly>
                        </td>
                        <td>&nbsp;</td><td>
                        <input class="form-control" value="<?php echo tgl_indo($row['tanggal']); ?>" readonly>
                        </td>
                        <td>&nbsp;</td>
                        </tr>
                        </table>
                    </div>
                </font>
                </form>
                <?php
            } else {
                echo '<div class="alert alert-warning">Data transaksi tidak ditemukan.</div>';
            }
        } elseif (isset($_POST['no_transfer'])) {
        $no_transfer = $con->real_escape_string($_POST['no_transfer']);
        // mengambil data berdasarkan id dari t_transfer
        $sql = $con->query("SELECT * FROM t_transfer WHERE no_transfer = '$no_transfer'");
        if ($sql && $sql->num_rows > 0) {
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
                <input class="form-control" value="<?php echo $row['no_transfer']; ?>" readonly>             
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
			</div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Nama Pengirim</label></td>
                <td>&nbsp;</td>
                <td><label>Kelas Pengirim</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $row['nama_pengirim']; ?>" readonly>          
                </td>
                <td>&nbsp;</td><td>
                <input class="form-control" value="<?php echo $row['kelas_pengirim']; ?>" readonly>          
                </td>
                <td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Nominal Transfer</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="Rp <?php echo number_format($row['nominal']) ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Nama Penerima</label></td>
                <td>&nbsp;</td>
                <td><label>Kelas Penerima</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $row['nama_penerima']; ?>" readonly>          
                </td>
                <td>&nbsp;</td><td>
                <input class="form-control" value="<?php echo $row['kelas_penerima']; ?>" readonly>          
                </td>
                <td>&nbsp;</td>
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
        </font>
        </form>
                      <?php } } else { echo '<div class="alert alert-warning">Data transfer tidak ditemukan.</div>'; } } else { echo '<div class="alert alert-warning">Parameter tidak diberikan.</div>'; } ?>