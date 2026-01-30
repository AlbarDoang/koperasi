<?php
        //koneksi
        include "../../koneksi/config.php";
        //fungsi tanggal
        include "../../koneksi/fungsi_indotgl.php";
        if($_REQUEST['no_keluar']) {
        $no_keluar = $_POST['no_keluar'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM t_keluar WHERE no_keluar = '$no_keluar'");
        while($row = $sql->fetch_assoc()){
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
            <input type="hidden" name="no_keluar" value="<?php echo $row['no_keluar']; ?>">
            <div class="form-group text-center">
                <font color="red" size="25x" ><i class="ion ion-help-circled"></i></font>
                <br>
                <br>
                <br>
                <h4>No Transaksi : <font color="blue" ><?php echo $row['no_keluar']; ?></font></h4>
                <br>
 				<h4>Apa Anda Yakin Ingin Menghapus Transaksi ini ?</h4>
                <br>
			</div>
          <!--Modal footer-->
         <div class="modal-footer">
            <a href="hapus_proses.php?id=<?php echo $row['no_keluar']; ?>"><button class="btn btn-dark" type="button">Ya, Hapus</button></a> 
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
         </div>
        </form>
                      <?php } ?>
                      <?php } ?>