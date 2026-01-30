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
              <h5>Transfer Tabungan</h5>
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
                                            <th class="text-center">No Transfer</th>
                                            <th class="text-center">ID Pengirim</th>
                                            <th class="text-center">Nama Pengirim</th>
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
                    url:"../../function/fetch_transfer_petugas.php",
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

      <!-- modal detail -->
      <div id="myModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Detail Transfer Tabungan</h5>
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
                    url : 'detail_transfer.php',
                    data :  'no_transfer='+ rowid,
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
