<div id="tambahkelas" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
          <!--Modal header-->
          <div class="modal-header">    
            <h5 class="modal-title text-center">Export Transaksi Tanggal</h5>
          </div>
          <!--Modal body-->
          
          <div class="modal-body" align="center">
            <form action="../pengaturan/" method="post" name="frm" >
                <div class="form-group">
                    <label>Tingkat</label>
                    <select class="form-control" data-placeholder="Choose one" name="tingkat" required>
                            <option> -- Pilih Tingkat -- </option>
                            <option value="1"> 1 | Satu </option>
                            <option value="2"> 2 | Dua </option>
                            <option value="3"> 3 | Tiga </option>
                            <option value="4"> 4 | Empat </option>
                            <option value="5"> 5 | Lima </option>
                            <option value="6"> 6 | Enam </option>
                            <option value="7"> 7 | Tujuh </option>
                            <option value="8"> 8 | Delapan </option>
                            <option value="9"> 9 | Sembilan </option>
                            <option value="10"> 10 | Sepuluh </option>
                            <option value="11"> 11 | Sebelas </option>
                            <option value="12"> 12 | Dua Belas </option>
                    </select>
                    </div>
                    <div class="form-group">
                    <hr>
                    <label>Jurusan</label>
                    <select class="form-control" data-placeholder="Choose one" name="singkatan" required>
                            <option> -- Pilih Jurusan -- </option>                            
                            <?php
                            $no = 1;
                            $query11 = "SELECT * FROM jurusan ORDER BY id_jur DESC";
                            $sql11 = mysqli_query($con, $query11); 
                            while($data = mysqli_fetch_array($sql11)){
                            ?>
                            <option value="<?php echo $data['id_jur'] ?>"> <?php echo $data['nama_jurusan'] ?> </option>
                            <?php } ?>
                    </select>
                    </div>
                    <hr>
                    <div class="form-group">
                    <label>Rombel</label>
                    <select class="form-control" data-placeholder="Choose one" name="rombel" required>
                            <option> -- Pilih Rombel -- </option>
                            <optgroup label="Rombel Angka">
                                <option value="1"> 1 | Satu </option>
                                <option value="2"> 2 | Dua </option>
                                <option value="3"> 3 | Tiga </option>
                                <option value="4"> 4 | Empat </option>
                                <option value="5"> 5 | Lima </option>
                                <option value="6"> 6 | Enam </option>
                                <option value="7"> 7 | Tujuh </option>
                                <option value="8"> 8 | Delapan </option>
                                <option value="9"> 9 | Sembilan </option>
                                <option value="10"> 10 | Sepuluh </option>
                                <option value="11"> 11 | Sebelas </option>
                                <option value="12"> 12 | Dua Belas </option>
                                <option value="13"> 13 | Tiga Belas </option>
                                <option value="14"> 14 | Empat Belas </option>
                                <option value="15"> 15 | Lima Belas </option>
                            </optgroup>
                            <optgroup label="Rombel Huruf">
                                <option value="A"> A </option>
                                <option value="B"> B </option>
                                <option value="C"> C </option>
                                <option value="D"> D </option>
                                <option value="E"> E </option>
                                <option value="F"> F </option>
                                <option value="G"> G </option>
                                <option value="H"> H </option>
                                <option value="I"> I </option>
                                <option value="J"> J </option>
                                <option value="K"> K </option>
                                <option value="L"> L </option>
                                <option value="M"> M </option>
                                <option value="N"> N </option>
                                <option value="O"> O </option>
                            </optgroup>
                    </select>
                    </div>
                    <br>
                    <div class="modal-footer">
                    <input type="submit" name="tkelas" value="Tambah Kelas" class="btn btn-success">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </form>   
          </div>
    </div>
  </div>
</div>