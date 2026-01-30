<div id="exportexcel5" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
          <!--Modal header-->
          <div class="modal-header">    
            <h5 class="modal-title text-center">Export Transaksi Tanggal</h5>
          </div>
          <!--Modal body-->
          
          <div class="modal-body" align="center">
            <form action="../rekap_cetak/export_tanggal_keluar.php" method="post" target="_blank">
                <div class="form-group">
                    <label>Dari Tanggal</label>
                    <input class="form-control" type="date" name="tgl_a" required>
                    </div>
                    <div class="form-group">
                    <label>Sampai Tanggal</label>
                    <input class="form-control" type="date" name="tgl_b" required>
                    </div>
                    <br>
                    <div class="modal-footer">
                    <input type="submit" name="cetak_tanggal1" value="Print" class="btn btn-success">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                    <div>
                </div>
            </form>   
          </div>
    </div>
  </div>
</div>