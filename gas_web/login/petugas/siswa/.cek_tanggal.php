<div id="exportexcel5" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
          <!--Modal header-->
          <div class="modal-header">    
            <h5 class="modal-title text-center">Export Transaksi Tanggal</h5>
          </div>
          <!--Modal body-->
          
          <div class="modal-body" align="center">
            <form action="../../../login/admin/rekap_cetak/export_tanggal_siswa1.php" method="post" target="_blank">
                <div class="form-group">
                    <label>Dari Tanggal</label>
                    <input class="form-control" type="date" name="tgl_a" required>
                    </div>
                    <div class="form-group">
                    <label>Sampai Tanggal</label>
                    <input class="form-control" type="date" name="tgl_b" required>
                    </div>
                    <input class="form-control" type="hidden" name="id_tabungan" value="<?php echo $id_tabungan ?>" required>
                    <br>
                    <div class="modal-footer">
                    <input type="submit" name="cetak_tanggal1" value="Print" class="btn btn-success">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </form>   
          </div>
    </div>
  </div>
</div>