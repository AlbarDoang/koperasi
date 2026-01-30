<?php
        //koneksi
        include "../../koneksi/config.php";
        //fungsi tanggal
        include "../../koneksi/fungsi_indotgl.php";
        if($_REQUEST['id_jur']) {
        $id_jur = $_POST['id_jur'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM jurusan WHERE id_jur = '$id_jur'");
        while($row = $sql->fetch_assoc()){
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
            <input type="hidden" name="id_jur" value="<?php echo $row['id_jur']; ?>">
            <div class="form-group text-center">
                <font color="red" size="25x" ><i class="ion ion-help-circled"></i></font>
                <br>
                <br>
                <br>
                <h4>Jurusan : <font color="blue" ><?php echo $row['nama_jurusan']; ?></font></h4>
                <br>
 				<h4>Apa Anda Yakin Ingin Menghapus Jurusan ini ?</h4>
                <br>
			</div>
          <!--Modal footer-->
         <div class="modal-footer">
            <a href="hapus_jurusan_proses.php?id=<?php echo $row['id_jur']; ?>"><button class="btn btn-dark" type="button">Ya, Hapus</button></a> 
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
         </div>
        </form>
                      <?php } ?>
                      <?php } ?>