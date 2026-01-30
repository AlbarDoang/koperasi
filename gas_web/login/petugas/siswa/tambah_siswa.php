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

    $query9      = "SELECT * FROM pengguna";
    $hasil9      = mysqli_query($con,$query9);
    $kodeBarang2 = mysqli_num_rows($hasil9) + 1;

    // Membuat ID Nasabah
    $acak       = date('Ymd');
    $acak2   = date('s');
    $acak3       = date('md');
    $newID      = $acak . $kodeBarang2;
    $newuser    = "user" . $acak3 . $kodeBarang2;

    $date        = date('Y-m-d');

    ?>


    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <!-- [INFO] Container untuk header halaman - tidak diubah -->
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <!-- [INFO] Toolbar untuk menampilkan judul -->
            <div class="btn-toolbar mb-2 mb-md-0">
              <!-- Tambah Anggota Koperasi -->
              <h5>Tambah Anggota</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="card">

                <form method="post" action="../siswa/" enctype="multipart/form-data">
                    <div class="card-body">
                            <div class="form-group">
                                <label class="form-label"> ID Tabungan</label>
                                <input type="text" name="id_tabungan" class="form-control" value="<?php echo $newID; ?>" readonly>
                            </div>
                        <br>
                            <div class="form-group">
                                <label class="form-label"> Nama</label>
                                <input type="text" name="nama" class="form-control" require>
                            </div>
                        <br>               
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"> Jenis Kelamin</label>
                                    <div class="input-group">
                                        <select class="form-control form-select" data-placeholder="Choose one" name="jk" required>
                                            <option> -- Pilih Jenis Kelamin -- </option>
                                            <option value="Laki-Laki">Laki-Laki</option>
                                            <option value="Perempuan">Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> Tempat Lahir</label>
                                    <div class="input-group">
                                        <input type="text" name="tempat_lahir" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> Tanggal Lahir</label>
                                    <div class="input-group">
                                        <input type="date" name="tanggal_lahir" class="form-control"  required>
                                    </div>
                                </div>
                            </div>
                        <br>
                            <div class="form-group">
                                <label class="form-label"> Alamat</label>
                                <input type="text" name="alamat" class="form-control" required>              
                            </div>
                        <br>   
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label"> No Whatsapp</label>
                                    <div class="input-group">
                                        <input type="text" onkeypress="return hanyaAngka(event)" name="no_wa" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"> Email</label>
                                    <div class="input-group">
                                        <input type="email" name="email" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        <br>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Tanda Pengenal</label>
                                    <div class="input-group">
                                        <select class="form-control form-select" data-placeholder="Choose one" name="tanda_pengenal" required>
                                            <option > -- Pilih Tanda Pengenal -- </option>
                                            <option value='Kartu Pelajar'>Kartu Pelajar</option>
                                            <option value='Kartu Tanda Penduduk'>Kartu Tanda Penduduk</option>
                                            <option value='Surat Izin Mengemudi'>Surat Izin Mengemudi</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"> NIS/No Pengenal</label>
                                    <div class="input-group">
                                        <input type="text" name="no_pengenal" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        <br>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Jurusan</label>
                                    <div class="input-group">                                    
                                        <select class="form-control select2-show-search form-select jurusan" name="id_jurusan" required>
                                            <option > -- Pilih Jurusan -- </option>
                                            <?php
                                            $query = "select * from jurusan where not id_jur='1'";
                                            $hasil = mysqli_query($con,$query);
                                            while($data=mysqli_fetch_array($hasil)){
                                            echo "<option value='".$data['id_jur']."'>$data[nama_jurusan]</option>";
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"> Kelas</label>
                                    <div class="input-group">                 
                                        <select class="form-control select2-show-search form-select kelas" name="kelas" required>
                                            <option > -- Pilih Kelas -- </option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        <br>      
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"> Nama Ayah</label>
                                    <div class="input-group">
                                        <input type="text" name="nama_ayah" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> Nama Ibu</label>
                                    <div class="input-group">
                                        <input type="text" name="nama_ibu" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> No Orang Tua</label>
                                    <div class="input-group">
                                        <input type="text" name="no_ortu" onkeypress="return hanyaAngka(event)" class="form-control" required>
                                    </div>
                                </div>
                            </div>
                        <br>
                    </div>
                    <input type="hidden" name="username" class="form-control" value="<?php echo $newuser; ?>" required>   
                    <input type="hidden" name="password" class="form-control" value="123456" required> 
                    <input type="hidden" name="transaksi_terakhir" class="form-control" value="0000-00-00" required> 

                  <div class="form-footer" align="center">
					<input type="submit" name="ttambah" value="Simpan" class="btn btn-dark">
                    <input type="reset"  name="reset" value="Reset" class="btn btn-warning">
                    <a href="../siswa/">
                    <input value="Kembali" class="btn btn-danger" readonly="readonly"></a>
				  </div>
                  <br>
                </form>
                </div>
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

    <script type="text/javascript">
      $(function(){
     $('.jurusan').change(function(){
        $('.kelas').after('<span class="loading">Proses ....</span>');
     $('.kelas').load('../function/cari/carikelasku.php?jur=' + $(this).val(),function(responseTxt,statusTxt,xhr)
      {
        if(statusTxt=="success")
        $('.loading').remove();
      });
      return false;
        });
      });
    </script>

  </body>
</html>
