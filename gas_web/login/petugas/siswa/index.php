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
          <!-- [INFO] Container untuk header halaman - tidak diubah -->
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <!-- [INFO] Toolbar untuk menampilkan judul -->
            <div class="btn-toolbar mb-2 mb-md-0">
              <!-- Daftar Anggota Koperasi -->
              <h5>Daftar Anggota</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-12">
                    <!-- [INFO] Card untuk menampilkan tabel data -->
                    <div class="card">
                        <!-- [INFO] Header card dengan tombol aksi -->
                        <div class="card-header" align="center">
                                <!-- [INFO] Link ke halaman tambah siswa - nama file tetap karena aturan: jangan ubah nama file -->
                                <a href="tambah_siswa">
                                <!-- [EDIT] Ganti teks 'Tambah Siswa' → 'Tambah Anggota', ubah warna btn-dark ke #FF4C00 -->
                                <button type="button" class="btn btn-dark" style="background-color: #FF4C00; border-color: #FF4C00;"><i class="fe fe-plus-circle me-2"></i>Tambah Anggota</button>
                               </a>
                                <!-- [INFO] Link download Excel - path tetap karena aturan: jangan ubah path -->
                                <a href="../../admin/rekap_cetak/siswa" target="_BLANK">
                                <!-- [EDIT] Ganti teks 'Download Data Siswa' → 'Download Data Anggota' -->
                                <button type="button" class="btn btn-success"><i class="fe fe-save me-2"></i>Download Data Anggota</button>
                               </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="user_data" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">ID</th>
                                            <th class="text-center">Pengenal</th>
                                            <th class="text-center">Nama</th>
                                            <th class="text-center">L/P</th>
                                            <th class="text-center">No Hp</th>
                                            <th class="text-center">Kelas</th>
                                            <th width="5%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
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

        <!-- Server Side -->
        <script type="text/javascript" language="javascript" >        
            var dataTable = $('#user_data').DataTable({
                "processing":true,
                "serverSide":true,
                "order":[],
                "ajax":{
                    url:"../../function/fetch_siswa_petugas.php",
                    type:"POST"
                },
                "columnDefs":[
                    {
                        "targets":[0, 7],
                        "orderable":false,
                    },
                ],

            });
        </script>
        <!-- END Server Side --> 
        
        <!-- Proses Tambah Siswa -->
        <?php
        include('../../koneksi/config.php');
        if(isset($_POST['ttambah'])){ //['ttambah'] merupakan name dari button di form tambah
            $id_tabungan        = $_POST['id_tabungan'];
            $namasiswa          = $_POST['nama'];
            $jk                 = $_POST['jk'];
            $tempat_lahir       = $_POST['tempat_lahir'];
            $tanggal_lahir      = $_POST['tanggal_lahir'];
            $alamat             = $_POST['alamat'];
            $no_wa              = $_POST['no_wa'];
            $email              = $_POST['email'];
            $tanda_pengenal     = $_POST['tanda_pengenal'];
            $no_pengenal        = $_POST['no_pengenal'];
            $id_jurusan         = $_POST['id_jurusan'];
            $kelas              = $_POST['kelas'];
            $nama_ibu           = $_POST['nama_ibu'];
            $nama_ayah          = $_POST['nama_ayah'];
            $no_ortu            = $_POST['no_ortu'];
            $username           = $_POST['username'];
            $password           = $_POST['password'];
            $transaksi_terakhir = $_POST['transaksi_terakhir'];
            $password2          = sha1($_POST['password']);

            if($jk=="Laki-Laki"){
              $foto = "l.png";
            } else {
              $foto = "p.png";
            }

            //Input Data Siswa
            $sql2 = "INSERT INTO pengguna VALUES(NULL, '$id_tabungan','$namasiswa','$jk','$tempat_lahir','$tanggal_lahir','$alamat','$no_wa','$email','$tanda_pengenal','$no_pengenal','$id_jurusan','$kelas','$nama_ibu','$nama_ayah','$no_ortu','$foto','$hariini','0','$transaksi_terakhir','$username','$password2','$password','pengguna','$nama')";

            $query2  = mysqli_query($con,$sql2);
            
            if ($query2) {

include '../../koneksi/wa.php';
$data = [
'api_key' => $api_key,
'sender' => $no_server,
'number' => $no_wa,
'message' => "Hai *".$namasiswa."*,
Berikut Informasi Akun Tabungan Koperasi :

ID : *".$id_tabungan."* 
Tempat, Tanggal Lahir : *".$tempat_lahir.", ".tgl_indo($tanggal_lahir)."* 
Tanda Pengenal : *".$tanda_pengenal."*
No Pengenal : *".$no_pengenal."* 
Kelas : *".$kelas."* 
Username : *".$username."*
Password : *".$password."* _(Password Boleh di Ganti)_

Untuk Menggunakan Username dan Password Silakan Download Aplikasi Tabungan Koperasi atau Login Melalui Web Berikut ".$linkweb."siswalogin

Terima Kasih Sudah Mendaftar di Tabungan Koperasi."
];

require('../../classsendmessage.php');

            //Jika Sukses
            ?>
                <script language="JavaScript">
                    setTimeout(function () { 
                        $.growl.notice({
                          title: "Sukses",
                          message: "Anggota Berhasil Ditambahkan!"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../siswa/');
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
                        message: 'Anggota Gagal Ditambahkan!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../siswa/tambah_siswa');
                    } ,1000);
                    });
                </script>";              
            }
        }
        ?>
        <!-- END Tambah Siswa -->

        <!-- Proses Edit Siswa -->
        <?php
        // Koneksi sudah ada dari head.php
        if(isset($_POST['tedit'])){
            $id_pengguna           = $_POST['id_siswa'];
            $namasiswa          = $_POST['nama'];
            $jk                 = $_POST['jk'];
            $tempat_lahir       = $_POST['tempat_lahir'];
            $tanggal_lahir      = $_POST['tanggal_lahir'];
            $alamat             = $_POST['alamat'];
            $no_wa              = $_POST['no_wa'];
            $email              = $_POST['email'];
            $tanda_pengenal     = $_POST['tanda_pengenal'];
            $no_pengenal        = $_POST['no_pengenal'];
            $id_jurusan         = $_POST['id_jurusan'];
            $kelas              = $_POST['kelas'];
            $nama_ibu           = $_POST['nama_ibu'];
            $nama_ayah          = $_POST['nama_ayah'];
            $no_ortu            = $_POST['no_ortu'];
            $saldo              = $_POST['saldo'];
            
            $sql    = 'update siswa set nama="'.$namasiswa.'", jk="'.$jk.'", tempat_lahir="'.$tempat_lahir.'", tanggal_lahir="'.$tanggal_lahir.'", alamat="'.$alamat.'", no_wa="'.$no_wa.'", email="'.$email.'", tanda_pengenal="'.$tanda_pengenal.'", no_pengenal="'.$no_pengenal.'", id_jurusan="'.$id_jurusan.'", kelas="'.$kelas.'", nama_ibu="'.$nama_ibu.'", nama_ayah="'.$nama_ayah.'", no_ortu="'.$no_ortu.'", saldo="'.$saldo.'" where id="'.$id_pengguna.'"';
            $query  = mysqli_query($con,$sql);
            
            if ($query) {
            //Jika Sukses
            ?>
                <script language="JavaScript">
                    setTimeout(function () { 
                        $.growl.notice({
                          title: "Sukses",
                          message: "Data Anggota Berhasil Diubah!"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../siswa/');
                    } ,1000);
                    });
                </script>
            <?php
            }
            else {
            //Jika Gagal ?>
            <script language='JavaScript'>
                    setTimeout(function () { 
                        $.growl.error({
                        title: 'Gagal',
                        message: 'Data Anggota Gagal Diubah!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../siswa/edit_siswa?id_pengguna=<?php echo $id_pengguna; ?>');
                    } ,1000);
                    });
            </script>  
          <?php  }
        }
        ?>
        <!-- END Ubah Siswa -->

      <!-- modal detail -->
      <div id="myModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Detail Akun Anggota</h5>
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
                    url : 'detail_akun.php',
                    data :  'id_pengguna='+ rowid,
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
