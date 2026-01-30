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
              <!-- top heading removed as requested -->
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row row-sm">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Rekap Transfer</h5>
                        </div> 
                        <div class="card-body">
                            <style>
                              /* Adopted from Pencairan Tabungan styles to match visuals */
                              #user_data { font-size: 0.88rem; table-layout: auto; width: 100%; }
                              #user_data th {
                                padding: 10px 8px; white-space: nowrap; font-weight: 700; background-color: #f6f7fb; border: 1px solid #e8e9ed; color: #344054; font-size: 0.9rem; text-align: center !important;
                              }
                              /* Remove DataTables sorting arrows/icons for cleaner header */
                              #user_data thead th.sorting:after,
                              #user_data thead th.sorting_asc:after,
                              #user_data thead th.sorting_desc:after,
                              #user_data thead th.sorting:before,
                              #user_data thead th.sorting_asc:before,
                              #user_data thead th.sorting_desc:before,
                              #user_data thead th:after,
                              #user_data thead th:before { display: none !important; content: none !important; background-image: none !important; }

                              #user_data td { padding: 10px 8px; vertical-align: middle; overflow: hidden; border: 1px solid #eef0f4; color: #222; white-space: nowrap; text-overflow: ellipsis; }

                              .table-responsive { overflow-x: auto; max-width: 100%; }
                              .btn-sm { padding: 0.35rem 0.5rem; font-size: 0.78rem; margin: 2px; }
                              .cell-ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
                              .cell-wrap { display: block; white-space: normal; word-wrap: break-word; overflow-wrap: anywhere; max-width: 320px; }

                              /* Column sizing to match Pencairan layout (7 columns) */
                              #user_data td:nth-child(1) { width: 4%; text-align: center; }
                              #user_data td:nth-child(2) { width: 12%; text-align: center; }
                              #user_data td:nth-child(3) { width: 28%; text-align: left; }
                              #user_data td:nth-child(4) { width: 16%; text-align: center; }
                              #user_data td:nth-child(5) { width: 16%; text-align: right; padding-right: 12px; }
                              #user_data td:nth-child(6) { width: 10%; text-align: left; }
                              #user_data td:nth-child(7) { width: 6%; text-align: center; }

                              .table { margin-bottom: 0; border-radius: 8px; }
                              #user_data tbody tr:hover { background: #fbfcff; }
                            </style>

                            <!-- Filter pills: only Masuk / Keluar (default Masuk) -->
                            <div class="mb-3">
                              <div class="btn-group btn-group-sm" role="group" aria-label="Filter jenis">
                                <button type="button" class="btn btn-sm status-pill btn-outline-secondary active" data-status="transfer_masuk">Masuk</button>
                                <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="transfer_keluar">Keluar</button>
                              </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="user_data" border="1">
                                    <thead>
                                        <tr>
                                            <th class="text-center">No</th>
                                            <th class="text-center">ID Akun</th>
                                            <th class="text-center">Jenis</th>
                                            <th class="text-center">Tanggal</th>
                                            <th class="text-center">Jumlah</th>
                                            <th class="text-center">Keterangan</th>
                                            <th width="10%" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                        <?php
                                        // removed export / print controls per design: buttons and modals were deleted
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
                "order":[[3, 'asc']], // default: oldest -> newest (tanggal column)
                "dom": 'rtip', // hide length (l) and filter (f) controls
                "searching": false,
                "lengthChange": false,
                "ajax":{
                    url:"../../function/fetch_transfer.php",
                    type:"POST",
                    data: function(d){
                        // default to 'transfer_masuk' if not set
                        d.filter_jenis = window.transferFilter || 'transfer_masuk';
                    }
                },
                "initComplete": function(settings, json) {
                    // Ensure the default filter state is set globally
                    if (typeof window.transferFilter === 'undefined' || !window.transferFilter) window.transferFilter = 'transfer_masuk';
                },
                "columnDefs":[
                    {
                        "targets":[0, 6],
                        "orderable":false,
                    },
                ],

            });

            // filter pills (aligned like Semua Pengguna)
            $(document).on('click', '.status-pill', function(){
                $('.status-pill').removeClass('active');
                $(this).addClass('active');
                window.transferFilter = $(this).data('status');
                dataTable.ajax.reload();
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
                // determine parameter to send: prefer id_transaksi for numeric ids
                var payload = '';
                if (!isNaN(parseInt(rowid)) && isFinite(rowid)) {
                    payload = 'id_transaksi=' + encodeURIComponent(rowid);
                } else {
                    payload = 'no_transfer=' + encodeURIComponent(rowid);
                }
                //menggunakan fungsi ajax untuk pengambilan data
                $.ajax({
                    type : 'post',
                    url : 'detail_transfer.php',
                    data :  payload,
                    success : function(data){
                    $('.fetched-data').html(data);//menampilkan data ke dalam modal
                    }
                });
            });
        });
      </script>
      <!-- END Data Modal-->

      <!-- modal detail -->
      <div id="mdDelete" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <!--Modal header-->
            <div class="modal-header">
              <h5 class="modal-title">Hapus Transaksi</h5>
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
                    url : 'hapus_transaksi.php',
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
