<div id="cekdetail" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
          <!--Modal header-->
          <div class="modal-header">
            <h5 class="modal-title text-center">Cek Transaksi Detail</h5>
          </div>
          <!--Modal body-->
          <div class="modal-body" align="center">
            <?php echo "<a target='_blank' href='../../../login/admin/function/cek/1cek_keluar.php'>"; ?>
            <button type="button" class="btn btn-dark"><i class="fe fe-printer me-2"></i>Print Data</button>
            <?php echo "</a>";?> 
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <?php echo "<a href='../../../login/admin/rekap_cetak/cek_keluar.php'>"; ?>
            <button type="button" class="btn btn-success"><i class="fe fe-save me-2"></i>Export Excel</button>
            <?php echo "</a>";?>
          </div>
          <!--Modal footer-->
         <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
         </div>
    </div>
  </div>
</div>