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
              <h5>Transaksi Tabungan</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header" align="center">
                                
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cetaktoday">
                                <i class="fe fe-save me-2"></i> <span class="icon-name">Hari Ini</span></a>
                                </button>
                                
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#cekdetail">
                                <i class="fe fe-database me-2"></i> <span class="icon-name">Cek Semua</span></a>
                                </button>

                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="user_data" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">ID</th>
                                            <th class="text-center">Nama</th>
                                            <th class="text-center">Kelas</th>
                                            <th class="text-center">Jumlah</th>
                                            <th class="text-center">Transaksi Terakhir</th>
                                            <th width="10%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                        <?php
                                        include "../rekap/.cek_hariini.php";
                                        include "../rekap/.cek_detail.php";
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
                    url:"../../function/fetch_transaksi_full.php",
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
        if (!function_exists('transaksi_sanitize_column')) {
          function transaksi_sanitize_column($name)
          {
            return (is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name)) ? $name : '';
          }

          function transaksi_column_exists(mysqli $conn, string $table, string $column): bool
          {
            $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            if (!$stmt) {
              return false;
            }
            $stmt->bind_param('s', $column);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;
            $stmt->close();
            return $exists;
          }

          function transaksi_first_column(mysqli $conn, string $table, array $candidates): ?string
          {
            foreach ($candidates as $candidate) {
              $candidate = transaksi_sanitize_column($candidate);
              if ($candidate && transaksi_column_exists($conn, $table, $candidate)) {
                return $candidate;
              }
            }
            return null;
          }

          function transaksi_fetch_pengguna_record(mysqli $conn, array $attempts): ?array
          {
            foreach ($attempts as $attempt) {
              $column = isset($attempt['column']) ? transaksi_sanitize_column($attempt['column']) : '';
              $value = isset($attempt['value']) ? trim((string)$attempt['value']) : '';
              if ($column === '' || $value === '') {
                continue;
              }
              if (!transaksi_column_exists($conn, 'pengguna', $column)) {
                continue;
              }
              $stmt = $conn->prepare("SELECT * FROM pengguna WHERE `$column` = ? LIMIT 1");
              if (!$stmt) {
                continue;
              }
              $stmt->bind_param('s', $value);
              $stmt->execute();
              $result = $stmt->get_result();
              if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return ['row' => $row, 'column' => $column, 'value' => $value];
              }
              $stmt->close();
            }
            return null;
          }

          function transaksi_pick_value(array $row, array $keys): string
          {
            foreach ($keys as $key) {
              if (!empty($row[$key])) {
                return $row[$key];
              }
            }
            return '';
          }
        }

        // Koneksi sudah ada dari head.php
        if(isset($_POST['tmasuk'])){ //['ttambah'] merupakan name dari button di form tambah
            $no_masuk           = $_POST['no'];
            $tanggal_masuk      = $_POST['tanggal'];
            $nama_pengguna         = $_POST['nama_siswa'];
            $id_tabungan        = $_POST['id_tabungan'];
            $kelas_pengguna        = $_POST['nama_kelas'];
          $jumlah             = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
            $username           = $_POST['user'];
            $kegiatan           = $_POST['kegiatan'];
          $penggunaKeyColumnPost = isset($_POST['pengguna_key_column']) ? $_POST['pengguna_key_column'] : '';
          $penggunaKeyValuePost  = isset($_POST['pengguna_key_value']) ? $_POST['pengguna_key_value'] : '';

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
                  $lookup = transaksi_fetch_pengguna_record($koneksi, [
                    ['column' => $penggunaKeyColumnPost, 'value' => $penggunaKeyValuePost],
                    ['column' => 'id_tabungan', 'value' => $id_tabungan],
                    ['column' => 'nis', 'value' => $id_tabungan],
                    ['column' => 'no_pengenal', 'value' => $id_tabungan],
                    ['column' => 'username', 'value' => $id_tabungan],
                  ]);

                  if (!$lookup) {
                    echo "<script language='JavaScript'>
                        setTimeout(function () {
                          $.growl.error({
                            title: 'Gagal',
                            message: 'Data pengguna tidak ditemukan.'
                          });
                        });
                      </script>";
                    goto transaksi_masuk_end;
                  }

                  $row = $lookup['row'];
                  $effectiveColumn = $lookup['column'];
                  $effectiveValue  = $lookup['value'];

                  $saldoField = transaksi_first_column($koneksi, 'pengguna', ['saldo', 'saldo_tabungan', 'saldo_akhir']);
                  $saldoCurrent = $saldoField ? (float)$row[$saldoField] : 0;
                  $wa1 = transaksi_pick_value($row, ['no_wa', 'no_hp', 'telepon']);
                  $wa2 = transaksi_pick_value($row, ['no_ortu', 'no_hp_ortu', 'kontak_ortu', 'kontak_darurat']);

                  $hasil  = $saldoCurrent + $jumlah;
                  $query2 = $query3 = $query4 = false;
            $tgl  = $_POST['tanggal'];
            $tgl_kirim  = tgl_indo($_POST['tanggal']);
            
                  include_once __DIR__ . '/../../function/ledger_helpers.php';
                  $user_id_numeric = intval($row['id']);
                  $ok = insert_ledger_masuk($con, $user_id_numeric, floatval($jumlah), 'Setoran Petugas (no: ' . $no_masuk . ')', 1, null);
                  $updateParts = [];
                  $tglEsc = mysqli_real_escape_string($con, $tgl);
                  $hasilEsc = mysqli_real_escape_string($con, (string)$hasil);
                  if (transaksi_column_exists($koneksi, 'pengguna', 'transaksi_terakhir')) {
                    $updateParts[] = "`transaksi_terakhir`='" . $tglEsc . "'";
                  }
                  if ($saldoField) {
                    $updateParts[] = "`" . $saldoField . "`='" . $hasilEsc . "'";
                  }

                  $effectiveValueEsc = mysqli_real_escape_string($con, $effectiveValue);
                  $sql = '';
                  if (!empty($updateParts)) {
                    // If helper succeeded, only update non-saldo fields (transaksi_terakhir), otherwise keep original behaviour
                    $sql = 'UPDATE pengguna SET ' . implode(', ', $updateParts) . " WHERE `" . $effectiveColumn . "`='" . $effectiveValueEsc . "'";
                  }

            //Input Data Transaksi
            $sql1 = "INSERT INTO t_masuk VALUES(NULL, '$no_masuk','$nama_pengguna','$id_tabungan','$kelas_pengguna','$tanggal_masuk','$jumlah',CURRENT_TIMESTAMP(),'$kegiatan')";
            $sql2 = "INSERT INTO transaksi VALUES(NULL, '$no_masuk','','$nama_pengguna','$id_tabungan','$kelas_pengguna','$kegiatan','$jumlah','0','$tanggal_masuk','$nama','$kegiatan','$ipa',CURRENT_TIMESTAMP())";

                  $query2  = empty($sql) ? true : mysqli_query($con,$sql);
                $query3  = mysqli_query($con,$sql1);
                $query4  = mysqli_query($con,$sql2);
            
                  if ($query2 && $query3 && $query4) {

include '../../koneksi/wa.php';
            if (!empty($wa1)) {
            $data = [
            'api_key' => $api_key,
            'sender' => $no_server,
            'number' => $wa1,
            'message' => "Hai Sobat Tabungan!
            ID *".$id_tabungan."* Anda Telah Melakukan Transaksi Tabungan Masuk Sebesar *Rp.".number_format($jumlah)."*. 
            Dengan Nomor Transaksi *".$no_masuk."* Pada Tanggal *".$tgl_kirim."*.
            Silahkan Cek Saldo Anda Di Aplikasi *Tabungan Koperasi* atau ".$linkweb.""
            ];

            require('../../classsendmessage.php');
            }

            if (!empty($wa2)) {
            $data = [
            'api_key' => $api_key,
            'sender' => $no_server,
            'number' => $wa2,
            'message' => "Hai Sobat Tabungan!
            Nama : *".$nama_pengguna."* Anda Telah Melakukan Transaksi Tabungan Masuk Sebesar *Rp.".number_format($jumlah)."*. 
            Dengan Nomor Transaksi *".$no_masuk."* Pada Tanggal *".$tgl_kirim."*.
            Total Saldo Tabungan Ada *Rp.".number_format($hasil)."*.
            Silahkan Cek Saldo Anda Di Aplikasi *Tabungan Koperasi* atau ".$linkweb.""
            ]; 
            require('../../classsendmessage.php');
            }

            //Jika Sukses
            ?>
                <script language="JavaScript">
                    setTimeout(function () { 
                        $.growl.notice({
                          title: "Sukses",
                          message: "Transaksi Tabungan Masuk Berhasil Di Tambah !"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../transaksi/');
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
                    window.location.replace('../transaksi/');
                    } ,1000);
                    });
                </script>";              
            }
        }
      transaksi_masuk_end:
        ?>
        <!-- END Tambah Transaksi -->

        <!-- Proses Tambah Transaksi -->
        <?php
        // Koneksi sudah ada dari head.php
        if(isset($_POST['tpenarikan'])){ //['ttambah'] merupakan name dari button di form tambah
            $no_keluar           = $_POST['no'];
            $tanggal_keluar      = $_POST['tanggal'];
            $nama_pengguna         = $_POST['nama_siswa'];
            $id_tabungan        = $_POST['id_tabungan'];
            $kelas_pengguna        = $_POST['nama_kelas'];
            $jumlah             = isset($_POST['jumlah']) ? floatval($_POST['jumlah']) : 0;
            $username           = $_POST['user'];
            $kegiatan           = $_POST['kegiatan'];
            $keterangan         = $_POST['keterangan'];
            $penggunaKeyColumnPost = isset($_POST['pengguna_key_column']) ? $_POST['pengguna_key_column'] : '';
            $penggunaKeyValuePost  = isset($_POST['pengguna_key_value']) ? $_POST['pengguna_key_value'] : '';

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
                  $lookup = transaksi_fetch_pengguna_record($koneksi, [
                    ['column' => $penggunaKeyColumnPost, 'value' => $penggunaKeyValuePost],
                    ['column' => 'id_tabungan', 'value' => $id_tabungan],
                    ['column' => 'nis', 'value' => $id_tabungan],
                    ['column' => 'no_pengenal', 'value' => $id_tabungan],
                    ['column' => 'username', 'value' => $id_tabungan],
                  ]);

                  if (!$lookup) {
                    echo "<script language='JavaScript'>
                        setTimeout(function () {
                          $.growl.error({
                            title: 'Gagal',
                            message: 'Data pengguna tidak ditemukan.'
                          });
                        });
                      </script>";
                    goto transaksi_keluar_end;
                  }

                  $row = $lookup['row'];
                  $effectiveColumn = $lookup['column'];
                  $effectiveValue  = $lookup['value'];

                  $saldoField = transaksi_first_column($koneksi, 'pengguna', ['saldo', 'saldo_tabungan', 'saldo_akhir']);
                  $saldoCurrent = $saldoField ? (float)$row[$saldoField] : 0;
                  $wa1 = transaksi_pick_value($row, ['no_wa', 'no_hp', 'telepon']);
                  $wa2 = transaksi_pick_value($row, ['no_ortu', 'no_hp_ortu', 'kontak_ortu', 'kontak_darurat']);

                  $hasil  = $saldoCurrent - $jumlah;
                  $query2 = $query3 = $query4 = false;
            $tgl  = $_POST['tanggal'];
            $tgl_kirim  = tgl_indo($_POST['tanggal']);
                  if ($hasil < 0) { 
            echo "<script language='JavaScript'>
                    setTimeout(function () { 
                        $.growl.error({
                        title: 'Gagal',
                        message: 'Saldo Tidak Mencukupi!!!'
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../transaksi/');
                    } ,1000);
                    });
                </script>";  
            } else {
                  $updateParts = [];
                  $tglEsc = mysqli_real_escape_string($con, $tgl);
                  $hasilEsc = mysqli_real_escape_string($con, (string)$hasil);
                  if (transaksi_column_exists($koneksi, 'pengguna', 'transaksi_terakhir')) {
                    $updateParts[] = "`transaksi_terakhir`='" . $tglEsc . "'";
                  }
                  if ($saldoField) {
                    $updateParts[] = "`" . $saldoField . "`='" . $hasilEsc . "'";
                  }
                  $effectiveValueEsc = mysqli_real_escape_string($con, $effectiveValue);
            
            //Update Saldo di Siswa
                  $sql = '';
                  if (!empty($updateParts)) {
                    $sql = 'UPDATE pengguna SET ' . implode(', ', $updateParts) . " WHERE `" . $effectiveColumn . "`='" . $effectiveValueEsc . "'";
                  }

            //Input Data Transaksi
            $sql1 = "INSERT INTO t_keluar VALUES(NULL, '$no_keluar','$nama_pengguna','$id_tabungan','$kelas_pengguna','$tanggal_keluar','$jumlah',CURRENT_TIMESTAMP(),'$keterangan')";
            $sql2 = "INSERT INTO transaksi VALUES(NULL, '','$no_keluar','$nama_pengguna','$id_tabungan','$kelas_pengguna','$keterangan','0','$jumlah','$tanggal_keluar','$nama','$kegiatan','$ipa',CURRENT_TIMESTAMP())";

                  $query2  = empty($sql) ? true : mysqli_query($con,$sql);
            $query3  = mysqli_query($con,$sql1);
            $query4  = mysqli_query($con,$sql2);
            }
                  if ($query2 && $query3 && $query4) {

include '../../koneksi/wa.php';
            if (!empty($wa1)) {
            $data = [
            'api_key' => $api_key,
            'sender' => $no_server,
            'number' => $wa1,
            'message' => "Hai Sobat Tabungan!
            ID *".$id_tabungan."* Anda Telah Melakukan Transaksi Penarikan Tabungan Sebesar *Rp.".number_format($jumlah)."*. 
            Dengan Nomor Transaksi *".$no_keluar."* Pada Tanggal *".$tgl_kirim."*.
            Silahkan Cek Saldo Anda Di Aplikasi *Tabungan Koperasi* atau ".$linkweb.""
            ];

            require('../../classsendmessage.php');
            }

            if (!empty($wa2)) {
            $data = [
            'api_key' => $api_key,
            'sender' => $no_server,
            'number' => $wa2,
            'message' => "Hai Sobat Tabungan!
            Nama : *".$nama_pengguna."* Anda Telah Melakukan Transaksi Penarikan Tabungan Sebesar *Rp.".number_format($jumlah)."*. 
            Dengan Nomor Transaksi *".$no_keluar."* Pada Tanggal *".$tgl_kirim."*.
            Sisa Saldo Tabungan Ada *Rp.".number_format($hasil)."*.
            Silahkan Cek Saldo Anda Di Aplikasi *Tabungan Koperasi* atau ".$linkweb.""
            ]; 
            require('../../classsendmessage.php');
            }

            //Jika Sukses
            ?>
                <script language="JavaScript">
                    setTimeout(function () { 
                        $.growl.notice({
                          title: "Sukses",
                          message: "Transaksi Penarikan Tabungan Berhasil Di Tambah !"
                        });   
                    window.setTimeout(function(){ 
                    window.location.replace('../transaksi/');
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
                    window.location.replace('../transaksi/');
                    } ,1000);
                    });
                </script>";              
            }
        }
      transaksi_keluar_end:
        ?>
        <!-- END Tambah Transaksi -->

      <!-- modal detail -->
      <div id="myMasuk" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Tambah Tabungan Masuk</h5>
            </div>
            <!--Modal body-->
            <div class="modal-body">
              <div class="fetched-data"></div>
            </div>
            <!--Modal footer-->
          </div>
        </div>
      </div>
      <!-- END modal detail -->

      <!-- Data Modal-->
      <script type="text/javascript">
        $(document).ready(function(){
            $('#myMasuk').on('show.bs.modal', function (e) {
                var rowid = $(e.relatedTarget).data('id');
                //menggunakan fungsi ajax untuk pengambilan data
                $.ajax({
                    type : 'post',
                    url : 'tambah_masuk.php',
                    data :  'id_pengguna='+ rowid,
                    success : function(data){
                    $('.fetched-data').html(data);//menampilkan data ke dalam modal
                    }
                });
            });
        });
      </script>
      <!-- END Data Modal-->

      <!-- modal detail -->
      <div id="myTarik" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Tambah Penarikan Tabungan</h5>
            </div>
            <!--Modal body-->
            <div class="modal-body">
              <div class="fetched-data"></div>
            </div>
            <!--Modal footer-->
          </div>
        </div>
      </div>
      <!-- END modal detail -->

      <!-- Data Modal-->
      <script type="text/javascript">
        $(document).ready(function(){
            $('#myTarik').on('show.bs.modal', function (e) {
                var rowid = $(e.relatedTarget).data('id');
                //menggunakan fungsi ajax untuk pengambilan data
                $.ajax({
                    type : 'post',
                    url : 'tambah_keluar.php',
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
