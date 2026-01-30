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
          </div>
        </div>

        <!-- ISI HALAMAN -->

        <!-- Row -->
        <div class="row row-sm">
          <div class="col-lg-12">
            <div class="card">
              <div class="card-header" align="center">
                <h5 class="mb-0">Pencairan Tabungan</h5>
              </div>
              <div class="card-body">
                <style>
                  /* Table style copied from Tabungan Masuk and adapted for Pencairan Tabungan (7 columns) */
                  #user_data {
                    font-size: 0.88rem;
                    table-layout: auto;
                    width: 100%;
                  }
                  #user_data th {
                    padding: 10px 8px;
                    white-space: nowrap;
                    font-weight: 700;
                    background-color: #f6f7fb;
                    border: 1px solid #e8e9ed;
                    color: #344054;
                    font-size: 0.9rem;
                    text-align: center !important;
                  }
                  /* Remove DataTables sort arrows/icons */
                  #user_data thead th.sorting:after,
                  #user_data thead th.sorting_asc:after,
                  #user_data thead th.sorting_desc:after,
                  #user_data thead th.sorting:before,
                  #user_data thead th.sorting_asc:before,
                  #user_data thead th.sorting_desc:before,
                  #user_data thead th:after,
                  #user_data thead th:before {
                    display: none !important;
                    content: none !important;
                    background-image: none !important;
                  }

                  #user_data td {
                    padding: 10px 8px;
                    vertical-align: middle;
                    overflow: hidden;
                    border: 1px solid #eef0f4;
                    color: #222;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                  }

                  .table-responsive { overflow-x: auto; max-width: 100%; }

                  .btn-sm { padding: 0.35rem 0.5rem; font-size: 0.78rem; margin: 2px; }
                  .btn-approve, .btn-reject { min-width: 48px; border-radius: 6px; }

                  .cell-ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
                  .cell-wrap { display: block; white-space: normal; word-wrap: break-word; overflow-wrap: anywhere; max-width: 320px; }

                  /* Column sizing for 7 columns */
                  #user_data td:nth-child(1) { width: 4%; text-align: center; }
                  #user_data td:nth-child(2) { width: 12%; text-align: center; }
                  #user_data td:nth-child(3) { width: 28%; text-align: left; }
                  #user_data td:nth-child(4) { width: 16%; text-align: center; }
                  #user_data td:nth-child(5) { width: 16%; text-align: right; padding-right: 12px; }
                  #user_data td:nth-child(6) { width: 10%; text-align: center; }
                  #user_data td:nth-child(7) { width: 6%; text-align: center; }

                  .table { margin-bottom: 0; border-radius: 8px; }

                  #user_data tbody tr:hover { background: #fbfcff; }
                </style>

                <!-- Filter buttons: Pending / Approved / Rejected / Semua (labels in Indonesian) -->
                <div class="mb-3">
                  <div class="btn-group btn-group-sm" role="group" aria-label="Filter status">
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="pending">Menunggu</button>
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="approved">Disetujui</button>
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="rejected">Ditolak</button>
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary active" data-status="all">Semua</button>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-bordered table-hover mb-0 text-nowrap border-bottom w-100" id="user_data" border="1">
                    <thead>
                      <tr>
                        <th class="text-center">No</th>
                        <th class="text-center">Tanggal Pengajuan</th>
                        <th class="text-start">Nama Pengguna</th>
                        <th class="text-center">Jenis Tabungan</th>
                        <th class="text-end">Jumlah Pencairan</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                      </tr>
                    </thead>
                    <?php
                    // legacy modal includes removed: .cek_hariini, .cek_detail, .cek_tanggal
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
  <script type="text/javascript" language="javascript">
    // current status filter
    var CURRENT_STATUS = 'all';

    var dataTable = $('#user_data').DataTable({
      "processing": true,
      "serverSide": true,
      // Default ordering: oldest submissions first (Tanggal Pengajuan asc)
      "order": [[1, 'asc']],
      "dom": 'rtip',
      "searching": false,
      "lengthChange": false,
      "ajax": {
        // absolute path to avoid relative resolution issues
        url: "/gas/gas_web/login/function/fetch_keluar_admin.php",
        type: "POST",
        data: function(d){
          // send status unless user chose All
          d.status = (CURRENT_STATUS === 'all') ? 'all' : CURRENT_STATUS;
        },
        error: function(xhr, status, error) {
          console.error('DataTables Ajax error (keluar):', status, error);
          console.error('XHR status:', xhr.status);
          try {
            console.error('Response text:', xhr.responseText);
          } catch (e) {}
        }
      },
      "columnDefs": [
        // Center common columns, left-align Nama Pengguna (col 2) and right-align Jumlah Pencairan (col 4)
        { "targets": [0,1,3,5,6], "className": "text-center" },
        { "targets": 2, "className": "text-start" },
        { "targets": 4, "className": "text-end" },
        // Disable ordering for No and Aksi
        { "targets": [0,6], "orderable": false },
        // Status renderer (badge) — map Indonesian labels to colors
        { "targets": 5, "render": function(data){ var raw = (data||''); var s = raw.toString().toLowerCase(); var map = { 'menunggu':'bg-warning', 'disetujui':'bg-success', 'ditolak':'bg-danger' }; var css = map[s] || 'bg-secondary'; return '<span class="badge '+css+'">'+raw+'</span>'; } },
        // Actions renderer — shows action menu only for pending
        { "targets": 6, "orderable": false, "searchable": false, "className": "text-center", "render": function(data){ if(!data) return ''; try{ var p = (typeof data === 'string') ? JSON.parse(data) : data; var id = p.id || ''; var no = p.no || ''; var status = (p.status||''); if (status.toLowerCase() !== 'pending') return '<span class="text-muted">-</span>'; var uid = 'action_' + id; var html = ''; html += '<div class="dropdown d-inline-block">'; html += '<button class="btn btn-sm btn-light border dropdown-toggle" type="button" id="'+uid+'" data-bs-toggle="dropdown" aria-expanded="false">&#8942;</button>'; html += '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="'+uid+'">'; html += '<li><a class="dropdown-item action-approve" href="#" data-id="'+id+'" data-no="'+no+'">Setujui</a></li>'; html += '<li><a class="dropdown-item action-reject" href="#" data-id="'+id+'" data-no="'+no+'">Tolak</a></li>'; html += '</ul></div>'; return html; } catch(e){ return ''; } } }
      ],

    });

    // status pill click
    $(document).on('click', '.status-pill', function(e){
      e.preventDefault();
      $('.status-pill').removeClass('active');
      $(this).addClass('active');
      var st = $(this).data('status');
      CURRENT_STATUS = st;
      dataTable.ajax.reload(null, false);
    });

    // ADMIN ACTIONS: approve/reject pending pencairan
    var ADMIN_ID = <?php echo isset($_SESSION['id_user']) ? intval($_SESSION['id_user']) : 0; ?>;

    // Approve (supports both legacy .approve-btn and new .action-approve dropdown item)
    $(document).on('click', '.approve-btn, .action-approve', function(e){
      e.preventDefault();
      var no = $(this).data('no') || $(this).data('id');
      if(!no) return;
      if(!confirm('Setujui penarikan ' + no + ' ?')) return;
      $.post('/gas/gas_web/flutter_api/approve_penarikan.php', { no_keluar: no, action: 'approve', approved_by: ADMIN_ID }, function(resp){
        if(resp && resp.success){
          $.growl.notice({ title: 'Sukses', message: resp.message });
          dataTable.ajax.reload(null, false);
        } else {
          $.growl.error({ title: 'Gagal', message: (resp && resp.message) ? resp.message : 'Gagal memproses' });
        }
      }, 'json').fail(function(){
        $.growl.error({ title: 'Gagal', message: 'Koneksi gagal' });
      });
    });

    // Reject (supports both legacy .reject-btn and new .action-reject dropdown item)
    $(document).on('click', '.reject-btn, .action-reject', function(e){
      e.preventDefault();
      var no = $(this).data('no') || $(this).data('id');
      if(!no) return;
      var reason = prompt('Alasan penolakan (opsional):');
      if (reason === null) return; // user cancelled
      $.post('/gas/gas_web/flutter_api/approve_penarikan.php', { no_keluar: no, action: 'reject', approved_by: ADMIN_ID, catatan: reason }, function(resp){
        if(resp && resp.success){
          $.growl.notice({ title: 'Sukses', message: resp.message });
          dataTable.ajax.reload(null, false);
        } else {
          $.growl.error({ title: 'Gagal', message: (resp && resp.message) ? resp.message : 'Gagal memproses' });
        }
      }, 'json').fail(function(){
        $.growl.error({ title: 'Gagal', message: 'Koneksi gagal' });
      });
    });

  </script>
  <!-- END Server Side -->

  <!-- Fix dropdowns: ensure menus appear above table and are not clipped -->
  <style>
    /* Allow the table wrapper to show overflow so menus are not clipped */
    #user_data_wrapper .table-responsive { overflow: visible !important; }
    /* Give dropdowns high z-index when appended to body */
    #user_data .dropdown-menu, body > .dropdown-menu { z-index: 3000 !important; }
  </style>
  <script>
  (function(){
    // delegated handlers to move dropdown menus to body when shown (fixes clipping/overlap)
    $(document).off('show.bs.dropdown', '#user_data .dropdown').on('show.bs.dropdown', '#user_data .dropdown', function(e){
      var $dropdown = $(this);
      var $toggle = $dropdown.find('.dropdown-toggle');
      var $menu = $dropdown.find('.dropdown-menu');
      if (!$menu.length || !$toggle.length) return;
      try {
        $menu.data('__orig_parent', $menu.parent());
        $menu.appendTo('body').css({ position: 'absolute', display: 'block', zIndex: 3000 });
        var rect = $toggle[0].getBoundingClientRect();
        var mw = $menu.outerWidth();
        var left = rect.left + window.scrollX;
        if (left + mw > window.scrollX + window.innerWidth) {
          left = rect.right + window.scrollX - mw;
        }
        if (left < window.scrollX + 5) left = window.scrollX + 5;
        var top = rect.bottom + window.scrollY;
        $menu.css({ left: left + 'px', top: top + 'px' }).attr('data-appended-to-body','1');
      } catch (err) { console.error('dropdown move error', err); }
    });

    $(document).off('hide.bs.dropdown', '#user_data .dropdown').on('hide.bs.dropdown', '#user_data .dropdown', function(e){
      var $dropdown = $(this);
      var $menu = $dropdown.find('.dropdown-menu');
      if (!$menu.length) return;
      try {
        if ($menu.attr('data-appended-to-body') === '1') {
          $menu.removeAttr('data-appended-to-body');
          var $orig = $menu.data('__orig_parent');
          $menu.css({ position: '', left: '', top: '', zIndex: ''});
          if ($orig && $orig.length) { $menu.appendTo($orig); }
        }
      } catch (err) { console.error('dropdown restore error', err); }
    });

    // Hide dropdowns on scroll to keep positioning correct
    $('#user_data').closest('.table-responsive').off('scroll.dropfix').on('scroll.dropfix', function(){
      $('.dropdown.show .dropdown-toggle').each(function(){ try{ $(this).dropdown('hide'); }catch(e){} });
    });

    // Initialize dropdowns after each DataTable draw
    $(document).off('draw.dt', '#user_data').on('draw.dt', '#user_data', function(){
      $('#user_data .dropdown-toggle').each(function(){ try{ $(this).dropdown(); }catch(e){} });
    });

    // Initial pass
    $(function(){ $('#user_data .dropdown-toggle').each(function(){ try{ $(this).dropdown(); }catch(e){} }); });
  })();
  </script>

  <!-- Proses Tambah Transaksi -->
  <?php
  include('../../koneksi/config.php');
  if (isset($_POST['ttambah'])) { //['ttambah'] merupakan name dari button di form tambah
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
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }
    $hostname = gethostbyaddr($_SERVER['REMOTE_ADDR']);

    $ipa = $ip;

    $query1          = mysqli_query($con, 'select * from pengguna where id_tabungan = "' . $id_tabungan . '"');
    $row            = mysqli_fetch_array($query1);
    $saldo           = $row['saldo'];

    $hasil  = $row['saldo'] - $_POST['jumlah'];
    $tgl  = $_POST['tanggal'];
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
      // Use ledger helper to perform withdrawal and update transaksi_terakhir only
      include_once __DIR__ . '/../../function/ledger_helpers.php';
      $user_id_numeric = intval($row['id']);
      $ok = insert_ledger_keluar($con, $user_id_numeric, floatval($_POST['jumlah']), 'Penarikan Admin: ' . $keterangan, 1, null);
      $sql    = 'update pengguna set transaksi_terakhir="' . $tgl . '" where id_tabungan="' . $id_tabungan . '"';

      //Input Data Transaksi
      $sql1 = "INSERT INTO t_keluar VALUES(NULL, '$no_keluar','$nama_pengguna','$id_tabungan','$kelas_pengguna','$tanggal_keluar','$jumlah',CURRENT_TIMESTAMP(),'$keterangan')";
      $sql2 = "INSERT INTO transaksi VALUES(NULL, '','$no_keluar','$nama_pengguna','$id_tabungan','$kelas_pengguna','$keterangan','0','$jumlah','$tanggal_keluar','$username','$kegiatan','$ipa',CURRENT_TIMESTAMP())";

      $query2  = mysqli_query($con, $sql);
      $query3  = mysqli_query($con, $sql1);
      $query4  = mysqli_query($con, $sql2);
    }
    if ($query2) {
      //Jika Sukses
  ?>
      <script language="JavaScript">
        setTimeout(function() {
          $.growl.notice({
            title: "Sukses",
            message: "Transaksi Penarikan Tabungan Berhasil Di Tambah !"
          });
          window.setTimeout(function() {
            window.location.replace('../keluar/');
          }, 1000);
        });
      </script>
  <?php
    } else {
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
    $(document).ready(function() {
      $('#myModal').on('show.bs.modal', function(e) {
        var rowid = $(e.relatedTarget).data('id');
        //menggunakan fungsi ajax untuk pengambilan data
        $.ajax({
          type: 'post',
          url: 'detail_keluar.php',
          data: 'no_keluar=' + rowid,
          success: function(data) {
            $('.fetched-data').html(data); //menampilkan data ke dalam modal
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
    $(document).ready(function() {
      $('#mdDelete').on('show.bs.modal', function(e) {
        var rowid = $(e.relatedTarget).data('id');
        //menggunakan fungsi ajax untuk pengambilan data
        $.ajax({
          type: 'post',
          url: 'hapus_transaksi.php',
          data: 'no_keluar=' + rowid,
          success: function(data) {
            $('.fetched-data').html(data); //menampilkan data ke dalam modal
          }
        });
      });
    });
  </script>
  <!-- END Data Modal-->


</body>

</html>