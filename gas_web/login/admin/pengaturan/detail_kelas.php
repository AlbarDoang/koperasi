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

    //hari ini
    $hariini = date('Y-m-d');

    $id_kel = $_GET['id_kel'];
    $query11 = "SELECT * FROM kelas WHERE id_kel='$id_kel'";
    $sql11 = mysqli_query($con, $query11); 
    while($data = mysqli_fetch_array($sql11)){
    $kelasku = $data['tingkat'] . ' ' . $data['singkatan'] . ' ' . $data['rombel'];
    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Detail Kelas <?php echo $kelasku; ?></h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header" align="center">
                            <a href="../rekap_cetak/detail_kelas?id_kel=<?php echo $id_kel; ?>" target="_BLANK">
                            <button type="button" class="btn btn-success"><i class="fe fe-save me-2"></i>Download Kelas <?php echo $kelasku; ?></button>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="example2" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">ID</th>
                                            <th class="text-center">Nama</th>
                                            <th class="text-center">L/P</th>
                                            <th class="text-center">No Hp</th>
                                            <th class="text-center">Saldo</th>
                                            <th width="5%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                            <?php
                                            $no = 1;
                                            $query1 = "SELECT * FROM pengguna WHERE kelas='$kelasku' ORDER BY nama ASC";
                                            $sql1 = mysqli_query($con, $query1); 
                                            while($data1 = mysqli_fetch_array($sql1)){
                                            ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++ ?></td>
                                            <td class="text-center"><?php echo $data1['id_tabungan'];  ?></td>
                                            <td class="text-center"><?php echo $data1['nama'];  ?></td>
                                            <td class="text-center"><?php echo $data1['jk'];  ?></td>
                                            <td class="text-center"><?php echo $data1['no_wa'];  ?></td>
                                            <td class="text-center">Rp <?php echo number_format($data1['saldo'])  ?></td>
                                            <td class="text-center">
                                                <a href="#myModal" id="custId" data-bs-toggle="modal" data-id="<?php echo $data1['id']; ?>">
                                                <button type="button" class="btn btn-dark btn-sm btn-icon" title="Detail Akun"><i class="fe fe-unlock icon-lg"></i></button></a>
                                                
                                                <a href="../siswa/buku_tabungan.php?id_siswa=<?php echo $data1['id']; ?>"><button type="button" class="btn btn-success btn-sm btn-icon" title="Cek Buku Tabungan"><i class="fe fe-file-text"></i></button></a>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Row -->


        </main>


      </div>
    </div>

    <?php } include "../dashboard/js.php"; ?>
        
    <!-- DATA TABLE JS-->
    <script src="../../../assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
    <script src="../../../assets/plugins/datatable/js/dataTables.bootstrap5.js"></script>
    <script src="../../../assets/plugins/datatable/js/dataTables.buttons.min.js"></script>
    <script src="../../../assets/plugins/datatable/js/buttons.bootstrap5.min.js"></script>
    <script src="../../../assets/plugins/datatable/js/jszip.min.js"></script>
    <script src="../../../assets/plugins/datatable/pdfmake/pdfmake.min.js"></script>
    <script src="../../../assets/plugins/datatable/pdfmake/vfs_fonts.js"></script>
    <script src="../../../assets/plugins/datatable/js/buttons.html5.min.js"></script>
    <script src="../../../assets/plugins/datatable/js/buttons.print.min.js"></script>
    <script src="../../../assets/plugins/datatable/js/buttons.colVis.min.js"></script>
    <script src="../../../assets/plugins/datatable/dataTables.responsive.min.js"></script>
    <script src="../../../assets/plugins/datatable/responsive.bootstrap5.min.js"></script>
    <script src="../../../assets/js/table-data.js"></script>    
        
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

      <!-- Data Modal-->
        <script type="text/javascript">
            $(document).ready(function() {
                $('#example2').DataTable({
                "lengthMenu": [[10, 100, -1], [10, 100, "All"]],
                "scrollX":true,
                });
            });
        </script>
      <!-- END Data Modal-->

      <!-- modal detail -->
      <div id="myModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Detail Akun Siswa</h5>
            </div>
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
            $('#myModal').on('show.bs.modal', function (e) {
                var rowid = $(e.relatedTarget).data('id');
                //menggunakan fungsi ajax untuk pengambilan data
                $.ajax({
                    type : 'post',
                    url : '../siswa/detail_akun.php',
                    data :  'id_siswa='+ rowid,
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
