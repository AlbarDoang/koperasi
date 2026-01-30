<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

  <?php include "../dashboard/head.php"; ?>

  <body>
    
    <?php include "../dashboard/icon.php"; ?>
    
    <?php include "../dashboard/header.php"; ?>

    <?php       
    // Koneksi sudah ada dari head.php
    //fungsi tanggal
    include "../../koneksi/fungsi_indotgl.php";
    //fungsi tanggal
    include "../../koneksi/fungsi_waktu.php";

    // admin-only
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['akses']) || $_SESSION['akses'] !== 'admin') { http_response_code(403); echo "<div class='container mt-4'><div class='alert alert-danger'>Akses ditolak.</div></div>"; exit; }

    //hari ini
    $hariini = date('Y-m-d');

    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Pengaturan Jurusan & Kelas</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header" align="center">
                            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#tambahjurusan">
                            <i class="fe fe-plus me-2"></i> <span class="icon-name">Tambah</span></a>
                            </button>

                            <a href="../rekap_cetak/jurusan" target="_BLANK">
                            <button type="button" class="btn btn-success"><i class="fe fe-save me-2"></i>Download Jurusan</button>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="example" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">Jurusan</th>
                                            <th class="text-center">Unit</th>
                                            <th width="10%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                            <?php
                                            $no = 1;
                                            $query11 = "SELECT * FROM jurusan ORDER BY id_jur DESC";
                                            $sql11 = mysqli_query($con, $query11); 
                                            while($data = mysqli_fetch_array($sql11)){
                                            ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++ ?></td>
                                            <td class="text-left"><?php echo $data['id_jur'] . ' | ' . $data['nama_jurusan'] . ' | ' . $data['singakatan']  ?></td>
                                            <td class="text-center"><?php echo $data['unit']; ?></td>
                                            <td class="text-center">

                                                <a href="../pengaturan/detail_jurusan?id_jur=<?php echo $data['id_jur']; ?>">
                                                <button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Jurusan"><i class="fe fe-alert-octagon icon-lg"></i></button>
                                                </a>

                                                <a href="#mdDelete" id="custId" data-bs-toggle="modal" data-id="<?php echo $data['id_jur']; ?>">
                                                <button type="button" class="btn btn-danger btn-sm btn-icon" title="Hapus Jurusan"><i class="fe fe-trash-2"></i></button></a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                        <?php 
                                        include ".tambah_jurusan.php";
                                        ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header" align="center">
                            <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#tambahkelas">
                            <i class="fe fe-plus me-2"></i> <span class="icon-name">Tambah</span></a>
                            </button>

                            <a href="../rekap_cetak/kelas" target="_BLANK">
                            <button type="button" class="btn btn-success"><i class="fe fe-save me-2"></i>Download Kelas</button>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="example2" border="1">
                                    <thead>
                                        <tr>
                                            <th width="5%" class="text-center">No</th>
                                            <th class="text-center">Kelas</th>
                                            <th width="10%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                            <?php
                                            $no = 1;
                                            $query11 = "SELECT * FROM kelas ORDER BY id_kel DESC";
                                            $sql11 = mysqli_query($con, $query11); 
                                            while($data = mysqli_fetch_array($sql11)){
                                            ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++ ?></td>
                                            <td class="text-center"><?php echo $data['tingkat'] . ' ' . $data['singkatan'] . ' ' . $data['rombel']  ?></td></td>
                                            <td class="text-center">

                                                <a href="../pengaturan/detail_kelas?id_kel=<?php echo $data['id_kel']; ?>">
                                                <button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Kelas"><i class="fe fe-alert-octagon icon-lg"></i></button>
                                                </a>

                                                <a href="#mdDelete2" id="custId" data-bs-toggle="modal" data-id="<?php echo $data['id_kel']; ?>">
                                                <button type="button" class="btn btn-danger btn-sm btn-icon" title="Hapus Kelas"><i class="fe fe-trash-2"></i></button></a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                        <?php 
                                        include ".tambah_kelas.php";
                                        ?>
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

    <?php include "../dashboard/js.php"; ?>

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
                $('#example').DataTable({
                "lengthMenu": [[10, 100, -1], [10, 100, "All"]],
                "scrollX":true,
                });
            });
        </script>
      <!-- END Data Modal-->

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

      <!-- Proses Tambah Transaksi -->
      <?php
      include('../../koneksi/config.php');
      if(isset($_POST['tjurusan'])){ //['ttambah'] merupakan name dari button di form tambah
          $nama           = $_POST['nama'];
          $singkatan      = $_POST['singkatan'];
          $unit           = $_POST['unit'];

          //Input Data Transaksi
          $sql = "INSERT INTO jurusan VALUES(NULL, '$nama','$singkatan','$unit')";

          $query2  = mysqli_query($con,$sql);
          
          if ($query2) {
          //Jika Sukses
          ?>
              <script language="JavaScript">
                  setTimeout(function () { 
                      $.growl.notice({
                        title: "Sukses",
                        message: "Jurusan Berhasil Di Tambah !"
                      });   
                  window.setTimeout(function(){ 
                  window.location.replace('../pengaturan/');
                  } ,1000);
                  });
              </script>
          <?php
          }
          else {
          //Jika Gagal
          echo "<script language='JavaScript'>
                  setTimeout(function () { 
                      $.growl.error({
                      title: 'Gagal',
                      message: 'Jurusan Gagal Di Tambah !!!'
                      });   
                  window.setTimeout(function(){ 
                  window.location.replace('../pengaturan/');
                  } ,1000);
                  });
              </script>";              
          }
      }
      ?>
      <!-- END Tambah Transaksi -->
      
      <!-- Proses Tambah Transaksi -->
      <?php
      include('../../koneksi/config.php');
      if(isset($_POST['tkelas'])){ //['ttambah'] merupakan name dari button di form tambah
          $tingkat        = $_POST['tingkat'];
          $singkatan      = $_POST['singkatan'];
          $rombel         = $_POST['rombel'];

            $query11 = "SELECT * FROM jurusan WHERE id_jur='$singkatan'";
            $sql11 = mysqli_query($con, $query11); 
            while($data = mysqli_fetch_array($sql11)){
            $id_jur = $data['singakatan'];
            
          //Input Data Transaksi
          $sql = "INSERT INTO kelas VALUES(NULL, '$tingkat','$id_jur','$rombel','$singkatan')";
            
            }

          $query2  = mysqli_query($con,$sql);          
          if ($query2) {
          //Jika Sukses
          ?>
              <script language="JavaScript">
                  setTimeout(function () { 
                      $.growl.notice({
                        title: "Sukses",
                        message: "Kelas Berhasil Di Tambah !"
                      });   
                  window.setTimeout(function(){ 
                  window.location.replace('../pengaturan/');
                  } ,1000);
                  });
              </script>
          <?php
          }
          else {
          //Jika Gagal
          echo "<script language='JavaScript'>
                  setTimeout(function () { 
                      $.growl.error({
                      title: 'Gagal',
                      message: 'Kelas Gagal Di Tambah !!!'
                      });   
                  window.setTimeout(function(){ 
                  window.location.replace('../pengaturan/');
                  } ,1000);
                  });
              </script>";              
          }
      }
      ?>
      <!-- END Tambah Transaksi -->

      <!-- modal detail -->
      <div id="mdDelete" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Hapus Jurusan</h5>
            </div>
            <!--Modal body-->
            <div class="modal-body">
              <div class="fetched-data"></div>
            </div>
          </div>
        </div>
      </div>
      <!-- END modal detail -->

      <!-- Data Modal-->
      <script type="text/javascript">
        $(document).ready(function(){
            $('#mdDelete').on('show.bs.modal', function (e) {
                var rowid = $(e.relatedTarget).data('id');
                //menggunakan fungsi ajax untuk pengambilan data
                $.ajax({
                    type : 'post',
                    url : 'hapus_jurusan.php',
                    data :  'id_jur='+ rowid,
                    success : function(data){
                    $('.fetched-data').html(data);//menampilkan data ke dalam modal
                    }
                });
            });
        });
      </script>
      <!-- END Data Modal-->

      <!-- modal detail -->
      <div id="mdDelete2" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Hapus Kelas</h5>
            </div>
            <!--Modal body-->
            <div class="modal-body">
              <div class="fetched-data"></div>
            </div>
          </div>
        </div>
      </div>
      <!-- END modal detail -->

      <!-- Data Modal-->
      <script type="text/javascript">
        $(document).ready(function(){
            $('#mdDelete2').on('show.bs.modal', function (e) {
                var rowid = $(e.relatedTarget).data('id');
                //menggunakan fungsi ajax untuk pengambilan data
                $.ajax({
                    type : 'post',
                    url : 'hapus_kelas.php',
                    data :  'id_kel='+ rowid,
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
