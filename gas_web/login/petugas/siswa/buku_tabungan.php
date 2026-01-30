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

    $id_pengguna = $_GET['id_siswa'];
    // mengambil data berdasarkan id
    $sql = $con->query("SELECT * FROM pengguna WHERE id = '$id_pengguna'");
    while($row = $sql->fetch_assoc()){
    $id_tabungan = $row['id_tabungan'];

    ?>

    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>


        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Buku Tabungan Siswa</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header" align="center">
                                <a href="../../../login/admin/function/cek/1tabungan_siswa.php?id_tabungan=<?php echo $id_tabungan ?>" target="_BLANK">
                                <button type="button" class="btn btn-dark"><i class="fe fe-printer me-2"></i>Cetak Tabungan</button>
                                </a>

                                <a href="../../../login/admin/rekap_cetak/tabungan_siswa.php?id_tabungan=<?php echo $id_tabungan ?>">
                                <button type="button" class="btn btn-success"><i class="fe fe-save me-2"></i>Download Tabungan</button>
                                </a>

                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportexcel5">
                                <i class="fe fe-calendar me-2"></i> <span class="icon-name">Tanggal Tabungan</span></a>
                                </button>

                                <a href="../siswa/">
                                <button type="button" class="btn btn-danger"><i class="fe fe-x-square me-2"></i>Kembali</button>
                                </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <div class="col-lg-12 table-responsive">
                                    <div class="text-center"><h2>Transaksi Buku Tabungan</h2></div>
                                    <table class="table invoice-summary">
                                        <thead>
                                            <tr class="bg-trans">
                                                <th width="15%"></th>
                                                <th width="10%"></th>
                                                <th width="30%"></th>
                                                <th width="20%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">ID Tabungan</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-left"><b><?php echo $row['id_tabungan']; ?></b></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Nama</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-left"><b><?php echo $row['nama']; ?></b></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Jenis Kelamin</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-left"><b><?php echo $row['jk']; ?></b></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Tanda Pengenal</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-left"><b><?php echo $row['tanda_pengenal']; ?></b></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">No Pengenal</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-left"><b><?php echo $row['no_pengenal']; ?></b></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Kelas</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-left"><b><?php echo $row['kelas']; ?></b></td>
                                                <td class="text-center"></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Total Saldo</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-left"><b>Rp <?php echo number_format($row['saldo']) ?> </b></td>
                                                <td class="text-center"></td>
                                            </tr>
                                        </tbody>
                                        <thead>
                                            <tr class="bg-trans">
                                                <th width="10%"></th>
                                                <th width="10%"></th>
                                                <th width="30%"></th>
                                                <th width="20%"></th>
                                            </tr>
                                        </thead>
                                    </table>
                                </div>

                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="example" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">No Transaksi</th>
                                            <th class="text-center">Tanggal</th>
                                            <th class="text-center">Keterangan</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Saldo</th>
                                            <th width="5%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                            <?php
                                            $no = 1;
                                            $query11 = "SELECT * FROM transaksi WHERE id_tabungan='$id_tabungan' ORDER BY id_transaksi DESC";
                                            $sql11 = mysqli_query($con, $query11); 
                                            while($data = mysqli_fetch_array($sql11)){
                                            ?>
                                        <tr>
                                            <td class="text-center"><?php echo $no++ ?></td>
                                            <td class="text-center"><?php 
                                             if (empty($data['no_masuk'])) {
                                                echo $data['no_keluar']; 
                                             } else {
                                                echo $data['no_masuk']; 
                                             }
                                            ?></td>
                                            <td class="text-center"><?php echo tgl_indo($data['tanggal']); ?></td>
                                            <td class="text-center"><?php
                                            $ket = $data['keterangan'];
                                             if ($ket=="Tabungan Masuk") {
                                                echo "-"; 
                                             } else {
                                                echo $data['keterangan'];
                                             }                                            
                                            ?></td>
                                            <td class="text-center"><?php 
                                             if (empty($data['no_masuk'])) {
                                                echo '<font color=red><b>' . $data['kegiatan'] . '</b></font>'; 
                                             } else {
                                                echo '<font color=green><b>' . $data['kegiatan'] . '</b></font>'; 
                                             }  
                                            ?></td>
                                            <td class="text-center">Rp <?php 
                                             if (empty($data['no_masuk'])) {
                                                echo number_format($data['nominal_k']); 
                                             } else {
                                                echo number_format($data['nominal_m']); 
                                             }  
                                            ?></td>
                                            <td class="text-center">
                                                <a href="#myModal" id="custId" data-bs-toggle="modal" data-id="<?php 
                                                if (empty($data['no_masuk'])) {
                                                    echo $data['no_keluar']; 
                                                } else {
                                                    echo $data['no_masuk']; 
                                                }
                                                ?>">
                                                <button type="button" class="btn btn-primary btn-sm btn-icon" title="Detail Transaksi"><i class="fe fe-alert-octagon icon-lg"></i></button></a>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                    </tbody>
                                        <?php
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

      <!-- modal detail -->
      <div id="myModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Detail Transaksi</h5>
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
                    url : 'detail_transaksi.php?id_tabungan='+ <?php echo $id_tabungan ?>,
                    data :  'no_transaksi='+ rowid,
                    success : function(data){
                    $('.fetched-data').html(data);//menampilkan data ke dalam modal
                    }
                });
            });
        });
      </script>
      <!-- END Data Modal-->

    <?php } ?>


  </body>
</html>
