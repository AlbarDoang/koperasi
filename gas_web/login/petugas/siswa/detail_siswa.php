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

    $id_siswa = $_GET['id_siswa'];
    // mengambil data berdasarkan id
    $sql = $con->query("SELECT * FROM siswa WHERE id = '$id_siswa'");
    while($row = $sql->fetch_assoc()){

    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Detail Anggota</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="card">
                    <div class="card-header" align="center">
                        <div class="form-footer" align="center">
                        <a href="../siswa/edit_siswa?id_siswa=<?php echo $id_siswa; ?>">
                        <input value="Edit Anggota" class="btn btn-dark" readonly="readonly"></a>
                        <a href="../siswa/">
                        <input value="Kembali" class="btn btn-danger" readonly="readonly"></a>
                        </div>
                    </div>
                    <form method="post" action="../siswa/" enctype="multipart/form-data">
                    <input type="hidden" name="id_siswa" class="form-control" value="<?php echo $id_siswa; ?>" readonly>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label"> ID Tabungan</label>
                            <input type="text" name="id_tabungan" class="form-control" value="<?php echo $row['id_tabungan']; ?>" readonly>
                        </div>
                    <br>
                        <div class="form-group">
                            <label class="form-label"> Nama</label>
                            <input type="text" name="nama" class="form-control" value="<?php echo $row['nama']; ?>" readonly>
                        </div>
                    <br>               
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label"> Jenis Kelamin</label>
                                <div class="input-group">
                                    <input type="text" name="jk" class="form-control" value="<?php echo $row['jk']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"> Tempat Lahir</label>
                                <div class="input-group">
                                    <input type="text" name="tempat_lahir" class="form-control" value="<?php echo $row['tempat_lahir']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"> Tanggal Lahir</label>
                                <div class="input-group">
                                    <input type="text" name="tanggal_lahir" class="form-control" value="<?php echo tgl_indo($row['tanggal_lahir']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    <br>
                        <div class="form-group">
                            <label class="form-label"> Alamat</label>
                            <input type="text" name="alamat" class="form-control" value="<?php echo $row['alamat']; ?>" readonly>              
                        </div>
                    <br>   
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label"> No Whatsapp</label>
                                <div class="input-group">
                                    <input type="text" name="no_wa" class="form-control" value="<?php echo $row['no_wa']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"> Email</label>
                                <div class="input-group">
                                    <input type="text" name="email" class="form-control" value="<?php echo $row['email']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                    <br>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Tanda Pengenal</label>
                                <div class="input-group">
                                    <input type="text" name="tanda_pengenal" class="form-control" value="<?php echo $row['tanda_pengenal']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"> NIS/No Pengenal</label>
                                <div class="input-group">
                                    <input type="text" name="no_pengenal" class="form-control" value="<?php echo $row['no_pengenal']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                    <br>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Jurusan</label>
                                <div class="input-group">
                                    <input type="text" name="id_jurusan" class="form-control" value="<?php 
                                    $id_jurusan = $row['id_jurusan'];
                                    $sql1 = $con->query("SELECT * FROM jurusan WHERE id_jur = '$id_jurusan'");
                                    while($row1 = $sql1->fetch_assoc()){
                                    echo $row1['nama_jurusan']; 
                                    }
                                    ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"> Kelas</label>
                                <div class="input-group">
                                    <input type="text" name="kelas" class="form-control" value="<?php echo $row['kelas']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                    <br>      
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label"> Nama Ayah</label>
                                <div class="input-group">
                                    <input type="text" name="nama_ayah" class="form-control" value="<?php echo $row['nama_ayah']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"> Nama Ibu</label>
                                <div class="input-group">
                                    <input type="text" name="nama_ibu" class="form-control" value="<?php echo $row['nama_ibu']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"> No Orang Tua</label>
                                <div class="input-group">
                                    <input type="text" name="no_ortu" class="form-control" value="<?php echo $row['no_ortu']; ?>" readonly>
                                </div>
                            </div>
                        </div>
                    
                    </div>
                    </form>
            </div>
        </div>
            <!-- End Row -->


        </main>


      </div>
    </div>

    <?php } ?>

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

  </body>
</html>
