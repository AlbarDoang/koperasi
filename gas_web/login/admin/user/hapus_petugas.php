<?php
        //koneksi
        include "../../koneksi/config.php";
        //fungsi tanggal
        include "../../koneksi/fungsi_indotgl.php";
        if($_REQUEST['id']) {
        $id = $_POST['id'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM user WHERE id = '$id'");
        while($row = $sql->fetch_assoc()){
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
            <div class="form-group text-center">
                <font color="red" size="25x" ><i class="ion ion-help-circled"></i></font>
                <br>
                <br>
                <br>
                <h4>Nama Petugas : <font color="#FF4C00" ><?php echo $row['nama']; ?></font></h4>
                <br>
 				<h4>Apa Anda Yakin Ingin Menghapus Petugas ini ?</h4>
                <br>
			</div>
          <!--Modal footer-->
         <div class="modal-footer">
            <a href="hapus_petugas_proses.php?id=<?php echo $row['id']; ?>"><button class="btn btn-dark" type="button">Ya, Hapus</button></a> 
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
         </div>
        </form>
                      <?php } ?>
                      <?php } ?>