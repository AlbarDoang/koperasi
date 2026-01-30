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
              <h5>Data Petugas</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header" align="center">
                            <a href="tambah_petugas">
                            <button type="button" class="btn btn-dark"><i class="fe fe-plus-circle me-2"></i>Tambah Petugas</button>
                            </a>
                            <a href="../rekap_cetak/petugas" target="_BLANK">
                            <button type="button" class="btn btn-success"><i class="fe fe-save me-2"></i>Download Daftar Petugas</button>
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="example" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">Nama</th>
                                            <th class="text-center">Username</th>
                                            <th class="text-center">Password</th>
                                            <th class="text-center">Last Login</th>
                                            <th width="5%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                            <?php
                                            $no = 1;
                                            $query11 = "SELECT * FROM user WHERE NOT hak_akses='admin' ORDER BY id DESC";
                                            $sql11 = mysqli_query($con, $query11); 
                                            while($data = mysqli_fetch_array($sql11)){
                                            ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++ ?>
                                            <td class="text-center"><?php echo $data['nama']; ?></td>
                                            <td class="text-center"><?php echo $data['username']; ?></td>
                                            <td class="text-center"><?php echo $data['password2']; ?></td>
                                            <td class="text-center"><?php echo indonesian_date_full($data['last_login']); ?></td>
                                            <td class="text-center">
                                                <a href="detail_petugas.php?id=<?php echo $data['id']; ?>"><button type="button" class="btn btn-dark btn-sm btn-icon" title="Edit Petugas"><i class="fe fe-edit"></i></button></a>

                                                <a href="#mdDelete" id="custId" data-bs-toggle="modal" data-id="<?php echo $data['id']; ?>">
                                                <button type="button" class="btn btn-danger btn-sm btn-icon" title="Hapus Petugas"><i class="fe fe-trash-2"></i></button></a>
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
      
        <!-- Proses Tambah Petugas -->
        <?php
        include('../../koneksi/config.php');
        if(isset($_POST['ttambahp'])){ //['ttambah'] merupakan name dari button di form tambah
            $nama_petugas   = $_POST['nama'];
            $username       = $_POST['username'];
            $password       = sha1($_POST['password2']);
            $password2      = $_POST['password2'];
            $hak_akses      = $_POST['akses'];
                
            //Input Data Siswa
            $sql2 = "INSERT INTO user VALUES(NULL, '$nama_petugas','$username','$password','$password2','$hak_akses','petugas.png',CURRENT_TIMESTAMP())";

            $query2  = mysqli_query($con,$sql2);

            if ($query2) {
            //Jika Sukses
            ?>
                <script language="JavaScript">
                    setTimeout(function () { 
                        $.growl.notice({
                          title: "Berhasil",
                          message: "Berhasil Tambah Petugas !"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../user/petugas');
                    } ,1000);
                    });
                </script>
            <?php
            }
            else { 
            //Jika Gagal
            ?>
            <script language='JavaScript'>
                    setTimeout(function () { 
                        $.growl.error({
                        title: 'Gagal',
                        message: 'Gagal Tambah Petugas !!!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../user/petugas/tambah_petugas');
                    } ,1000);
                    });
                </script>
           <?php }
        }
        ?>
        <!-- END Tambah Petugas -->
      
        <!-- Proses Edit Petugas -->
        <?php
        include('../../koneksi/config.php');
        if(isset($_POST['teditp'])){ //['ttambah'] merupakan name dari button di form tambah
            $id_petugas     = $_POST['id'];
            $nama_petugas   = $_POST['nama'];
            $username       = $_POST['username'];
            $password       = sha1($_POST['password2']);
            $password2      = $_POST['password2'];
                
            $pilihan = $_POST['example-radios'];

            if ($pilihan=="iya") {
                $errors= array();
                $file_name = $_FILES['image2']['name'];
                $file_size = $_FILES['image2']['size'];
                $file_tmp = $_FILES['image2']['tmp_name'];
                $file_type = $_FILES['image2']['type'];
                $file_ext=strtolower(end(explode('.',$_FILES['image2']['name'])));

                echo $fotobaru = $file_name;
                
                $extensions= array("jpeg","jpg","png");
            
                if(in_array($file_ext,$extensions)=== false){
                    $errors[]="extension not allowed, please choose a JPEG or PNG file.";
                }
                
                if($file_size > 2097152) {
                    $errors[]='File size must be excately 2 MB';
                }
            } else {

                echo $fotobaru = $_POST['foto'];
                $errors   = "";

            }
            //Update Saldo di Siswa
            $sql    = 'update user set nama="'.$nama_petugas.'", username="'.$username.'", password="'.$password.'", password2="'.$password2.'", foto="'.$fotobaru.'" where id="'.$id_petugas.'"';
            
            if (empty($errors)==true) {
            $query  = mysqli_query($con,$sql);
            //Jika Sukses
            move_uploaded_file($file_tmp,"../../../assets/images/".$fotobaru);
            ?>
                <script language="JavaScript">
                    setTimeout(function () { 
                        $.growl.notice({
                          title: "Berhasil",
                          message: "Berhasil Merubah Petugas !"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../user/petugas');
                    } ,1000);
                    });
                </script>
            <?php
            }
            else { 
            //Jika Gagal
            ?>
            <script language='JavaScript'>
                    setTimeout(function () { 
                        $.growl.error({
                        title: 'Gagal',
                        message: 'Gagal Merubah Petugas !!!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../user/detail_petugas?id=<?php echo $id_petugas ?>');
                    } ,1000);
                    });
                </script>
           <?php }
        }
        ?>
        <!-- END Edit Petugas -->

      <!-- modal detail -->
      <div id="mdDelete" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Hapus Petugas</h5>
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
                    url : 'hapus_petugas.php',
                    data :  'id='+ rowid,
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
