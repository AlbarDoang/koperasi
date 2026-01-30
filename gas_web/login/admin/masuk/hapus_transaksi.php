<?php
        //koneksi
        include "../../koneksi/config.php";
        //fungsi tanggal
        include "../../koneksi/fungsi_indotgl.php";
        if($_REQUEST['no_masuk']) {
        $no_masuk = $_POST['no_masuk'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM t_masuk WHERE no_masuk = '$no_masuk'");
        while($row = $sql->fetch_assoc()){
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
            <input type="hidden" name="no_masuk" value="<?php echo $row['no_masuk']; ?>">
            <div class="form-group text-center">
                <font color="red" size="25x" ><i class="ion ion-help-circled"></i></font>
                <br>
                <br>
                <br>
                <h4>No Transaksi : <font color="blue" ><?php echo $row['no_masuk']; ?></font></h4>
                <br>
 				<h4>Apa Anda Yakin Ingin Menghapus Transaksi ini ?</h4>
                <br>
			</div>
          <!--Modal footer-->
         <div class="modal-footer">
            <a href="hapus_proses.php?id=<?php echo $row['no_masuk']; ?>"><button class="btn btn-dark" type="button">Ya, Hapus</button></a> 
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
         </div>
        </form>
                      <?php } ?>
                      <?php } ?>