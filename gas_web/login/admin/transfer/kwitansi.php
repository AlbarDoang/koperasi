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
    //fungsi angka ke text
    include "../../koneksi/terbilang.php";

    if($_REQUEST['no_transfer']) {
    $no_transfer = $_GET['no_transfer'];
    // mengambil data berdasarkan id
    $sql = $con->query("SELECT * FROM t_transfer WHERE no_transfer = '$no_transfer'");
    while($row = $sql->fetch_assoc()){
    $tanggal    =   tgl_indo($row['tanggal']);
    $uang       =   number_format($row['nominal']);

    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Kwitansi Transaksi Tabungan</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="clearfix">
                                <div class="float-start">
                                    <h5 >Kwitansi Transaksi</h5>
                                    <h3 class="card-title mb-0">#<?php echo $no_transfer ?></h3>
                                </div>
                                <div class="float-end">
                                    <h3 class="card-title" align="right"><img src="../../../assets/brand/logo.png" width="50%"></h3>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-lg-6 ">
                                    <address>
                                        <h5><button type="button" class="btn btn-success btn-sm mb-1">Berhasil</button></h5>
                                        <h5><?php echo hariindo($row['tanggal']); ?></h5>
                                        <h5><?php echo $tanggal; ?></h5>
                                        <h5>Transfer Tabungan</h5>
                                    </address>
                                </div>
                                <div class="col-lg-6 text-end">
                                    <address>
                                        <a href="#myMod" id="custId" data-bs-toggle="modal" data-id="<?php echo $row['no_transfer']; ?>" title="Klik Untuk Memperbesar">
                                        <img src="../../../assets/barcode/barcode.php?s=qr&d=<?php echo "$row[no_transfer]"?>&p=-8&h=100&w=100"></a><br>
                                        Scan Barcode
                                    </address>
                                </div>
                            </div>                              
                            <div class="row">
                                <div class="col-lg-12 table-responsive">
                                    <table class="table invoice-summary">
                                        <thead>
                                            <tr>
                                                <th width="10%"></th>
                                                <th width="10%"></th>
                                                <th width="30%"></th>
                                                <th width="20%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">ID Pengirim</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo $row['id_pengirim']; ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Nama Pengirim</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo $row['nama_pengirim']; ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Kelas Pengirim</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo $row['kelas_pengirim']; ?></b></td>
                                            </tr>
                                            <tr>
                                                <td class="text-center"></td>
                                                <td class="text-center"></td>
                                                <td class="text-center"></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">ID Penerima</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo $row['id_penerima']; ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Nama Penerima</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo $row['nama_penerima']; ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Kelas Penerima</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo $row['kelas_penerima']; ?></b></td>
                                            </tr>
                                            <tr>
                                                <td class="text-center"></td>
                                                <td class="text-center"></td>
                                                <td class="text-center"></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Keterangan</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo $row['keterangan']; ?></b></td>
                                            </tr>
                                        </tbody>
                                        <thead>
                                            <tr>
                                                <td class="text-uppercase" width="20%"><b>Nominal</b></td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b>Rp. <?php echo number_format($row['nominal']) ?></b></td>
                                            </tr>
                                        </thead>
                                        <thead>
                                            <tr>
                                                <td colspan="4"><h4><?php echo penyebut($row['nominal']) ?> Rupiah</h4></td>
                                            </tr>
                                        </thead>
                                    </table>
                        Kwitansi Resmi Transaksi di Tabungan Siswa <?php echo $nama_sekolah; ?> Pada <?php echo indonesian_date_full($row['waktu']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end no-print">

                            <a href="print_kwitansi?no_transfer=<?php echo $no_transfer; ?>" target="_blank">
                            <button type="button" class="btn btn-primary"><i class="si si-printer me-2"></i>Print</button>
                            </a>

                            <a href="../transfer/">
                            <button type="button" class="btn btn-danger"><i class="fe fe-x-square me-2"></i>Kembali</button>
                            </a>
                        </div>
                    </div>
                </div><!-- COL-END -->
            </div>
            <!-- End Row -->
            
            <?php } ?>
            <?php } ?>

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

    
    <!-- modal detail -->
    <div id="myMod" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <!--Modal body-->
          <div class="modal-body">
            <div class="fetched-data"></div>
          </div>
          <!--Modal footer-->
         <div class="modal-footer">
              <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
         </div>
        </div>
      </div>
    </div>
    <!-- END modal detail -->

    <!-- Data Modal-->
    <script type="text/javascript">
      $(document).ready(function(){
          $('#myMod').on('show.bs.modal', function (e) {
              var rowid = $(e.relatedTarget).data('id');
              //menggunakan fungsi ajax untuk pengambilan data
              $.ajax({
                  type : 'post',
                  url : 'barcode_transaksi.php',
                  data :  'no_transfer='+ rowid,
                  success : function(data){
                  $('.fetched-data').html(data);//menampilkan data ke dalam modal
                  }
              });
           });
      });
    </script>
    <!-- END Data Modal-->
  </body>
</html>
