<?php
        //koneksi
        include "../../koneksi/config.php";
        //fungsi tanggal
        include "../../koneksi/fungsi_indotgl.php";
        if($_REQUEST['id_kel']) {
        $id_kel = $_POST['id_kel'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM kelas WHERE id_kel = '$id_kel'");
        while($row = $sql->fetch_assoc()){
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
            <input type="hidden" name="id_kel" value="<?php echo $row['id_kel']; ?>">
            <div class="form-group text-center">
                <font color="red" size="25x" ><i class="ion ion-help-circled"></i></font>
                <br>
                <br>
                <br>
                <h4>Kelas : <font color="blue" ><?php echo $row['tingkat'] . ' ' . $row['singkatan'] . ' ' . $row['rombel']  ?></font></h4>
                <br>
 				<h4>Apa Anda Yakin Ingin Menghapus Kelas ini ?</h4>
                <br>
			</div>
          <!--Modal footer-->
         <div class="modal-footer">
            <a href="hapus_kelas_proses.php?id=<?php echo $row['id_kel']; ?>"><button class="btn btn-dark" type="button">Ya, Hapus</button></a> 
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
         </div>
        </form>
                      <?php } ?>
                      <?php } ?>