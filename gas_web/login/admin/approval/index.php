<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

  <?php include "../dashboard/head.php"; ?>

  <body>
    
    <?php include "../dashboard/icon.php"; ?>
    
    <?php include "../dashboard/header.php"; ?>

    <?php       
    // Koneksi sudah ada dari head.php
    include "../../koneksi/fungsi_indotgl.php";
    include "../../koneksi/fungsi_waktu.php";
    // Simple mode: fixed two tabs (Pinjaman Biasa / Pinjaman Kredit)
    // Use ?type=kredit to open the kredit tab; default is 'biasa'.
    $current_type = (isset($_GET['type']) && strtolower($_GET['type']) === 'kredit') ? 'kredit' : 'biasa';
    $heading = 'Approval Pinjaman';
    $approvalError = null; // kept for compatibility with existing markup

    // No server-side schema discovery here — the frontend will request data via AJAX with ?type=biasa|kredit
    ?>

    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div class="btn-toolbar mb-2 mb-md-0"></div>
          </div>

          <style>
          /* Page-specific styles for approval UI */
          .filter-pills { display:inline-flex; padding:6px; background:#f6f7f9; border-radius:8px; }
          .filter-pill { border:0; padding:6px 12px; border-radius:6px; background:transparent; color:#333; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:8px; }
          .filter-pill.active { background:#fff; box-shadow:0 1px 2px rgba(0,0,0,0.06); }

          /* Allow dropdowns to escape the scroll container and sit above pagination */
          .table-responsive { overflow: visible; }

          /* Table cosmetics: tighter spacing, vertical centering, hover and numeric alignment */
          .card .card-header { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; }
          #approval_table th, #approval_table td { vertical-align: middle; padding: .65rem .75rem; }
          #approval_table thead th { font-weight:600; }
          #approval_table tbody tr:hover { background: #fafafa; }
          /* Numeric columns use `.text-end` from column definitions for alignment; remove brittle nth-child selectors */
          /* Example: columns that are numbers are defined with `className: 'text-end'` in `columnsFor` */
          /* Ensure action column is centered like the Tabungan Masuk page */
          #approval_table td:last-child { text-align: center; }

          .action-wrapper { position:relative; display:inline-block; }
          /* Make the toggle button look clean and simple */
          .action-toggle { 
            border:0; 
            background:transparent; 
            font-size:20px; 
            cursor:pointer; 
            color:#666; 
            padding:4px 8px; 
            border-radius:4px;
            transition: all 0.2s ease;
          }
          .action-toggle:hover { 
            background:#f0f0f0; 
            color:#333;
          }
          
          /* Modal-style action dropdown positioned fixed for better UX */
          .action-dropdown { 
            position:fixed;
            background:#fff; 
            border:1px solid #ddd; 
            border-radius:8px; 
            box-shadow:0 10px 40px rgba(0,0,0,0.12); 
            z-index:9999; 
            padding:8px;
            min-width:140px;
            display:none;
          }
          .action-dropdown.visible { 
            display:block !important; 
          }
          .action-dropdown .dropdown-item { 
            display:block; 
            width:100%; 
            padding:10px 12px; 
            border:0; 
            text-align:left; 
            background:transparent; 
            cursor:pointer; 
            border-radius:6px; 
            font-weight:500;
            font-size:14px;
            transition: background 0.15s ease;
          }
          .action-dropdown .dropdown-item:hover { 
            background:#f5f5f5; 
          }
          .action-dropdown .btn-approve { 
            color:#fff; 
            background:#28a745;
            margin-bottom:4px;
          }
          .action-dropdown .btn-approve:hover { 
            background:#218838; 
          }
          .action-dropdown .btn-reject { 
            color:#fff; 
            background:#dc3545;
          }
          .action-dropdown .btn-reject:hover { 
            background:#c82333; 
          }

          /* Approval table visuals - match Pencairan Tabungan styles */
          #approval_table {
            font-size: 0.88rem;
            table-layout: auto;
            width: 100%;
          }
          #approval_table th {
            padding: 10px 8px;
            white-space: nowrap;
            font-weight: 700;
            background-color: #f6f7fb;
            border: 1px solid #e8e9ed;
            color: #344054;
            font-size: 0.9rem;
            text-align: center !important;
          }
          #approval_table thead th.sorting:after,
          #approval_table thead th.sorting_asc:after,
          #approval_table thead th.sorting_desc:after,
          #approval_table thead th.sorting:before,
          #approval_table thead th.sorting_asc:before,
          #approval_table thead th.sorting_desc:before,
          #approval_table thead th:after,
          #approval_table thead th:before {
            display: none !important;
            content: none !important;
            background-image: none !important;
          }

          #approval_table td {
            padding: 10px 8px;
            vertical-align: middle;
            overflow: hidden;
            border: 1px solid #eef0f4;
            color: #222;
            white-space: nowrap;
            text-overflow: ellipsis;
          }

          .table-responsive { overflow-x: auto; max-width: 100%; }

          #approval_table tbody tr:hover { background: #fbfcff; }

          /* Small pills style to match Pencairan Tabungan */
          .status-pill { padding: .35rem .5rem; font-size: .78rem; border-radius: 6px; margin: 2px; }

          </style>

          <!-- ISI HALAMAN -->
          <div class="row row-sm">
              <div class="col-lg-12">
                  <div class="card">
                      <div class="card-header" align="center">
                          <h5 class="mb-0"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h5>
                      </div>
                      <div class="card-body">
                          <?php if ($approvalError): ?>
                            <div class="alert alert-warning mb-0">
                              <?php echo htmlspecialchars($approvalError, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                          <?php else: ?>

                            <!-- Filters (positioned like Pencairan Tabungan) -->
                            <div class="mb-3">
                              <div class="d-flex align-items-center gap-3">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Filter status">
                                  <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="pending">Menunggu</button>
                                  <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="approved">Disetujui</button>
                                  <button type="button" class="btn btn-sm status-pill btn-outline-secondary" data-status="rejected">Ditolak</button>
                                  <button type="button" class="btn btn-sm status-pill btn-outline-secondary active" data-status="all">Semua</button>
                                </div>
                                <div class="d-flex gap-2">
                                  <button type="button" class="btn btn-sm btn-outline-secondary table-option <?php echo ($current_type === 'biasa' ? 'active' : ''); ?>" data-type="biasa">Pinjaman Biasa</button>
                                  <button type="button" class="btn btn-sm btn-outline-secondary table-option <?php echo ($current_type === 'kredit' ? 'active' : ''); ?>" data-type="kredit">Pinjaman Kredit</button>
                                </div>
                              </div>
                            </div>

                            <div class="table-responsive">
                              <table class="table table-bordered table-hover mb-0 text-nowrap border-bottom w-100" id="approval_table">
                                  <thead>
                                          <!-- The table header will be populated by DataTables dynamically based on the active tab -->
                                      <tr></tr>
                                  </thead>
                                  <tbody>
                                      <!-- Data populated via AJAX DataTable. -->
                                  </tbody>
                              </table>
                            </div>
                          <?php endif; ?>
                      </div>
                  </div>
              </div>
          </div>

        </main>

      </div>
    </div>

    <!-- Detail modal (loaded via AJAX) -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Detail Pengajuan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <div class="text-center text-muted">Memuat...</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Approve Modal -->
    <div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Konfirmasi Persetujuan</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <p>Apakah Anda yakin menyetujui pinjaman ini?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-success" id="confirmApproveBtn">Terima</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirm Reject Modal (compact) -->
    <div class="modal fade" id="confirmRejectModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content compact-reject">
          <div class="modal-header">
            <h6 class="modal-title">Konfirmasi Penolakan</h6>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
          </div>
          <div class="modal-body">
            <p class="mb-2">Masukkan alasan penolakan <strong>(wajib)</strong>:</p>
            <textarea id="rejectReason" class="form-control form-control-sm" rows="2" placeholder="Alasan penolakan..."></textarea>
            <div id="rejectReasonFeedback" class="invalid-feedback d-none">Alasan penolakan wajib diisi.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-danger btn-sm" id="confirmRejectBtn">Tolak</button>
          </div>
        </div>
      </div>
    </div>

    <style>
    /* Compact reject modal tweaks */
    .modal-content.compact-reject { padding: 6px; border-radius: 8px; }
    .modal-dialog.modal-sm { max-width: 420px; }
    </style>

    <?php include "../dashboard/js.php"; ?>


    <!-- DATA TABLE JS-->
    <script src="../../../assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
    <script src="../../../assets/plugins/datatable/js/dataTables.bootstrap5.js"></script>
    <script src="../../../assets/plugins/datatable/dataTables.responsive.min.js"></script>

    <script>
    // Dynamic DataTable (AJAX source) with tab switching
    var approvalTable = null;
    // Start with the server-provided current type (biasa or kredit)
    var activeType = '<?php echo $current_type; ?>';
    // Current status filter (all|pending|approved|rejected)
    var CURRENT_STATUS = 'all';

    // Format helpers
    function formatRupiah(value){
        if (value === null || value === undefined || value === '') return '-';
        var s = String(value).trim();
        // Remove any currency symbols or surrounding text, keep digits and separators
        s = s.replace(/[^0-9\-,\.]/g, '');
        // If both '.' and ',' present, assume '.' is thousand separator and ',' is decimal
        if (s.indexOf('.') !== -1 && s.indexOf(',') !== -1) {
            s = s.replace(/\./g, '');
            s = s.replace(/,/g, '.');
        } else if (s.indexOf('.') !== -1) {
            // If the last group after '.' has exactly 3 digits, treat '.' as thousand separator
            var parts = s.split('.');
            if (parts.length > 1 && parts[parts.length-1].length === 3) {
                s = s.replace(/\./g, '');
            }
            // otherwise leave '.' as decimal separator
        } else if (s.indexOf(',') !== -1) {
            // Treat ',' as decimal separator
            s = s.replace(/,/g, '.');
        }
        var n = Number(s) || 0;
        return 'Rp ' + n.toLocaleString('id-ID');
    }
    function formatDateIndo(dateString){
        // Preferred output: DD/MM/YYYY HH:MM:SS WIB
        if (!dateString) return '-';
        var s = String(dateString).trim();
        // Attempt to parse common formats: ISO (YYYY-MM-DD HH:MM:SS) or MySQL
        var d = null;
        // ISO or space-separated
        var iso = s.replace(' ', 'T');
        d = new Date(iso);
        if (isNaN(d.getTime())) {
            // Try parsing as YYYY-MM-DD HH:MM:SS manually
            var parts = s.split(' ');
            var datePart = parts[0] || '';
            var timePart = parts[1] || '00:00:00';
            var dp = datePart.split('-');
            var tp = timePart.split(':');
            if (dp.length === 3) {
                d = new Date(dp[0], dp[1]-1, dp[2], parseInt(tp[0]||0,10), parseInt(tp[1]||0,10), parseInt(tp[2]||0,10));
            }
        }
        if (!d || isNaN(d.getTime())) return s;
        function pad(n){ return ('0'+n).slice(-2); }
        var day = pad(d.getDate());
        var month = pad(d.getMonth()+1);
        var year = d.getFullYear();
        var hh = pad(d.getHours());
        var mm = pad(d.getMinutes());
        var ss = pad(d.getSeconds());
        return day + '/' + month + '/' + year + ' ' + hh + ':' + mm + ':' + ss + ' WIB';
    }

    function columnsFor(type) {
        if (type === 'kredit') {
            // Slimmed columns for quick approval scanning: remove Pokok, Cicilan/Bulan, Tenor to keep row compact
            return [
                { data: null, title: 'No', orderable:false },
                { data: 'date', title: 'Tanggal Pengajuan', render: function(d){ return formatDateIndo(d); } },
                { data: 'name', title: 'Nama Anggota' },
                { data: 'nama_barang', title: 'Nama Barang' },
                { data: 'harga', title: 'Harga', className: 'text-end', render: function(d){ return formatRupiah(d); } },
                { data: 'dp', title: 'DP', className: 'text-end', render: function(d){ return formatRupiah(d); } },
                { data: 'total_bayar', title: 'Total Bayar', className: 'text-end', render: function(d){ return formatRupiah(d); } },
                { data: 'status', title: 'Status', render: function(d){ var s = (d||'pending'); var map = { 'pending':'bg-warning', 'approved':'bg-success', 'rejected':'bg-danger' }; var labelMap = { 'pending':'Menunggu', 'approved':'Disetujui', 'rejected':'Ditolak' }; var label = labelMap[s] || (s.charAt(0).toUpperCase()+s.slice(1)); return '<span class="badge '+(map[s]||'bg-secondary')+'">'+label+'</span>'; } },
                { data: 'actions', title: 'Aksi', orderable:false, searchable:false, className: 'text-center' }
            ];
        }
        // default = biasa. Keep Tenor in the list; move Tujuan to Detail
        return [
            { data: null, title: 'No', orderable:false },
            { data: 'date', title: 'Tanggal', render: function(d){ return formatDateIndo(d); } },
            { data: 'name', title: 'Nama Anggota' },
            { data: 'amount', title: 'Jumlah Pinjaman', className: 'text-end', render: function(d){ return formatRupiah(d); } },
            { data: 'tenor', title: 'Tenor', className: 'text-center', render: function(d){ return (d && parseInt(d) > 0) ? (parseInt(d) + ' bulan') : '-'; } },
            { data: 'status', title: 'Status', render: function(d, t, r){ var s = (d||'pending'); var map = { 'pending':'bg-warning', 'approved':'bg-success', 'rejected':'bg-danger' }; var labelMap = { 'pending':'Menunggu', 'approved':'Disetujui', 'rejected':'Ditolak' }; var label = labelMap[s] || (s.charAt(0).toUpperCase()+s.slice(1)); return '<span class="badge '+(map[s]||'bg-secondary')+'">'+label+'</span>'; } },
            { data: 'actions', title: 'Aksi', orderable:false, searchable:false, className: 'text-center', render: function(d){ return d || ''; } }
        ];
    }

    function initApprovalTable(type) {
        // destroy existing
        if (approvalTable) {
            approvalTable.clear().destroy();
            $('#approval_table').empty();
            $('#approval_table').append('<thead></thead><tbody></tbody>');
        }

        var cols = columnsFor(type);
        // build thead
        var $thead = $('#approval_table thead');
        var tr = '<tr>' + cols.map(function(c){ return '<th>' + (c.title||'') + '</th>'; }).join('') + '</tr>';
        $thead.html(tr);

        approvalTable = $('#approval_table').DataTable({
            ajax: {
                url: '../../../api/approval_pinjaman.php',
                type: 'GET',
                data: function(d){ d.type = type; d.status = CURRENT_STATUS; },
                dataSrc: function(json) {
                    // defensive: ensure valid json object with data array
                    if (!json || typeof json !== 'object') {
                        console.error('Invalid JSON response from server', json);
                        alert('Kesalahan server: respon tidak valid. Periksa console untuk respons mentah.');
                        return [];
                    }
                    if (json.error) {
                        console.warn('API returned error:', json.error);
                        // show a friendly alert and log raw response
                        alert('Server: ' + json.error + '\n(cek console untuk detail)');
                        return json.data || [];
                    }
                    return json.data || [];
                }
            },
            columns: cols,
            // Always order by date ascending (oldest -> newest)
            order: [[1, 'asc']],
            pageLength: 10,
            lengthChange: false,
            searching: false,
            responsive: true,
            autoWidth: false,
            language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
            createdRow: function(row, data, dataIndex){
                // serial number (stable across pages)
                var start = 0;
                try { var pinfo = approvalTable.page.info(); start = pinfo.start || 0; } catch(e) { start = 0; }
                $('td:eq(0)', row).html(start + dataIndex + 1);

                // Build action cell as an ellipsis menu for all rows. "Detail" is always present; "Setujui/Tolak" only when status == 'pending'.
                var $actionCell = $('td:last', row);
                var id = data.id;
                var status = String(data.status||'').trim().toLowerCase();
                var actionHtml = '<div class="action-wrapper">'
                    + '<button class="action-toggle" data-id="'+id+'" title="Opsi aksi" role="button" tabindex="0">&#x22EE;</button>'
                    + '<div class="action-dropdown">'
                    + '<button class="dropdown-item btn-detail" data-id="'+id+'">📄 Detail</button>';
                if (status === 'pending') {
                    actionHtml += '<button class="dropdown-item btn-approve" data-id="'+id+'">✓ Terima</button>'
                               + '<button class="dropdown-item btn-reject" data-id="'+id+'">✗ Tolak</button>';
                }
                actionHtml += '</div></div>';
                $actionCell.html(actionHtml);
            },
            error: function (xhr, error, thrown) {
                console.error('DataTables error', error, thrown);
            }
        });

        // sync visual status pills with current filter
        $('.status-pill').removeClass('active');
        $('.status-pill[data-status="' + CURRENT_STATUS + '"]').addClass('active');

        // set activeType for performAction
        activeType = type;
    }

    $(document).ready(function(){
        // Initialize on load
        initApprovalTable(activeType);

        // Global AJAX error handler for easier debugging (shows raw response in console)
        $(document).ajaxError(function(event, jqXHR, ajaxSettings, thrownError) {
            if (ajaxSettings && ajaxSettings.url && ajaxSettings.url.indexOf('approval_pinjaman.php') !== -1) {
                console.error('AJAX failed for approval_pinjaman.php. Response text:', jqXHR && jqXHR.responseText ? jqXHR.responseText : '(no response)');
                alert('Terjadi kesalahan server saat memuat data approval. Periksa console (F12) untuk respon lengkap.');
            }
        });

        // Replace DataTables default error handler to avoid the modal and instead show console + alert
        if ($.fn && $.fn.dataTable && $.fn.dataTable.ext) {
            $.fn.dataTable.ext.errMode = function ( settings, helpPage, message ) {
                console.error('DataTables error: ', message);
                alert('Terjadi kesalahan saat memuat tabel. Periksa console untuk detail.');
            };
        }

        // Tab click - switch view without reloading (buttons have data-type=biasa|kredit)
        $('.table-option').on('click', function(){
            var type = $(this).data('type') || 'biasa';
            $('.table-option').removeClass('active');
            $(this).addClass('active');
            initApprovalTable(type);
        });

        // status pill click (server-side filter via status param)
        $(document).on('click', '.status-pill', function(e){
            e.preventDefault();
            $('.status-pill').removeClass('active');
            $(this).addClass('active');
            CURRENT_STATUS = $(this).data('status') || 'all';
            if (approvalTable) approvalTable.ajax.reload(null, false);
        });

        // action menu handlers (ellipsis menu open/close) - robust floating modal
        $(document).on('click touchstart', '.action-toggle', function(e){
            try {
                e.preventDefault();
                e.stopPropagation();
                var $btn = $(this);
                var id = $btn.attr('data-id') || $btn.data('id');
                console.debug('action-toggle clicked', id, $btn.length);

                // Hide other visible dropdowns
                $('.action-dropdown.visible').removeClass('visible');

                // Find dropdown in the same wrapper; fall back to global search by id if it was moved to body
                var $wrapper = $btn.closest('.action-wrapper');
                var $dropdown = $wrapper.find('.action-dropdown');
                if ($dropdown.length === 0) {
                    $dropdown = $('#action-dropdown-' + id);
                }

                // If still missing, create a fallback dropdown dynamically
                if ($dropdown.length === 0) {
                    console.warn('action-dropdown missing for id', id, '— creating fallback');

                    // Attempt to find the row data to check status; default to non-pending
                    var rowData = null;
                    try {
                        var rr = approvalTable.row(function(idx, d, node){ return String(d.id) === String(id); });
                        if (rr && typeof rr.data === 'function') rowData = rr.data();
                    } catch(e) { rowData = null; }
                    var isPending = (rowData && String(rowData.status||'').toLowerCase() === 'pending');

                    $dropdown = $('<div class="action-dropdown" id="action-dropdown-' + id + '">');
                    $dropdown.append('<button class="dropdown-item btn-detail" data-id="' + id + '">📄 Detail</button>');
                    if (isPending) {
                        $dropdown.append('<button class="dropdown-item btn-approve" data-id="' + id + '">✓ Terima</button>');
                        $dropdown.append('<button class="dropdown-item btn-reject" data-id="' + id + '">✗ Tolak</button>');
                    }
                    $dropdown.appendTo('body');
                }

                // ensure accessible attributes
                $btn.attr('aria-controls', 'action-dropdown-' + id);
                $dropdown.attr('id', 'action-dropdown-' + id);

                var isVisible = $dropdown.hasClass('visible');
                if (isVisible) {
                    $dropdown.removeClass('visible');
                    $btn.attr('aria-expanded', 'false');
                    return;
                }

                // Append to body to avoid overflow/scroll clipping and compute viewport-safe position
                $dropdown.appendTo('body');
                var offset = $btn.offset();
                var btnHeight = $btn.outerHeight();
                var ddWidth = $dropdown.outerWidth();
                var ddHeight = $dropdown.outerHeight();
                var winW = $(window).width();
                var winH = $(window).height();
                var top = offset.top + btnHeight + 8;
                var left = offset.left - Math.round((ddWidth - $btn.outerWidth()) / 2);

                // Clamp within viewport with 8px padding
                if (left < 8) left = 8;
                if (left + ddWidth > winW - 8) left = winW - ddWidth - 8;
                if (top + ddHeight > $(document).scrollTop() + winH - 8) {
                    // open above button instead
                    top = offset.top - ddHeight - 8;
                }

                $dropdown.css({ top: top + 'px', left: left + 'px', 'pointer-events': 'auto' }).addClass('visible');
                $btn.attr('aria-expanded', 'true');
            } catch (ex) {
                console.error('error in action-toggle handler', ex);
            }
        });

        // Hide dropdowns when clicking elsewhere or pressing Escape
        $(document).on('click', function(e){
            if (!$(e.target).closest('.action-dropdown, .action-toggle').length) {
                $('.action-dropdown.visible').removeClass('visible');
                $('.action-toggle[aria-expanded="true"]').attr('aria-expanded', 'false');
            }
        });
        $(document).on('keydown', function(e){ if (e.key === 'Escape') { $('.action-dropdown.visible').removeClass('visible'); $('.action-toggle[aria-expanded="true"]').attr('aria-expanded', 'false'); } });

        // Utility: hide all modals and remove backdrops (robust cleanup to avoid stuck dim screens)
        function hideAllModals() {
            try {
                // hide Bootstrap modals instances if present
                ['detailModal','confirmApproveModal','confirmRejectModal'].forEach(function(id){
                    var el = document.getElementById(id);
                    if (!el) return;
                    try { var m = bootstrap.Modal.getInstance(el); if (m) { m.hide(); try { m.dispose(); } catch(e) {} } } catch(e) { /* ignore */ }
                    // force remove show class and clear content
                    el.classList.remove('show');
                    try { $(el).find('.modal-body').html(''); } catch(e) {}
                });
                // remove any modal backdrops
                $('.modal-backdrop').remove();
                // remove modal-open class on body and clear any body padding Bootstrap may have set
                $('body').removeClass('modal-open').css('padding-right','');
                // reset aria-expanded on action toggles
                $('.action-toggle[aria-expanded="true"]').attr('aria-expanded', 'false');
                // hide floating action dropdowns
                $('.action-dropdown.visible').removeClass('visible');
            } catch (ex) {
                console.error('hideAllModals error', ex);
            }
        }

        // support keyboard activation for accessibility (Enter / Space)
        $(document).on('keydown', '.action-toggle', function(e){
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); }
        });

        // Also hide on window scroll/resize or table redraw to avoid stale dropdowns
        $(window).on('scroll resize', function(){ $('.action-dropdown.visible').removeClass('visible'); $('.action-toggle[aria-expanded="true"]').attr('aria-expanded', 'false'); });
        $('#approval_table').on('draw.dt', function(){ $('.action-dropdown.visible').removeClass('visible'); $('.action-toggle[aria-expanded="true"]').attr('aria-expanded', 'false');
            // Remove any dropdowns that are no longer attached to a row to avoid confusion
            $('.action-dropdown').each(function(){ var $d = $(this); var id = $d.attr('id'); if (id && $('#'+id).length && $('#'+id).closest('body').length) { /* ok */ } else { $d.remove(); } });
        });

        // Approve/Reject from dropdown or inline buttons -> show confirm modals
        function isRowPending(id) {
            try {
                var rr = approvalTable.row(function(idx, d, node){ return String(d.id) === String(id); });
                if (rr && typeof rr.data === 'function') {
                    var rd = rr.data();
                    return String(rd.status||'').toLowerCase() === 'pending';
                }
            } catch(e){ }
            return false;
        }

        $(document).on('click', '.action-dropdown .btn-approve, .btn-approve', function(){
            var id = $(this).data('id');
            if (!isRowPending(id)) { alert('Aksi hanya dapat dilakukan pada pengajuan dengan status pending.'); return; }
            showApproveModal(id);
        });
        $(document).on('click', '.action-dropdown .btn-reject, .btn-reject', function(){
            var id = $(this).data('id');
            if (!isRowPending(id)) { alert('Aksi hanya dapat dilakukan pada pengajuan dengan status pending.'); return; }
            showRejectModal(id);
        });

        // Load detail modal (single robust handler used by both inline buttons and dropdown items)
        function loadDetailModal(id){
            if (!id) { alert('ID tidak valid'); return; }
            $('#detailModal .modal-body').html('<div class="text-center text-muted">Memuat...</div>');
            var modalEl = new bootstrap.Modal(document.getElementById('detailModal'));
            modalEl.show();

            var detailUrl = (activeType === 'kredit') ? '/gas/gas_web/login/admin/pinjaman_kredit/detail.php' : '/gas/gas_web/login/admin/pinjaman_biasa/detail.php';
            $.ajax({
                url: detailUrl,
                method: 'GET',
                data: {id:id, ajax:1},
                dataType: 'html',
                timeout: 15000,
                success: function(html){
                    // If server returned a full HTML page (e.g. redirect to login), try to detect and fallback
                    var lower = (html || '').toLowerCase();
                    if (lower.indexOf('<!doctype') !== -1 || lower.indexOf('<html') !== -1 || (lower.indexOf('login') !== -1 && lower.indexOf('password') !== -1)) {
                        console.warn('Detail returned full page, falling back to opening detail in a new tab');
                        $('#detailModal .modal-body').html('<div class="text-danger">Respon detail tidak valid. Membuka halaman detail di tab baru...</div>');
                        // open in new tab as fallback
                        window.open(detailUrl + '?id='+encodeURIComponent(id), '_blank');
                        return;
                    }
                    $('#detailModal .modal-body').html(html);
                },
                error: function(jqxhr, status, err){
                    console.error('Detail load failed', status, err, jqxhr && jqxhr.responseText ? jqxhr.responseText.substr(0,500) : '');
                    $('#detailModal .modal-body').html('<div class="text-danger">Gagal memuat detail. Coba buka di tab baru.</div>');
                }
            });
        }

        // Catch clicks on any .btn-detail (works both for dropdown items and inline buttons returned by the API)
        $(document).on('click', '.btn-detail', function(e){
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var id = $btn.data('id') || $btn.attr('data-id');
            if (!id) return;
            // close any floating dropdowns first
            $('.action-dropdown.visible').removeClass('visible');
            $('.action-toggle[aria-expanded="true"]').attr('aria-expanded', 'false');
            loadDetailModal(id);
        });

        // Provide adminAction wrapper used by detail partials to call the same modal-based flow
        function adminAction(id, action){
            if (action === 'reject'){
                showRejectModal(id);
            } else {
                showApproveModal(id);
            }
            // close detail modal if open
            try { var m = bootstrap.Modal.getInstance(document.getElementById('detailModal')); if (m) m.hide(); } catch(e){}
        }

        // Modal helpers
        function showApproveModal(id){
            // ensure any floating dropdowns are closed before showing modal
            $('.action-dropdown.visible').removeClass('visible');
            $('.action-toggle[aria-expanded="true"]').attr('aria-expanded','false');

            window._pendingActionId = id;
            window._pendingActionKind = 'approve';
            var modalEl = new bootstrap.Modal(document.getElementById('confirmApproveModal'));
            modalEl.show();
        }
        function showRejectModal(id){
            // ensure any floating dropdowns are closed before showing modal
            $('.action-dropdown.visible').removeClass('visible');
            $('.action-toggle[aria-expanded="true"]').attr('aria-expanded','false');

            window._pendingActionId = id;
            window._pendingActionKind = 'reject';
            // reset textarea and feedback
            $('#rejectReason').val('');
            $('#rejectReason').removeClass('is-invalid');
            $('#rejectReasonFeedback').addClass('d-none');

            // disable confirm until input provided
            $('#confirmRejectBtn').prop('disabled', true);
            // attach a namespaced input handler to toggle the button
            $('#rejectReason').off('.rejectValidation').on('input.rejectValidation', function(){
                var v = ($(this).val() || '').trim();
                $('#confirmRejectBtn').prop('disabled', v === '');
                if (v !== '') { $(this).removeClass('is-invalid'); $('#rejectReasonFeedback').addClass('d-none'); }
            });

            var modalEl = new bootstrap.Modal(document.getElementById('confirmRejectModal'));
            modalEl.show();
            // focus textarea for quick input in compact modal
            setTimeout(function(){ try { $('#rejectReason').focus(); } catch(e){} }, 250);
        }

        // Confirm modal button handlers
        $('#confirmApproveBtn').on('click', function(){
            if (!window._pendingActionId) return; 
            performActionAjax(window._pendingActionId, 'approve', null);
        });
        $('#confirmRejectBtn').on('click', function(){
            if (!window._pendingActionId) return;
            var reason = ($('#rejectReason').val() || '').trim();
            if (reason === '') {
                // show inline validation
                $('#rejectReason').addClass('is-invalid');
                $('#rejectReasonFeedback').removeClass('d-none');
                $('#rejectReason').focus();
                return;
            }
            performActionAjax(window._pendingActionId, 'reject', reason);
        });

        // Ensure that when any of the modals are hidden we clean up stray backdrops and floating dropdowns
        $(document).on('hidden.bs.modal', '#confirmRejectModal, #confirmApproveModal, #detailModal', function(){
            // run shortly after Bootstrap finishes its own cleanup
            setTimeout(function(){
                try {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('padding-right','');
                    $('.action-dropdown.visible').removeClass('visible');
                    $('.action-toggle[aria-expanded="true"]').attr('aria-expanded','false');
                    // extra cleanup for reject modal
                    try { $('#rejectReason').off('.rejectValidation'); } catch(e) {}
                    try { $('#confirmRejectBtn').prop('disabled', false).text('Tolak'); } catch(e) {}
                    try { $('#rejectReason').removeClass('is-invalid'); $('#rejectReasonFeedback').addClass('d-none'); } catch(e) {}                } catch(e) { console.error('modal hidden cleanup failed', e); }
            }, 10);
        });

        // Also hide dropdowns when a modal is about to show to avoid focus/overlay conflicts
        $(document).on('show.bs.modal', '#confirmRejectModal, #confirmApproveModal, #detailModal', function(){
            $('.action-dropdown.visible').removeClass('visible');
            $('.action-toggle[aria-expanded="true"]').attr('aria-expanded','false');
        });

        function adminEvent(id, event){
            var note = prompt('Tambah catatan (opsional):');
            if (!confirm('Yakin menerapkan event "' + event + '"?')) return;
            $.post('/gas/gas_web/api/pinjaman_kredit/admin_event.php', {id:id, event:event, note: note}, function(data){
                if (data && data.status) { alert('Sukses: ' + (data.message||'')); if (approvalTable) approvalTable.ajax.reload(null, false); } else { alert('Error: ' + (data && data.message)); }
            }, 'json');
        }



        // Switching between pinjaman types
        $('.table-option').on('click', function(){
            var type = $(this).data('type') || 'biasa';
            $('.table-option').removeClass('active');
            $(this).addClass('active');
            initApprovalTable(type);
        });

        // handle approve/reject click coming from DataTable action cells -> use modal flow
        $(document).on('click', '.btn-approve', function(){
            var id = $(this).data('id');
            showApproveModal(id);
        });
        $(document).on('click', '.btn-reject', function(){
            var id = $(this).data('id');
            showRejectModal(id);
        });
    });

    var currentTable = function(){
        return (activeType === 'kredit') ? 'pinjaman_kredit' : 'pinjaman_biasa';
    };

    // New AJAX action with modal confirmations and in-place row update
    function performActionAjax(id, action, reason) {
        // If we're handling pinjaman_kredit, use its dedicated admin API
        if (currentTable() === 'pinjaman_kredit') {
            $('#confirmApproveBtn, #confirmRejectBtn').prop('disabled', true).text('Mohon tunggu...');
            var note = $('#adminNote').length ? ($('#adminNote').val() || '') : '';
            var payload = { id: id, action: action, reason: reason || '', note: note };
            $.ajax({
                url: '/gas/gas_web/api/pinjaman_kredit/admin_action.php',
                method: 'POST',
                data: payload,
                dataType: 'json',
                timeout: 15000,
                success: function(data){
                    $('#confirmApproveBtn').prop('disabled', false).text('Terima');
                    $('#confirmRejectBtn').prop('disabled', false).text('Tolak');
                    if (!data || typeof data !== 'object') { alert('Respon server tidak valid. Periksa console.'); console.error('Invalid response', data); return; }
                    console.debug('performActionAjax response', data);
                    var ok = !!data.status;
                    // Ensure modal and backdrops are cleaned up BEFORE showing alerts to avoid a blocked UI
                    try { hideAllModals(); } catch(e) { console.error('hideAllModals error', e); }
                    // Extra defensive cleanup in case Bootstrap left inline padding or extra backdrops
                    try { $('body').css('padding-right',''); $('.modal-backdrop').remove(); setTimeout(function(){ $('.modal-backdrop').remove(); $('body').removeClass('modal-open').css('padding-right',''); }, 200); } catch(e) {}

                    // Explicitly ensure the confirmRejectModal is closed (extra measure)
                    try { var m = bootstrap.Modal.getInstance(document.getElementById('confirmRejectModal')); if (m) { m.hide(); try{ m.dispose(); } catch(e){} } } catch(e) {}
                    try { $('.modal-backdrop').remove(); $('body').removeClass('modal-open').css('padding-right',''); } catch(e) {}

                    if (ok) {
                        // update the row in-place and then refresh data from server to ensure consistency
                        updateRowAfterAction(id, action);
                        if (approvalTable && approvalTable.ajax) approvalTable.ajax.reload(null, false);
                        var humanMsg = data.message || (action === 'approve' ? 'Pengajuan disetujui' : (action === 'reject' ? 'Pengajuan ditolak' : 'Tindakan diterapkan'));
                        alert(humanMsg);
                    } else {
                        // show server message and keep console log for debugging
                        console.warn('Action failed on server', data);
                        alert('Error: ' + (data.message || 'Tidak diketahui'));
                    }
                },
                error: function(jqxhr, status, err){
                    $('#confirmApproveBtn').prop('disabled', false).text('Terima');
                    $('#confirmRejectBtn').prop('disabled', false).text('Tolak');
                    var resp = jqxhr && jqxhr.responseText ? jqxhr.responseText : '';
                    console.error('AJAX fail', status, err, resp);
                    var msg = 'Terjadi kesalahan saat menghubungi server.';
                    // attempt to parse server JSON or HTML error message
                    try {
                        var parsed = JSON.parse(resp);
                        if (parsed && parsed.message) msg = 'Server: ' + parsed.message;
                    } catch(e) {
                        if (resp) msg += '\nRespon server: ' + (resp.length > 300 ? resp.substr(0,300) + '...' : resp);
                    }
                    // ensure cleanup even on error
                    try { hideAllModals(); } catch(e) { console.error('hideAllModals error', e); }
                    alert(msg);
                }
            });
            return;
        }

        // default: generic approve_process flow for other pending tables
        var note = $('#adminNote').length ? ($('#adminNote').val() || '') : '';
        var payload = { id_pending: id, action: action, reason: reason || null, note: note, table: currentTable() };
        // disable modal buttons to prevent double submits
        $('#confirmApproveBtn, #confirmRejectBtn').prop('disabled', true).text('Mohon tunggu...');
        $.post('approve_process.php', payload, function(data){
            $('#confirmApproveBtn').prop('disabled', false).text('Terima');
            $('#confirmRejectBtn').prop('disabled', false).text('Tolak');
            console.debug('approve_process response', data);
            if (!data || typeof data !== 'object') {
                // defensive cleanup first to avoid stuck backdrop
                try { hideAllModals(); } catch(e) { console.error('hideAllModals error', e); }
                try { $('body').css('padding-right',''); $('.modal-backdrop').remove(); setTimeout(function(){ $('.modal-backdrop').remove(); $('body').removeClass('modal-open').css('padding-right',''); }, 200); } catch(e) {}
                alert('Respon server tidak valid. Periksa console.'); console.error('Invalid response', data); return;
            }
            var ok = (typeof data.status !== 'undefined') ? data.status : (data.success ? true : false);
            // Ensure cleanup BEFORE showing alerts so the page is interactive when the user dismisses alerts
            try { hideAllModals(); } catch(e) { console.error('hideAllModals error', e); }
            try { $('body').css('padding-right',''); $('.modal-backdrop').remove(); setTimeout(function(){ $('.modal-backdrop').remove(); $('body').removeClass('modal-open').css('padding-right',''); }, 200); } catch(e) {}

            if (ok) {
                // Ensure confirmRejectModal is closed before showing the alert
                try { var m = bootstrap.Modal.getInstance(document.getElementById('confirmRejectModal')); if (m) { m.hide(); try{ m.dispose(); } catch(e){} } } catch(e) {}
                try { $('.modal-backdrop').remove(); $('body').removeClass('modal-open').css('padding-right',''); } catch(e) {}

                // Update row in place and reload to make sure server-side fields are authoritative
                updateRowAfterAction(id, action);
                if (approvalTable && approvalTable.ajax) approvalTable.ajax.reload(null, false);
                var humanMsg = data.message || (action === 'approve' ? 'Pengajuan disetujui' : (action === 'reject' ? 'Pengajuan ditolak' : 'Tindakan diterapkan'));
                alert(humanMsg);
            } else {
                console.warn('approve_process reported failure', data);
                alert('Error: ' + (data.message || 'Tidak diketahui'));
            }
        }, 'json').fail(function(jqxhr, status, err){
            $('#confirmApproveBtn').prop('disabled', false).text('Terima');
            $('#confirmRejectBtn').prop('disabled', false).text('Tolak');
            console.error('AJAX fail', status, err, jqxhr.responseText);
            // cleanup before user dismisses alert to avoid stuck UI
            try { hideAllModals(); } catch(e) { console.error('hideAllModals error', e); }
            try { $('body').css('padding-right',''); $('.modal-backdrop').remove(); setTimeout(function(){ $('.modal-backdrop').remove(); $('body').removeClass('modal-open').css('padding-right',''); }, 200); } catch(e) {}
            alert('Terjadi kesalahan saat menghubungi server. Periksa console untuk detail.');
        });
    }

    function updateRowAfterAction(id, action) {
        var newStatus = (action === 'approve') ? 'approved' : 'rejected';
        var found = false;
        approvalTable.rows().every(function(idx, tableLoop, rowLoop){
            var d = this.data();
            if (String(d.id) === String(id)) {
                d.status = newStatus;
                // Replace actions with a single Detail button (approval actions disabled after decision)
                d.actions = '<button class="btn btn-sm btn-secondary btn-detail" data-id="' + String(d.id) + '">📄 Detail</button>';
                // Optional: set status_html for compatibility
                d.status_html = '<span class="badge ' + (newStatus === 'approved' ? 'bg-success' : 'bg-danger') + '">' + (newStatus === 'approved' ? 'Disetujui' : 'Ditolak') + '</span>';
                this.data(d).invalidate();
                found = true;
            }
        });
        if (found) {
            approvalTable.draw(false);
            // Update action cell for the row to remove/disable actions
            var rowNode = approvalTable.row(function(idx, data, node){ return String(data.id) === String(id); }).node();
            if (rowNode) {
                var $row = $(rowNode);
                var $actionCell = $row.find('td').last();
                // Show a neutral dash like other admin pages
                $actionCell.html('<span class="text-muted">-</span>');
            }
            // hide any floating dropdowns
            $('.action-dropdown.visible').removeClass('visible');
        }
    }
    </script>

  </body>
</html>
