<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<?php include "../dashboard/head.php"; ?>

<body>

    <?php include "../dashboard/icon.php"; ?>
    <?php include "../dashboard/header.php"; ?>

    <?php
    include "../../koneksi/fungsi_indotgl.php";
    include "../../koneksi/fungsi_waktu.php";
    $hariini = date('Y-m-d');
    ?>

    <div class="container-fluid">
        <div class="row">

            <?php include "../dashboard/menu.php"; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h5>Tabungan Masuk</h5>
                </div>

                <!-- ISI HALAMAN -->
                <div class="row row-sm">
                    <div class="col-lg-12">
                        <div class="card">

                            <div class="card-header" align="center">
                                <a href="tambah">
                                    <button type="button" class="btn btn-dark">
                                        <i class="fe fe-plus-square me-2"></i>Tambah
                                    </button>
                                </a>

                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cetaktoday">
                                    <i class="fe fe-save me-2"></i>Hari Ini
                                </button>

                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#cekdetail">
                                    <i class="fe fe-database me-2"></i>Cek Semua
                                </button>

                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exportexcel5">
                                    <i class="fe fe-calendar me-2"></i>Tanggal
                                </button>
                            </div>

                            <div class="card-body">
                                <style>
                                  /* Remove DataTables sort arrows and icons robustly */
                                  #user_data thead th.sorting:after,
                                  #user_data thead th.sorting_asc:after,
                                  #user_data thead th.sorting_desc:after,
                                  #user_data thead th.sorting:before,
                                  #user_data thead th.sorting_asc:before,
                                  #user_data thead th.sorting_desc:before,
                                  #user_data thead th:after,
                                  #user_data thead th:before { display:none !important; content:none !important; background-image:none !important; }
                                  /* Force headers centered */
                                  #user_data thead th { text-align:center !important; }
                                </style>
                                <div class="table-responsive">

                                    <!-- TABEL CLEAN UNTUK DATATABLES SERVER-SIDE -->
                                    <table class="table table-bordered table-hover mb-0 text-nowrap border-bottom w-100"
                                        id="user_data" border="1">
                                        <thead>
                                            <tr>
                                                <th class="text-center">No</th>
                                                <th class="text-center">Tanggal</th>
                                                <th class="text-center">Nama Pengguna</th>
                                                <th class="text-center">No. HP</th>
                                                <th class="text-center">Jenis Tabungan</th>
                                                <th class="text-center">Jumlah</th>
                                                <th class="text-center">Status</th>
                                                <th width="10%" class="text-center">Aksi</th>
                                            </tr>
                                        </thead>

                                        <!-- serverSide = TRUE → tbody harus kosong -->
                                        <tbody></tbody>

                                    </table>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </main>

        </div>
    </div>

    <?php include "../dashboard/js.php"; ?>

    <!-- DATA TABLE -->
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

    <script>
        // Disable DataTables default alert on ajax error and log instead
        $.fn.dataTable.ext.errMode = 'none';
        $(document).on('error.dt', '#user_data', function(e, settings, techNote, message) {
            console.error('DataTables error: ', message);
        });
        var dataTable = $('#user_data').DataTable({
            "processing": true,
            "serverSide": true,
            "order": [[1, 'desc']],
            "pageLength": 10,
            "lengthChange": false,
            "responsive": true,
            "autoWidth": false,
            "ajax": {
                // Use absolute path to avoid relative path issues in different pages
                url: "/gas/gas_web/login/function/fetch_masuk_petugas.php",
                type: "POST",
                error: function(xhr, status, error) {
                    console.error('Ajax error fetching table:', status, error);
                    console.error('XHR status:', xhr.status);
                    try {
                        console.error('Response text:', xhr.responseText);
                    } catch (e) {}
                }
            },
            "columnDefs": [
                { "targets": 0, "orderable": false, "className": "dt-center" },
                { "targets": 1, "className": "dt-center" },
                { "targets": 2, "className": "dt-left" },
                { "targets": 3, "className": "dt-center" },
                { "targets": 4, "className": "dt-left" },
                { "targets": 5, "className": "dt-right" },
                { "targets": 6, "className": "dt-center" },
                { "targets": 7, "orderable": false, "className": "dt-center" },
            ],
            "drawCallback": function(settings) {
                var api = this.api();
                var pageInfo = api.page.info();
                api.column(0, {page: 'current'}).nodes().each(function(cell, i) {
                    cell.innerHTML = '<div class="text-center">' + (pageInfo.start + i + 1) + '</div>';
                });
            },
        });
    </script>


    <!-- Modal Detail -->
    <div id="myModal" class="modal fade" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Detail Tabungan Masuk</h5>
                </div>

                <div class="modal-body">
                    <div class="fetched-data"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                </div>

            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#myModal').on('show.bs.modal', function(e) {
                var rowid = $(e.relatedTarget).data('id');

                $.ajax({
                    type: 'post',
                    url: 'detail_masuk.php',
                    data: {
                        no_masuk: rowid
                    },
                    success: function(data) {
                        $('.fetched-data').html(data);
                    }
                });
            });
        });
    </script>

</body>

</html>