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

    $id_pengguna = $_GET['id_siswa'];
    // mengambil data berdasarkan id
    $sql = $con->query("SELECT * FROM pengguna WHERE id = '$id_pengguna'");
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
              <h5>Detail Pengguna</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="card">

                <form method="post" action="../siswa/" enctype="multipart/form-data">
                    <input type="hidden" name="id_pengguna" class="form-control" value="<?php echo $id_pengguna; ?>" readonly>
                    <div class="card-body">
                            <div class="form-group">
                                <label class="form-label"> ID Tabungan</label>
                                <input type="text" name="id_tabungan" class="form-control" value="<?php echo $row['id_tabungan']; ?>" readonly>
                            </div>
                        <br>
                            <div class="form-group">
                                <label class="form-label"> Nama</label>
                                <input type="text" name="nama" class="form-control" value="<?php echo $row['nama']; ?>" require>
                            </div>
                        <br>               
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"> Jenis Kelamin</label>
                                    <div class="input-group">
                                        <select class="form-control form-select" data-placeholder="Choose one" name="jk" required>
                                            <option value="<?php echo $row['jk']; ?>"><?php echo $row['jk']; ?></option>
                                            <?php   
                                            $jk = $row['jk'];
                                            if ($jk=="Perempuan")
                                                echo "<option value='Laki-laki'>Laki-Laki</option>";
                                            else
                                                echo "<option value='Perempuan'>Perempuan</option>";  ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> Tempat Lahir</label>
                                    <div class="input-group">
                                        <input type="text" name="tempat_lahir" class="form-control" value="<?php echo $row['tempat_lahir']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> Tanggal Lahir</label>
                                    <div class="input-group">
                                        <input type="date" name="tanggal_lahir" class="form-control" value="<?php echo $row['tanggal_lahir']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        <br>
                            <div class="form-group">
                                <label class="form-label"> Alamat</label>
                                <input type="text" name="alamat" class="form-control" value="<?php echo $row['alamat']; ?>" required>              
                            </div>
                        <br>   
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label"> No Whatsapp</label>
                                    <div class="input-group">
                                        <input type="text" onkeypress="return hanyaAngka(event)" name="no_wa" class="form-control" value="<?php echo $row['no_wa']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"> Email</label>
                                    <div class="input-group">
                                        <input type="email" name="email" class="form-control" value="<?php echo $row['email']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        <br>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Tanda Pengenal</label>
                                    <div class="input-group">
                                        <select class="form-control form-select" data-placeholder="Choose one" name="tanda_pengenal" required>
                                            <option value="<?php echo $row['tanda_pengenal']; ?>"><?php echo $row['tanda_pengenal']; ?></option>
                                            <?php   
                                            $tanda_pengenal = $row['tanda_pengenal'];
                                            if ($tanda_pengenal=="Kartu Pelajar") {
                                                echo "<option value='Kartu Tanda Penduduk'>Kartu Tanda Penduduk</option>";
                                                echo "<option value='Surat Izin Mengemudi'>Surat Izin Mengemudi</option>";
                                            } else if ($tanda_pengenal=="Kartu Tanda Penduduk")  { 
                                                echo "<option value='Kartu Pelajar'>Kartu Pelajar</option>";  
                                                echo "<option value='Surat Izin Mengemudi'>Surat Izin Mengemudi</option>";  
                                            } else {
                                                echo "<option value='Kartu Pelajar'>Kartu Pelajar</option>";  
                                                echo "<option value='Kartu Tanda Penduduk'>'Kartu Tanda Penduduk</option>";  
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"> NIS/No Pengenal</label>
                                    <div class="input-group">
                                        <input type="text" name="no_pengenal" class="form-control" value="<?php echo $row['no_pengenal']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        <br>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Jurusan</label>
                                    <div class="input-group">                                    
                                        <select class="form-control select2-show-search form-select jurusan" name="id_jurusan" required>
                                            <option value="<?php echo $row['id_jurusan']; ?>"> 
                                            <?php
                                            $id_jurusan = $row['id_jurusan'];
                                            $query = "select * from jurusan where id_jur='$id_jurusan'";
                                            $hasil = mysqli_query($con,$query);
                                            while($data=mysqli_fetch_array($hasil)){ 
                                            echo "$data[nama_jurusan]";  
                                            } ?>
                                            </option>
                                            <?php
                                            $query = "select * from jurusan where not id_jur='$id_jurusan'";
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
                                            <option value="<?php echo $row['kelas']; ?>"> <?php echo $row['kelas']; ?> </option>
                                            <?php
                                            $kelas  = $row['kelas'];
                                            $dataex = explode(" " , $kelas);
                                            print_r($dataex);
                                            $queryx = "select * from kelas where id_jur='$id_jurusan' and tingkat='$dataex[0]' and not rombel='$dataex[2]'";
                                            $hasilx = mysqli_query($con,$queryx);
                                            while($datax=mysqli_fetch_array($hasilx)){
                                            echo "<option value='".$datax['tingkat']." ".$datax['singkatan']." ".$datax['rombel']."'>$datax[tingkat] $datax[singkatan] $datax[rombel]</option>";
                                            } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        <br>      
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label"> Nama Ayah</label>
                                    <div class="input-group">
                                        <input type="text" name="nama_ayah" class="form-control" value="<?php echo $row['nama_ayah']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> Nama Ibu</label>
                                    <div class="input-group">
                                        <input type="text" name="nama_ibu" class="form-control" value="<?php echo $row['nama_ibu']; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"> No Orang Tua</label>
                                    <div class="input-group">
                                        <input type="text" name="no_ortu" onkeypress="return hanyaAngka(event)" class="form-control" value="<?php echo $row['no_ortu']; ?>" required>
                                    </div>
                                </div>
                            </div>
                        <br>
                            <div class="form-group">
                                <label class="form-label"> Saldo</label>
                                <input type="text" name="saldo" class="form-control" value="<?php echo $row['saldo']; ?>" readonly>  
                                <small class="text-muted"><font color=red>* Hanya bisa diubah oleh Admin</font></small>            
                            </div>
                        <br>   
                    </div>

                  <div class="form-footer" align="center">
					<input type="submit" name="tedit" value="Simpan Perubahan" class="btn btn-dark">
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
