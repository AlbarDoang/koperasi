<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

  <?php include "../dashboard/head.php"; ?>

  <body>
    
    <?php include "../dashboard/icon.php"; ?>
    
    <?php include "../dashboard/header.php"; ?>

    <?php       
    //koneksi
    include "../../koneksi/config.php";
    //fungsi tanggal
    include "../../koneksi/fungsi_indotgl.php";
    //fungsi tanggal
    include "../../koneksi/fungsi_waktu.php";

    $date        = date('Y-m-d');

    $query1      = "SELECT max(id_masuk) as maxKode FROM t_masuk";
    $hasil1      = mysqli_query($con,$query1);
    $data1       = mysqli_fetch_array($hasil1);
    $idmasuk  = $data1['maxKode'];
	
    $idmasuk = $idmasuk + 1;

    $char   = "TM-";
    $acak   = date('is');
    $newID  = $char . $idmasuk . ("($acak)");

    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Check Saldo Tabungan Anggota</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
        <div class="row">
            <div class="col-lg-12 col-md-12">

                <form class="card">
                  <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mt-6 mt-md-0">
                            <label class="form-label"> ID Tabungan</label>
                            <div class="input-group idt">
                            <input type="text" class="form-control" placeholder="ID Tabungan" readonly>
                            </div>                    
                        </div>

                        <div class="col-md-6 mt-6 mt-md-0">
                        <label class="form-label"> Nama Anggota</label>
                        <select
                            class="form-control select2-show-search form-select nama select2" data-placeholder=" - Ketik Nama Anggota - " name="id_tabungan" required>
                        </select>
                        <small class="help-block">Cari Nama Anggota</small>
                        </div>

                        <div class="col-md-1 id">
                        <input type="hidden" id="demo-text-input" class="form-control" name="nama_pengguna"  placeholder=" - Nama Anggota - " readonly>
                        </div>
                    </div>
                  <br>                  
                    <div class="row">
                      <div class="col-md-4 mt-4 mt-md-0">
                        <label class="form-label"> Kelas</label>
                        <div class="col-md-12 kls">
                            <input type="text" name="nama_kelas" class="form-control" placeholder=" - Kelas - " readonly>
                        </div> 
                      </div>
                      <div class="col-md-4">
                          <label class="form-label"> Saldo Tabungan</label>
                        <div class="input-group">
                          <div class="input-group-text">
                            Rp.
                          </div>
                          <select  class="form-control sal" name="saldo" readonly>
                              <option value="Tabungan"> - Saldo Tabungan - </option>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-4 mt-4 mt-md-0">
                          <label class="form-label"> Transaksi Terakhir</label>
                        <div class="input-group">
                            <select  class="form-control tgl" readonly>
                                <option value=""> - Tanggal Terakhir Transaksi - </option>
                            </select>                  
                        </div>
                      </div>
                    </div>
                  <br>
                </form>
            </div>
        </div>
            <!-- End Row -->


        </main>


      </div>
    </div>

    <?php include "../dashboard/js.php"; ?>
    
        
    <!-- INTERNAL Notifications js -->
    <script src="../../../assets/plugins/notify/js/rainbow.js"></script>
    <!--script src="public/assets/plugins/notify/js/sample.js"></script-->
    <script src="../../../assets/plugins/notify/js/jquery.growl.js"></script>
    <script src="../../../assets/plugins/notify/js/notifIt.js"></script>

    <!-- FILE UPLOADES JS -->
    <script src="../../../assets/plugins/fileuploads/js/fileupload.js"></script>
    <script src="../../../assets/plugins/fileuploads/js/file-upload.js"></script>

    <!-- INTERNAL Bootstrap-Datepicker js-->
    <script src="../../../assets/plugins/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- INTERNAL File-Uploads Js-->
    <script src="../../../assets/plugins/fancyuploder/jquery.ui.widget.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.fileupload.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.iframe-transport.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.fancy-fileupload.js"></script>
    <script src="../../../assets/plugins/fancyuploder/fancy-uploader.js"></script>

    <!-- SELECT2 JS -->
    <script src="../../../assets/plugins/select2/select2.full.min.js"></script>

    <!-- BOOTSTRAP-DATERANGEPICKER JS -->
    <script src="../../../assets/plugins/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="../../../assets/plugins/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- INTERNAL Bootstrap-Datepicker js-->
    <script src="../../../assets/plugins/bootstrap-datepicker/bootstrap-datepicker.js"></script>

    <!-- INTERNAL Sumoselect js-->
    <script src="../../../assets/plugins/sumoselect/jquery.sumoselect.js"></script>

    <!-- TIMEPICKER JS -->
    <script src="../../../assets/plugins/time-picker/jquery.timepicker.js"></script>
    <script src="../../../assets/plugins/time-picker/toggles.min.js"></script>

    <!-- INTERNAL intlTelInput js-->
    <script src="../../../assets/plugins/intl-tel-input-master/intlTelInput.js"></script>
    <script src="../../../assets/plugins/intl-tel-input-master/country-select.js"></script>
    <script src="../../../assets/plugins/intl-tel-input-master/utils.js"></script>

    <!-- INTERNAL jquery transfer js-->
    <script src="../../../assets/plugins/jQuerytransfer/jquery.transfer.js"></script>

    <!-- INTERNAL multi js-->
    <script src="../../../assets/plugins/multi/multi.min.js"></script>

    <!-- DATEPICKER JS -->
    <script src="../../../assets/plugins/date-picker/date-picker.js"></script>
    <script src="../../../assets/plugins/date-picker/jquery-ui.js"></script>
    <script src="../../../assets/plugins/input-mask/jquery.maskedinput.js"></script>

    <!-- MULTI SELECT JS-->
    <script src="../../../assets/plugins/multipleselect/multiple-select.js"></script>
    <script src="../../../assets/plugins/multipleselect/multi-select.js"></script>

    <!-- FORMELEMENTS JS -->
    <script src="../../../assets/js/formelementadvnced.js"></script>
    <script src="../../../assets/js/form-elements.js"></script>
    
    <script src="../../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>

		<!-- WYSIWYG Editor JS -->
		<script src="../../../assets/plugins/wysiwyag/jquery.richtext.js"></script>
		<script src="../../../assets/plugins/wysiwyag/wysiwyag.js"></script>

		<!-- SUMMERNOTE JS -->
		<script src="../../../assets/plugins/summernote/summernote-bs4.js"></script>

		<!-- FORMEDITOR JS -->
		<script src="../../../assets/plugins/quill/quill.min.js"></script>
		<script src="../../../assets/js/form-editor2.js"></script>

    <script>
    function hanyaAngka(evt) {
      var charCode = (evt.which) ? evt.which : event.keyCode
       if (charCode > 31 && (charCode < 48 || charCode > 57))
 
        return false;
      return true;
    }
    </script>

    <!-- Data Cari --> 
    <script type="text/javascript">
      //Cari Nama
      $(function(){
     $('.nama').change(function(){
        $('.id').after('<span class="loading">Proses ....</span>');
     $('.id').load('../function/cari/cariid.php?id=' + $(this).val(),function(responseTxt,statusTxt,xhr)
      {
        if(statusTxt=="success")
        $('.loading').remove();
      });
      return false;
        });
      });
      //Cari ID
      $(function(){
     $('.nama').change(function(){
        $('.idt').after('<span class="loading">Proses ....</span>');
     $('.idt').load('../function/cari/idtampil.php?idt=' + $(this).val(),function(responseTxt,statusTxt,xhr)
      {
        if(statusTxt=="success")
        $('.loading').remove();
      });
      return false;
        });
      });
      //Cari Kelas Nasabah
      $(function(){
     $('.nama').change(function(){
        $('.kls').after('<span class="loading">Proses ....</span>');
     $('.kls').load('../function/cari/carikelas.php?kls=' + $(this).val(),function(responseTxt,statusTxt,xhr)
      {
        if(statusTxt=="success")
        $('.loading').remove();
      });
      return false;
        });
      });
      //Cari Saldo
      $(function(){
     $('.nama').change(function(){
        $('.sal').after('<span class="loading">Proses ....</span>');
     $('.sal').load('../function/cari/carisaldo.php?sal=' + $(this).val(),function(responseTxt,statusTxt,xhr)
      {
        if(statusTxt=="success")
        $('.loading').remove();
      });
      return false;
        });
      });
      //Cari tanggal
      $(function(){
     $('.nama').change(function(){
        $('.tgl').after('<span class="loading">Proses ....</span>');
     $('.tgl').load('../function/cari/caritanggal.php?tgl=' + $(this).val(),function(responseTxt,statusTxt,xhr)
      {
        if(statusTxt=="success")
        $('.loading').remove();
      });
      return false;
        });
      });
    </script>
    <!-- END Data Cari -->  

    <script type="text/javascript">
        $(function(){
           $('.select2').select2({
               minimumInputLength: 3,
               allowClear: true,
               placeholder: ' - Ketik Nama Anggota - ',
               ajax: {
                  dataType: 'json',
                  url: '../function/data_select.php',
                  delay: 800,
                  data: function(params) {
                    return {
                      search: params.term
                    }
                  },
                  processResults: function (data, page) {
                  return {
                    results: data
                  };
                },
              }
          });
     });
    </script>
  </body>
</html>
