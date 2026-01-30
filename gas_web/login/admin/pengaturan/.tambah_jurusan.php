<div id="tambahjurusan" class="modal fade" role="dialog">
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
                    <label>Nama Jurusan</label>
                    <input class="form-control" type="text" name="nama" required>
                    </div>
                    <div class="form-group">
                    <hr>
                    <label>Singkatan</label>
                    <input class="form-control" type="text" name="singkatan" required>
                    </div>
                    <hr>
                    <div class="form-group">
                    <label>Unit</label>
                    <select class="form-control" data-placeholder="Choose one" name="unit" required>
                            <option value="All"> Semua Unit (All)</option>
                            <option value="SD"> Sekolah Dasar (SD)</option>
                            <option value="SMP"> Sekolah Menengah Pertama (SMP)</option>
                            <option value="SMA"> Sekolah Menengah Atas (SMA)</option>
                            <option value="SMK"> Sekolah Menengah Kejuruan (SMK)</option>
                        </select>
                    </div>
                    <br>
                    <div class="modal-footer">
                    <input type="submit" name="tjurusan" value="Tambah Jurusan" class="btn btn-success">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </form>   
          </div>
    </div>
  </div>
</div>