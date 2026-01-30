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
  
  // Cek apakah ada pesan sukses dari confirm_topup.php
  $success_message = null;
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  if (isset($_SESSION['success_message'])) {
      $success_message = $_SESSION['success_message'];
      unset($_SESSION['success_message']);
  }

  ?>


  <div class="container-fluid">
    <div class="row">

      <?php include "../dashboard/menu.php"; ?>


      <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
          <div class="btn-toolbar mb-2 mb-md-0"></div>
        </div>

        <!-- ISI HALAMAN -->

        <!-- Row -->
        <div class="row row-sm">
          <div class="col-lg-12">
            <div class="card">
              <div class="card-header" align="center">
                <h5 style="margin: 0;">TABUNGAN MASUK</h5>
              </div>
              <div class="card-body">
                <style>
                  /* General table visual refresh for a professional look */
                  #user_data {
                    font-size: 0.88rem;
                    table-layout: auto; /* let browser size columns naturally for a balanced layout */
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
                  }
                  /* Remove DataTables sort arrows and icons robustly (before/after/background) */
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
                  /* Force all header titles to be centered */
                  #user_data thead th { text-align: center !important; }
                  /* Keep utility classes if needed */
                  #user_data thead th.text-start { text-align: center !important; }
                  #user_data thead th.text-end { text-align: center !important; }
                  #user_data thead th.text-center { text-align: center !important; }

                  #user_data td {
                    padding: 10px 8px;
                    vertical-align: middle;
                    overflow: visible;
                    border: 1px solid #eef0f4;
                    color: #222;
                  }
                  #user_data tbody tr { height: auto; }

                  /* Responsive container tweaks: hide horizontal scrollbar and allow DataTables responsive handling */
                  .table-responsive { overflow-x: auto; max-width: 100%; }

                  /* Button polishing */
                  .btn-sm { padding: 0.35rem 0.5rem; font-size: 0.78rem; margin: 2px; }
                  .btn-approve, .btn-reject { min-width: 48px; border-radius: 6px; }

                  /* Cell helpers: ellipsis for single-line, wrap for multi-line */
                  .cell-ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
                  .cell-wrap { display: block; white-space: normal; word-wrap: break-word; overflow-wrap: anywhere; max-width: 320px; }

                  /* Keep table rows compact: most cells use ellipsis to avoid very tall rows */
                  #user_data th { white-space: nowrap; }
                  #user_data td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                  /* Allow the name column (3) to wrap when necessary */
                  #user_data td:nth-child(3) .cell-wrap { white-space: normal; }

                  /* Alignments for specific columns */
                  #user_data td:nth-child(1) { width: 4%; text-align: center; }
                  #user_data td:nth-child(2) { width: 10%; text-align: center; }
                  #user_data td:nth-child(3) { width: 24%; text-align: left; }
                  #user_data td:nth-child(4) { width: 12%; text-align: center; }
                  #user_data td:nth-child(5) { width: 18%; text-align: left; }
                  #user_data td:nth-child(6) { width: 12%; text-align: right; padding-right: 12px; }
                  #user_data td:nth-child(7) { width: 10%; text-align: center; }
                  #user_data td:nth-child(8) { width: 6%; text-align: center; }

                  /* Compact but clean table */
                  .table { margin-bottom: 0; border-radius: 8px; }

                  /* Subtle hover to emphasize row */
                  #user_data tbody tr:hover { background: #fbfcff; }

                  /* Professional Setor Manual Button Styling */
                  .btn-setor-manual {
                    background: linear-gradient(135deg, var(--gas-primary) 0%, #e85a00 100%);
                    border: none;
                    color: white;
                    font-weight: 600;
                    font-size: 0.85rem;
                    padding: 0.45rem 1rem;
                    border-radius: 8px;
                    transition: all 0.3s ease;
                    box-shadow: 0 2px 8px rgba(255, 107, 0, 0.2);
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    letter-spacing: 0.3px;
                  }

                  .btn-setor-manual:hover {
                    background: linear-gradient(135deg, #e85a00 0%, #d64e00 100%);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
                    color: white;
                    text-decoration: none;
                  }

                  .btn-setor-manual:active {
                    transform: translateY(0);
                    box-shadow: 0 2px 6px rgba(255, 107, 0, 0.2);
                  }

                  .btn-setor-manual i {
                    font-size: 1rem;
                  }
                </style>
                
                <!-- Tombol Setor Manual Admin -->
                <div class="mb-3">
                  <button type="button" class="btn-setor-manual" data-bs-toggle="modal" data-bs-target="#modalSetorManual">
                    <i class="fas fa-plus-circle"></i> Setor Manual
                  </button>
                </div>

                <!-- Filter buttons: Pending / Approved / Rejected / Semua -->
                <div class="mb-3">
                  <div class="btn-group btn-group-sm" role="group" aria-label="Filter status">
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="pending">Menunggu</button>
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="approved">Disetujui</button>
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="rejected">Ditolak</button>
                    <button type="button" class="btn btn-sm status-pill btn-outline-secondary active" data-status="all">Semua</button>
                  </div>
                </div>

                <div class="table-responsive">
                  <table class="table table-bordered table-hover mb-0 border-bottom" id="user_data" border="1">
                    <thead>
                      <tr>
                        <th class="text-center">No</th>
                        <th class="text-center">Tanggal</th>
                        <th class="text-center">Nama Pengguna</th>
                        <th class="text-center">No. HP</th>
                        <th class="text-center">Jenis Tabungan</th>
                        <th class="text-center">Jumlah</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
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

  <!-- Server Side -->
  <script type="text/javascript" language="javascript">
    // Current status filter (pending / approved / rejected / all)
    var currentFilter = 'all';

    // Set visual active button and reload table
    function setFilter(f) {
      currentFilter = f;
      $('.status-pill').removeClass('active');
      $('.status-pill[data-status="' + f + '"]').addClass('active');
      if (typeof dataTable !== 'undefined' && dataTable.ajax) { dataTable.ajax.reload(); }
    }

    // Init DataTable
    var dataTable = $('#user_data').DataTable({
      "processing": true,
      "serverSide": true,
      "order": [[1, 'desc']], // default order by Tanggal desc
      "pageLength": 10,
      "lengthChange": false,
      "responsive": true,
      "autoWidth": false,
      "ajax": {
        // gunakan absolute path supaya tidak salah resolving relatif
        url: "/gas/gas_web/login/function/fetch_masuk.php",
        type: "POST",
        data: function(d) {
          // send the chosen status filter to the server
          d.status_filter = currentFilter;
        },
        error: function(xhr, status, error) {
          console.error('DataTables Ajax error:', status, error);
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
        { "targets": [1,2,3,4,5,6], "orderable": true },
        { "targets": [0,7], "responsivePriority": 1 }
      ],
      "drawCallback": function(settings) {
        // Replace first column with sequence number (1..n) on current page
        var api = this.api();
        var pageInfo = api.page.info();
        api.column(0, {page: 'current'}).nodes().each(function(cell, i) {
          cell.innerHTML = '<div class="text-center">' + (pageInfo.start + i + 1) + '</div>';
        });

        // Center cells in 'Jenis Tabungan' column that equal 'Tabungan Investasi'
        api.column(4, {page: 'current'}).nodes().each(function(cell, i) {
          var $cell = $(cell);
          // Normalize text and compare
          if ($cell.text().trim().toLowerCase() === 'tabungan investasi') {
            $cell.addClass('text-center');
            $cell.css('text-align', 'center');
          } else {
            $cell.removeClass('text-center');
            $cell.css('text-align', 'left'); // keep default left alignment for others
          }
        });

      },
      "dom": "tp",
      "bPaginate": true,
      "bInfo": false

    });

    // Hook filter buttons
    $(document).on('click', '.status-pill', function() { setFilter($(this).data('status')); });
    // Set initial active filter button
    setFilter('all');

    // Delegate approve/reject button clicks (server-side buttons rendered in DataTables rows)
    // Helper functions for dropdown actions
    function approveMasuk(id){
      if (!confirm('Akan dialihkan ke halaman konfirmasi top-up. Lanjutkan?')) return;
      window.location.href = './confirm_topup.php?id=' + encodeURIComponent(id);
    }

    function rejectMasuk(id){
      if (!confirm('Yakin ingin menolak top-up ini?')) return;
      // find the row by data-id in the rendered table
      var $row = $('#user_data').find('[data-id="' + id + '"]').closest('tr');
      $.ajax({
        url: '/gas/gas_web/flutter_api/admin_verifikasi_mulai_nabung.php',
        method: 'POST',
        dataType: 'json',
        data: { id_mulai_nabung: id, action: 'tolak' },
        success: function(j) {
          if (j && j.success) {
            var statusCol = $row.find('td:eq(6)');
            if (statusCol.length) { statusCol.html('<span class="badge bg-danger">' + 'ditolak' + '</span>'); statusCol.attr('title','ditolak'); }
            var actionCol = $row.find('td:eq(7)');
            if (actionCol.length) { actionCol.html('<span class="text-muted">-</span>'); }
            $row.find('.btn-reject, .btn-approve').prop('disabled', true).css('opacity','0.5');
            if (typeof dataTable !== 'undefined' && dataTable.ajax) { dataTable.ajax.reload(null, false); }
            $.growl.error({ title: 'Ditolak', message: j.message || 'Permintaan top-up telah ditolak' });
          } else {
            $.growl.error({ title: 'Gagal', message: (j && j.message) ? j.message : 'Gagal memproses verifikasi' });
          }
        },
        error: function(xhr, status, err) {
          var msg = 'Request gagal';
          try {
            var txt = xhr && xhr.responseText ? xhr.responseText.trim() : '';
            if (txt) {
              var parsed = null;
              try { parsed = JSON.parse(txt); } catch(e) { parsed = null; }
              if (parsed && parsed.message) msg = parsed.message;
              else msg = txt.length > 200 ? txt.substr(0,200) + '...' : txt;
            }
          } catch(e){}
          console.error('rejectMasuk error', status, err, xhr.responseText);
          $.growl.error({ title: 'Error', message: msg });
        }
      });
    }

    function showDetailMasuk(e, el){
      e.preventDefault();
      var id = el.getAttribute('data-id');
      $('#myModal').modal('show');
      $.ajax({
        type: 'post',
        url: 'detail_masuk.php',
        data: { no_masuk: id },
        success: function(data){ $('.fetched-data').html(data); },
        error: function(xhr){ $('.fetched-data').html('<div class="text-danger">Gagal mengambil detail: ' + (xhr.responseText || 'Server error') + '</div>'); }
      });
    }

    // Existing delegated handlers (kept for compatibility if buttons still exist)
    $(document).on('click', '.btn-approve', function(e) {
      e.preventDefault();
      var $btn = $(this);
      var id = $btn.data('id');
      var $row = $btn.closest('tr');
      // Redirect admin to confirmation page so they can input / confirm the saldo top-up
      if (!confirm('Akan dialihkan ke halaman konfirmasi top-up. Lanjutkan?')) return;
      window.location.href = './confirm_topup.php?id=' + encodeURIComponent(id);
    });

    $(document).on('click', '.btn-reject', function(e) {
      e.preventDefault();
      var $btn = $(this);
      var id = $btn.data('id');
      // use central helper to handle reject action
      rejectMasuk(id);
    });


  </script>
  <!-- END Server Side -->

  <script>
    // Initialize Bootstrap tooltips for elements created dynamically by DataTables
    function initTooltips() {
      try {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (el) {
          if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            // avoid re-initializing same element
            if (!el._tooltipInit) {
              new bootstrap.Tooltip(el);
              el._tooltipInit = true;
            }
          }
        });
      } catch (e) {
        // silent
        console.error('Tooltip init error', e);
      }
    }

    // Run after DataTables has drawn a page
    $(document).on('draw.dt', '#user_data', function() {
      initTooltips();
    });

    // Show success message if present (from confirm_topup redirect)
    <?php if ($success_message): ?>
      setTimeout(function() {
        $.growl.notice({
          title: 'Sukses',
          message: '<?php echo htmlspecialchars(addslashes($success_message), ENT_QUOTES, 'UTF-8'); ?>'
        });
      }, 500);
    <?php endif; ?>
    // Also init on DOM ready for first load
    document.addEventListener('DOMContentLoaded', function() { setTimeout(initTooltips, 300); });
  </script>

  <!-- Fix: Ensure dropdown menus in the masuk table are not clipped by overflow and appear above other elements -->
  <style>
    /* Allow the table wrapper to show overflow (so menus are not clipped) */
    #user_data_wrapper .table-responsive { overflow: visible !important; }
    /* Ensure dropdowns have a high z-index when appended to body */
    #user_data .dropdown-menu, body > .dropdown-menu { z-index: 3000 !important; }
  </style>
  <script>
  (function(){
    // Use delegated event handlers so dynamically-created rows from DataTables are handled
    $(document).off('show.bs.dropdown', '#user_data .dropdown').on('show.bs.dropdown', '#user_data .dropdown', function(e){
      var $dropdown = $(this);
      var $toggle = $dropdown.find('.dropdown-toggle');
      var $menu = $dropdown.find('.dropdown-menu');
      if (!$menu.length || !$toggle.length) return;
      try {
        // store original parent for restore later
        $menu.data('__orig_parent', $menu.parent());
        // append to body to avoid clipping
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

    // Hide dropdowns when the table scrolls (keeps menu from floating incorrectly)
    $('#user_data').closest('.table-responsive').off('scroll.dropfix').on('scroll.dropfix', function(){
      $('.dropdown.show .dropdown-toggle').each(function(){
        try { $(this).dropdown('hide'); } catch(e) {}
      });
    });

    // Ensure dropdowns inside DataTable are initialized after each draw
    $(document).off('draw.dt', '#user_data').on('draw.dt', '#user_data', function(){
      $('#user_data .dropdown-toggle').each(function(){ try{ $(this).dropdown(); }catch(e){} });
    });

    // Initial pass for any already-rendered rows
    $(function(){ $('#user_data .dropdown-toggle').each(function(){ try{ $(this).dropdown(); }catch(e){} }); });
  })();
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
    // Support show triggered by our custom showDetailMasuk or by data-bs-toggle attributes
    $(document).ready(function() {
      $('#myModal').on('show.bs.modal', function(e) {
        // If triggered by our JS via showDetailMasuk, it already loaded content; if triggered by attribute, load here
        var related = e.relatedTarget;
        if (related && related.dataset && related.dataset.id) {
          var rowid = related.dataset.id;
          $.ajax({ type: 'post', url: 'detail_masuk.php', data: { no_masuk: rowid }, success: function(data){ $('.fetched-data').html(data); } });
        }
      });
    });
  </script>

  <!-- Proses Tambah Transaksi -->
  <?php
  include('../../koneksi/config.php');
  if (isset($_POST['ttambah'])) { //['ttambah'] merupakan name dari button di form tambah
    $no_masuk           = $_POST['no'];
    $tanggal_masuk      = $_POST['tanggal'];
    $nama_pengguna         = $_POST['nama_siswa'];
    $id_tabungan        = $_POST['id_tabungan'];
    $kelas_pengguna        = $_POST['nama_kelas'];
    $jumlah             = $_POST['jumlah'];
    $username           = $_POST['user'];
    $kegiatan           = $_POST['kegiatan'];

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

    $hasil  = $row['saldo'] + $_POST['jumlah'];
    $tgl  = $_POST['tanggal'];

    // Update transaksi_terakhir only; ledger helper will adjust saldo.
    include_once __DIR__ . '/../../function/ledger_helpers.php';
    $user_id_numeric = intval($row['id']);
    $ok = insert_ledger_masuk($con, $user_id_numeric, floatval($_POST['jumlah']), 'Setoran Admin: ' . $kegiatan, 1, null);
    $sql    = 'update pengguna set transaksi_terakhir="' . $tgl . '" where id_tabungan="' . $id_tabungan . '"';

    //Input Data Transaksi
    $sql1 = "INSERT INTO t_masuk VALUES(NULL, '$no_masuk','$nama_pengguna','$id_tabungan','$kelas_pengguna','$tanggal_masuk','$jumlah',CURRENT_TIMESTAMP(),'$kegiatan')";
    $sql2 = "INSERT INTO transaksi VALUES(NULL, '$no_masuk','','$nama_pengguna','$id_tabungan','$kelas_pengguna','$kegiatan','$jumlah','0','$tanggal_masuk','$username','$kegiatan','$ipa',CURRENT_TIMESTAMP())";

    $query2  = mysqli_query($con, $sql);
    $query3  = mysqli_query($con, $sql1);
    $query4  = mysqli_query($con, $sql2);

    if ($query2) {
      //Jika Sukses
  ?>
      <script language="JavaScript">
        setTimeout(function() {
          $.growl.notice({
            title: "Sukses",
            message: "Transaksi Tabungan Masuk Berhasil Di Tambah !"
          });
          window.setTimeout(function() {
            window.location.replace('../masuk/');
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
                    window.location.replace('../masuk/tambah');
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
          <h5 class="modal-title">Detail Tabungan Masuk</h5>
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
          url: 'detail_masuk.php',
          data: 'no_masuk=' + rowid,
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
          data: 'no_masuk=' + rowid,
          success: function(data) {
            $('.fetched-data').html(data); //menampilkan data ke dalam modal
          }
        });
      });
    });
  </script>
  <!-- END Data Modal-->

  <!-- Modal Setor Manual Admin -->
  <div id="modalSetorManual" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Setor Saldo Manual</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="formSetorManual">
            <!-- Pilih Pengguna -->
            <div class="mb-3">
              <label for="idPengguna" class="form-label">Pengguna <span class="text-danger">*</span></label>
              <select class="form-control" id="idPengguna" name="id_pengguna" required>
                <option value="">-- Pilih Pengguna --</option>
              </select>
              <small class="form-text text-muted">Hanya user aktif yang ditampilkan</small>
            </div>

            <!-- Pilih Jenis Tabungan -->
            <div class="mb-3">
              <label for="idJenisTabungan" class="form-label">Jenis Tabungan <span class="text-danger">*</span></label>
              <select class="form-control" id="idJenisTabungan" name="id_jenis_tabungan" required>
                <option value="">-- Pilih Jenis Tabungan --</option>
              </select>
            </div>

            <!-- Nominal Setoran -->
            <div class="mb-3">
              <label for="jumlahSetor" class="form-label">Nominal Setoran <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">Rp</span>
                <input type="number" class="form-control text-end" id="jumlahSetor" name="jumlah" 
                       placeholder="0" min="1" step="100" required>
              </div>
              <small class="form-text text-muted">Jumlah harus lebih dari 0</small>
            </div>

            <!-- Tanggal Setor -->
            <div class="mb-3">
              <label for="tanggalSetor" class="form-label">Tanggal Setor</label>
              <input type="date" class="form-control" id="tanggalSetor" name="tanggal_setor">
              <small class="form-text text-muted">Default: Hari ini</small>
            </div>

            <!-- Keterangan -->
            <div class="mb-3">
              <label for="keteranganSetor" class="form-label">Keterangan (Opsional)</label>
              <textarea class="form-control" id="keteranganSetor" name="keterangan" 
                        rows="3" placeholder="Catatan atau alasan setor manual..."></textarea>
            </div>

            <!-- Hidden field for admin_id (akan diisi via PHP) -->
            <input type="hidden" id="adminId" name="admin_id" value="<?php echo isset($id) ? intval($id) : ''; ?>">
            <!-- Hidden fields untuk nama_pengguna dan no_hp (dari data pengguna yang dipilih) -->
            <input type="hidden" id="hiddenNamaPengguna" name="nama_pengguna" value="">
            <input type="hidden" id="hiddenNoHp" name="no_hp" value="">
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="button" class="btn btn-primary" id="btnSimpanSetor">Simpan Setor</button>
        </div>
      </div>
    </div>
  </div>
  <!-- END Modal Setor Manual -->

  <!-- Script untuk Setor Manual Admin -->
  <script type="text/javascript">
    // Get current admin ID from PHP (sudah diset di hidden field)
    var currentAdminId = parseInt($('#adminId').val()) || 0;

    // Load data pengguna dan jenis tabungan saat modal dibuka
    $('#modalSetorManual').on('show.bs.modal', function() {
      // Admin ID sudah ada dari PHP, jangan overwrite
      // Hanya set ulang jika kosong (safety check)
      if (!$('#adminId').val()) {
        $('#adminId').val(currentAdminId);
      }
      
      // Set default tanggal ke hari ini
      var today = new Date().toISOString().split('T')[0];
      $('#tanggalSetor').val(today);

      // Load pengguna list jika belum
      if ($('#idPengguna option').length <= 1) {
        loadPenggunaList();
      }

      // Load jenis tabungan list jika belum
      if ($('#idJenisTabungan option').length <= 1) {
        loadJenisTabunganList();
      }
    });

    // Load pengguna aktif
    function loadPenggunaList() {
      $.ajax({
        url: '/gas/gas_web/flutter_api/get_pengguna_aktif.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response && response.success && Array.isArray(response.data)) {
            var $select = $('#idPengguna');
            
            // Clear existing options except placeholder
            $select.find('option:not(:first)').remove();
            
            // Add pengguna options dengan format: Nama (Nomor HP)
            response.data.forEach(function(pengguna) {
              var optionText = pengguna.nama;
              if (pengguna.nomor_hp && pengguna.nomor_hp.trim() !== '') {
                optionText += ' <small style="font-size: 0.85em; color: #666;">(' + pengguna.nomor_hp + ')</small>';
              }
              
              var $option = $('<option></option>')
                .attr('value', pengguna.id)
                .attr('data-hp', pengguna.nomor_hp || '')
                .attr('data-nama', pengguna.nama || '')
                .html(pengguna.nama + (pengguna.nomor_hp ? ' (' + pengguna.nomor_hp + ')' : ''));
              
              $select.append($option);
            });
            
            if (response.data.length === 0) {
              $select.append($('<option></option>').text('Tidak ada data pengguna'));
            }
            
            // Handle pengguna selection change
            $select.off('change').on('change', function() {
              var selectedOption = $(this).find('option:selected');
              var namaSelected = selectedOption.attr('data-nama') || '';
              var hpSelected = selectedOption.attr('data-hp') || '';
              $('#hiddenNamaPengguna').val(namaSelected);
              $('#hiddenNoHp').val(hpSelected);
              console.log('Selected pengguna - Nama: ' + namaSelected + ', HP: ' + hpSelected);
            });
          } else {
            console.error('Invalid response format:', response);
            $.growl.warning({ title: 'Warning', message: 'Data pengguna kosong' });
          }
        },
        error: function(xhr, status, error) {
          console.error('Error loading pengguna:', status, error, xhr.responseText);
          $.growl.error({ title: 'Error', message: 'Gagal memuat data pengguna' });
        }
      });
    }

    // Load jenis tabungan
    function loadJenisTabunganList() {
      $.ajax({
        url: '/gas/gas_web/flutter_api/get_jenis_tabungan.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
          if (response && response.success && Array.isArray(response.data)) {
            var $select = $('#idJenisTabungan');
            
            // Clear existing options except placeholder
            $select.find('option:not(:first)').remove();
            
            response.data.forEach(function(jenis) {
              var displayText = jenis.nama_jenis || jenis.nama || 'Unknown';
              // Add "Tabungan " prefix if not already present
              if (!displayText.toLowerCase().startsWith('tabungan')) {
                displayText = 'Tabungan ' + displayText;
              }
              var $option = $('<option></option>')
                .attr('value', jenis.id)
                .text(displayText);
              
              $select.append($option);
            });
            
            if (response.data.length === 0) {
              $select.append($('<option></option>').text('Tidak ada data jenis tabungan'));
            }
          } else {
            console.error('Invalid response format:', response);
            $.growl.warning({ title: 'Warning', message: 'Data jenis tabungan kosong' });
          }
        },
        error: function(xhr, status, error) {
          console.error('Error loading jenis tabungan:', status, error, xhr.responseText);
          $.growl.error({ title: 'Error', message: 'Gagal memuat data jenis tabungan' });
        }
      });
    }

    // Handle simpan setor
    $('#btnSimpanSetor').on('click', function() {
      // Validasi manual (lebih fleksibel)
      var idPengguna = $('#idPengguna').val();
      var idJenisTabungan = $('#idJenisTabungan').val();
      var jumlah = $('#jumlahSetor').val();
      var tanggalSetor = $('#tanggalSetor').val();
      var keterangan = $('#keteranganSetor').val();
      var adminId = $('#adminId').val();

      // Check required fields
      if (!idPengguna) {
        $.growl.error({ title: 'Error', message: 'Pilih pengguna terlebih dahulu' });
        $('#idPengguna').focus();
        return;
      }

      if (!idJenisTabungan) {
        $.growl.error({ title: 'Error', message: 'Pilih jenis tabungan terlebih dahulu' });
        $('#idJenisTabungan').focus();
        return;
      }

      if (!jumlah || jumlah <= 0) {
        $.growl.error({ title: 'Error', message: 'Nominal harus lebih dari 0' });
        $('#jumlahSetor').focus();
        return;
      }

      if (!tanggalSetor) {
        $.growl.error({ title: 'Error', message: 'Pilih tanggal setor' });
        $('#tanggalSetor').focus();
        return;
      }

      var formData = {
        id_pengguna: idPengguna,
        id_jenis_tabungan: idJenisTabungan,
        jumlah: jumlah,
        tanggal_setor: tanggalSetor,
        keterangan: keterangan,
        admin_id: adminId,
        nama_pengguna: $('#hiddenNamaPengguna').val(),
        no_hp: $('#hiddenNoHp').val()
      };

      // Send to API
      console.log('Sending setor data:', formData);
      $.ajax({
        url: '/gas/gas_web/flutter_api/setor_manual_admin.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
          console.log('API Response:', response);
          if (response.success) {
            $.growl.notice({
              title: 'Sukses ✓',
              message: 'Setor saldo manual berhasil! Saldo user sudah diupdate ke akun mereka.'
            });

            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('modalSetorManual'));
            if (modal) modal.hide();

            // Reset form
            $('#formSetorManual')[0].reset();
            $('#tanggalSetor').val(new Date().toISOString().split('T')[0]);
            // Jangan reset admin ID - tetap gunakan ID yang sudah ter-set

            // Refresh tabel
            if (typeof dataTable !== 'undefined' && dataTable.ajax) {
              dataTable.ajax.reload();
            }
          } else {
            $.growl.error({
              title: 'Gagal',
              message: response.message || 'Gagal melakukan setor manual'
            });
          }
        },
        error: function(xhr) {
          console.error('AJAX Error:', xhr.status, xhr.statusText, xhr.responseText);
          var errorMsg = 'Terjadi kesalahan pada server';
          try {
            var response = JSON.parse(xhr.responseText);
            errorMsg = response.message || errorMsg;
          } catch (e) {
            errorMsg = 'Error: ' + xhr.status + ' ' + xhr.statusText;
          }
          $.growl.error({ title: 'Error', message: errorMsg });
        }
      });
    });

    // Keyboard shortcut: Enter to submit
    $('#formSetorManual').on('keypress', function(e) {
      if (e.which === 13) { // Enter key
        e.preventDefault();
        $('#btnSimpanSetor').click();
      }
    });
  </script>
  <!-- END Script Setor Manual -->


</body>

</html>