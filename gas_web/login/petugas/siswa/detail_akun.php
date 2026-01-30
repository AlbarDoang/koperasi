<?php
        //koneksi
        include "../../koneksi/config.php";
        if($_REQUEST['id_siswa']) {
        $id_pengguna = $_POST['id_siswa'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM pengguna WHERE id = '$id_pengguna'");
        while($row = $sql->fetch_assoc()){
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
        <font color=black>
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
                <td><label>Username</label></td>
                <td>&nbsp;</td>
                <td><label>Password</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo $row['username']; ?>" readonly>                
                </td><td>&nbsp;</td><td>
                <input class="form-control" value="<?php echo $row['password2']; ?>" readonly>                      
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
        </font>
        </form>
                      <?php } ?>
                      <?php } ?>