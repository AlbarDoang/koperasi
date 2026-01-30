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

    $date        = date('Y-m-d');

    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Profil</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row">
                <div class="col-lg-12 col-md-12">
                    <?php                 
                    // mengambil data berdasarkan id
                    $sql = $con->query("SELECT * FROM user WHERE id='$id'");
                    while($row = $sql->fetch_assoc()){
                    ?>
                    <form method="post" action="../user/" enctype="multipart/form-data" class="card">
                        <input type="hidden" name="id" class="form-control" value="<?php echo $row['id']; ?>" readonly>
                    <div class="card-body" >
                        <div class="form-group" align=center>
                        <img src="../../../assets/images/<?php echo $row['foto']; ?>" height="100">
                        </div>
                        <input type="hidden" name="foto" class="form-control" value="<?php echo $row['foto']; ?>" readonly>
                    <br>
                        <div class="form-group">
                        <label class="form-label"> Nama</label>
                        <input type="text" name="nama" class="form-control" value="<?php echo $row['nama']; ?>" required>
                        </div>
                    <br>     
                        <div class="row">
                        <div class="col-md-6 mt-6 mt-md-0">
                            <label class="form-label"> Username</label>
                            <div class="input-group">
                            <input type="text" name="username" class="form-control" value="<?php echo $row['username']; ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6 mt-6 mt-md-0">
                            <label class="form-label"> Password</label>
                            <div class="input-group">
                            <input type="text" name="password2" class="form-control" value="<?php echo $row['password2']; ?>" required>
                            </div>
                        </div>
                        </div>
                    <br>
                        <div class="form-group">
                            <label class="form-label"> Cari Foto Petugas</label>
                            <input type="file" name="image2" class="form-control">
                        </div>
                        <small><font color="red">* Ukuran Maksimal 2MB dimensi 5 cm x 5 cm </font></small>
                    <br>
                    <br>
                        <div class="form-group">
                        <label class="form-label"> Ganti Foto ?</label>
                        <div class="col-md-12">
                        <label>
                          <input type="radio" class="control-input" name="example-radios" value="iya">
                          <span class="custom-control-label">Ya Ganti Foto </span><br>
                        </label>
                        <br>
                        <label>
                          <input type="radio" class="control-input" name="example-radios" value="tidak" checked>
                          <span class="custom-control-label"> Tidak Ganti Foto</span>
                        </label>
                        </div>                     
                        </div>
                    <br>     

                    <div class="form-footer" align="center">
                        <input type="submit" name="tprofil" value="Simpan" class="btn btn-dark">
                   </div>
                    <br>
                    </form>
                    <?php } ?>
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
      
        <!-- Proses Edit Petugas -->
        <?php
        // Koneksi sudah ada dari head.php
        if(isset($_POST['tprofil'])){ //['ttambah'] merupakan name dari button di form tambah
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
                          message: "Berhasil Merubah Profil !"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../user/');
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
                        message: 'Gagal Merubah Profil !!!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../user/');
                    } ,1000);
                    });
                </script>
           <?php }
        }
        ?>
        <!-- END Edit Petugas -->

  </body>
</html>
