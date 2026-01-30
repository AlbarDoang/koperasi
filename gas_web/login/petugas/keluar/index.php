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
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Penarikan Tabungan</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header" align="center">
                                <a href="tambah">
                                <button type="button" class="btn btn-dark"><i class="fe fe-plus-square me-2"></i>Tambah</button>
                               </a>
                                
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cetaktoday">
                                <i class="fe fe-save me-2"></i> <span class="icon-name">Hari Ini</span></a>
                                </button>
                                
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#cekdetail">
                                <i class="fe fe-database me-2"></i> <span class="icon-name">Cek Semua</span></a>
                                </button>

                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportexcel5">
                                <i class="fe fe-calendar me-2"></i> <span class="icon-name">Tanggal</span></a>
                                </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="user_data" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">No Penarikan</th>
                                            <th class="text-center">ID Tabungan</th>
                                            <th class="text-center">Nama</th>
                                            <th class="text-center">Tanggal</th>
                                            <th class="text-center">Jumlah</th>
                                            <th width="10%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                        <?php
                                        include ".cek_hariini.php";
                                        include ".cek_detail.php";
                                        include ".cek_tanggal.php";
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

        <!-- Server Side -->
        <script type="text/javascript" language="javascript" >        
            var dataTable = $('#user_data').DataTable({
                "processing":true,
                "serverSide":true,
                "order":[],
                "ajax":{
                    url:"../../function/fetch_keluar_petugas.php",
                    type:"POST"
                },
                "columnDefs":[
                    {
                        "targets":[0, 6],
                        "orderable":false,
                    },
                ],

            });
        </script>
        <!-- END Server Side --> 
        
        <!-- Proses Tambah Transaksi -->
        <?php
        include('../../koneksi/config.php');
        if(isset($_POST['ttambah'])){ //['ttambah'] merupakan name dari button di form tambah
            $no_keluar           = $_POST['no'];
            $tanggal_keluar      = $_POST['tanggal'];
            $nama_pengguna         = $_POST['nama_siswa'];
            $id_tabungan        = $_POST['id_tabungan'];
            $kelas_pengguna        = $_POST['nama_kelas'];
            $jumlah             = $_POST['jumlah'];
            $username           = $_POST['user'];
            $kegiatan           = $_POST['kegiatan'];
            $keterangan         = $_POST['keterangan'];

            //cek ip
            if(!empty($_SERVER['HTTP_CLIENT_IP'])){
                  $ip=$_SERVER['HTTP_CLIENT_IP'];
                }
                elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
                  $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
                }
                else{
                  $ip=$_SERVER['REMOTE_ADDR'];
                }
            $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);
            
            $ipa = $ip;

            $query1          = mysqli_query($con,'select * from siswa where id_tabungan = "'.$id_tabungan.'"');
            $row            = mysqli_fetch_array($query1);
            $saldo           = $row['saldo'];
            $wa1           = $row['no_wa'];
            $wa2           = $row['no_ortu'];

            $hasil  = $row['saldo'] - $_POST['jumlah'];
            $tgl  = $_POST['tanggal'];
            $tgl_kirim  = tgl_indo($_POST['tanggal']);
            if ($hasil <= '0') { 
            echo "<script language='JavaScript'>
                    setTimeout(function () { 
                        $.growl.error({
                        title: 'Gagal',
                        message: 'Saldo Tidak Mencukupi!!!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../keluar/tambah');
                    } ,1000);
                    });
                </script>";  
            } else {
            //Update Saldo di Siswa
            $sql    = 'update siswa set transaksi_terakhir="'.$tgl.'", saldo="'.$hasil.'" where id_tabungan="'.$id_tabungan.'"';

            //Input Data Transaksi
            $sql1 = "INSERT INTO t_keluar VALUES(NULL, '$no_keluar','$nama_pengguna','$id_tabungan','$kelas_pengguna','$tanggal_keluar','$jumlah',CURRENT_TIMESTAMP(),'$keterangan')";
            $sql2 = "INSERT INTO transaksi VALUES(NULL, '','$no_keluar','$nama_pengguna','$id_tabungan','$kelas_pengguna','$keterangan','0','$jumlah','$tanggal_keluar','$username','$kegiatan','$ipa',CURRENT_TIMESTAMP())";

            $query2  = mysqli_query($con,$sql);
            $query3  = mysqli_query($con,$sql1);
            $query4  = mysqli_query($con,$sql2);
            }
            
            if ($query2) {

include '../../koneksi/wa.php';
$data = [
'api_key' => $api_key,
'sender' => $no_server,
'number' => $wa1,
'message' => "Hai Sobat Tabungan!
ID *".$id_tabungan."* Anda Telah Melakukan Transaksi Penarikan Tabungan Sebesar *Rp.".number_format($jumlah)."*. 
Dengan Nomor Transaksi *".$no_keluar."* Pada Tanggal *".$tgl_kirim."*.
Silahkan Cek Saldo Anda Di Aplikasi *Tabungan Siswa* atau ".$linkweb.""
];

require('../../classsendmessage.php');

$data = [
'api_key' => $api_key,
'sender' => $no_server,
'number' => $wa2,
'message' => "Hai Sobat Tabungan!
Nama : *".$nama_pengguna."* Anda Telah Melakukan Transaksi Penarikan Tabungan Sebesar *Rp.".number_format($jumlah)."*. 
Dengan Nomor Transaksi *".$no_keluar."* Pada Tanggal *".$tgl_kirim."*.
Sisa Saldo Tabungan Ada *Rp.".number_format($hasil)."*.
Silahkan Cek Saldo Anda Di Aplikasi *Tabungan Siswa* atau ".$linkweb.""
]; 
require('../../classsendmessage.php');

            //Jika Sukses
            ?>
                <script language="JavaScript">
                    setTimeout(function () { 
                        $.growl.notice({
                          title: "Sukses",
                          message: "Transaksi Penarikan Tabungan Berhasil Di Tambah !"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../keluar/');
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
                        message: 'Transaksi Gagal Di Tambah !!!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../keluar/tambah');
                    } ,1000);
                    });
                </script>";              
            }
        }
        ?>
        <!-- END Tambah Transaksi -->

      <!-- modal detail -->
      <div id="myModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Detail Penarikan Tabungan</h5>
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
                    url : 'detail_keluar.php',
                    data :  'no_keluar='+ rowid,
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
