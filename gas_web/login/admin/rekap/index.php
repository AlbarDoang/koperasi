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
          <div class="btn-toolbar mb-2 mb-md-0"></div>
        </div>

        <!-- ISI HALAMAN -->

        <!-- Row -->
        <div class="row row-sm">
          <div class="col-lg-12">
            <div class="card card-nooverflow">
              <div class="card-header" align="center">
                <h5 class="mb-0">Pengajuan Aktivasi</h5>
              </div>
              <div class="card-body">
                <style>
                  /* Adopt style from Semua Pengguna / Pencairan Tabungan for visual consistency */
                  #user_data { font-size: 0.88rem; table-layout: auto; width: 100%; }
                  #user_data th { padding: 10px 8px; white-space: nowrap; font-weight: 700; background-color: #f6f7fb; border: 1px solid #e8e9ed; color: #344054; font-size: 0.9rem; text-align: center !important; }
                  #user_data thead th:after, #user_data thead th:before { display:none !important; }
                  #user_data td { padding: 10px 8px; vertical-align: middle; overflow: hidden; border: 1px solid #eef0f4; color: #222; white-space: nowrap; text-overflow: ellipsis; }
                  /* Keep horizontal scrolling when needed, but allow dropdowns to escape the box.
                     Structural fix: do NOT rely on z-index or appendTo(body). For this table we use a wrapper class
                     and make parent wrappers overflow: visible so dropdown menus are not clipped. If you need
                     table scrolling, prefer DataTables scrollX/scrollY options instead of overflow on card.
                  */
                  .table-responsive { overflow-x: auto; max-width: 100%; }
                  /* page-scoped fix for Pengajuan Aktivasi table */
                  .table-wrapper-nooverflow { overflow: visible !important; }
                  /* DataTables creates #user_data_wrapper; allow it to be visible so dropdowns can escape */
                  #user_data_wrapper, #user_data_wrapper .dataTables_wrapper { overflow: visible !important; }
                  /* Defensive: ensure any nested .table-responsive inside the DT wrapper is visible */
                  #user_data_wrapper .table-responsive { overflow: visible !important; }
                  .btn-sm { padding: 0.35rem 0.5rem; font-size: 0.78rem; margin: 2px; }
                  .cell-ellipsis { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px; }
                  .cell-wrap { display: block; white-space: normal; word-wrap: break-word; overflow-wrap: anywhere; max-width: 320px; }
                  .table { margin-bottom: 0; border-radius: 8px; }
                  #user_data tbody tr:hover { background: #fbfcff; }

                  /* Structural fix specific to this card: allow dropdowns to escape beyond card boundaries */
                  .card-nooverflow { overflow: visible !important; }
                  .card-nooverflow .card-body { overflow: visible !important; }
                  .card-nooverflow .table-responsive { overflow: visible !important; }
                </style>

                <div class="table-responsive table-wrapper-nooverflow">
                  <table class="table table-bordered  table-hover mb-0 text-nowrap border-bottom w-100" id="user_data" border="1">
                    <thead>
                      <tr>
                        <th class="text-center">No</th>
                        <th class="text-center">Nomor HP</th>
                        <th class="text-center">Nama</th>
                        <th class="text-center">Alamat</th>
                        <th class="text-center">Tgl Lahir</th>
                        <th class="text-center">Status</th>
                        <th width="15%" class="text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- End Row -->

        <!-- Server Side -->
        <script type="text/javascript" language="javascript">
          // Initialize pending-users DataTable once DataTables plugin is available.
          (function initPendingUsersTable(){
            function create() {
              if (typeof $.fn !== 'object' || typeof $.fn.DataTable !== 'function') {
                // Retry for up to ~5 seconds
                window._retryInitPendingUsers = (window._retryInitPendingUsers || 0) + 1;
                if (window._retryInitPendingUsers < 50) {
                  setTimeout(initPendingUsersTable, 100);
                  return;
                }
                console.error('DataTables plugin not loaded; cannot initialize pending users table');
                return;
              }

              window.dataTable = $('#user_data').DataTable({
                "processing": true,
                "serverSide": true,
                "order": [[2, 'asc']],
                "ajax": {
                  url: "../../function/fetch_pending_users_impl.php",
                  type: "POST"
                },
                "columnDefs": [{
                    "targets": [0, 6],
                    "orderable": false,
                  }, ],
                // Hide length change ("Show entries") and search box
                "lengthChange": false,
                "searching": false

              });
            }

            if (document.readyState === 'complete' || document.readyState === 'interactive') {
              try { create(); } catch (e) { console.error('Failed to create pending users table', e); }
            } else {
              document.addEventListener('DOMContentLoaded', create);
            }
          })();

          // Approve (reuse existing process endpoint)
          function approveUser(id){
            if (!confirm('Terima aktivasi pengguna ini?')) return;
            fetch('../approval/approve_user_process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id, action:'approve'})})
              .then(async r=>{
                const text = await r.text();
                try{
                  const j = text ? JSON.parse(text) : {};
                  if (j.success) { alert('User diaktifkan'); if (window.dataTable && window.dataTable.ajax) dataTable.ajax.reload(null,false); } else alert('Error: '+(j.message || text || 'unknown'));
                } catch(err){ console.error('approve parse error', err, text); alert('Error: Response parsing failed (server error)'); }
              }).catch(e=>{ console.error('approve error', e); alert('Network or server error: ' + (e.message || e)); });
          }

          // Reject with reason
          function rejectUser(id){
            var reason = prompt('Alasan reject (opsional)');
            if (reason===null) return;
            fetch('../approval/approve_user_process.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id, action:'reject', reason:reason})})
              .then(async r=>{
                const text = await r.text();
                try{
                  const j = text ? JSON.parse(text) : {};
                  if (j.success) { alert('User direject'); if (window.dataTable && window.dataTable.ajax) dataTable.ajax.reload(null,false); } else alert('Error: '+(j.message || text || 'unknown'));
                } catch(err){ console.error('reject parse error', err, text); alert('Error: Response parsing failed (server error)'); }
              }).catch(e=>{ console.error('reject error', e); alert('Network or server error: ' + (e.message || e)); });
          }

          // Show user detail in modal (fetches profile and verification images)
          function showUserDetailFromRekap(e, el){
            e.preventDefault();
            var id = el && (el.getAttribute('data-id') || el.dataset.id) ? (el.getAttribute('data-id') || el.dataset.id) : null;
            if (!id) return alert('User ID tidak tersedia');
            // create or reuse modal
            var modalHtml = `
            <div class="modal fade" id="ud_modal_rekap" tabindex="-1" aria-labelledby="ud_modal_rekap_label" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="ud_modal_rekap_label">Detail Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                    <dl class="row mb-0 view-mode">
                      <dt class="col-sm-4">ID</dt><dd class="col-sm-8" id="ud_id_r">...</dd>
                      <dt class="col-sm-4">Nomor HP</dt><dd class="col-sm-8" id="ud_no_r">...</dd>
                      <dt class="col-sm-4">Nama</dt><dd class="col-sm-8" id="ud_nama_r">...</dd>
                      <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8" id="ud_alamat_r">...</dd>
                      <dt class="col-sm-4">Tgl Lahir</dt><dd class="col-sm-8" id="ud_tgl_r">...</dd>
                      <dt class="col-sm-4">Waktu Daftar</dt><dd class="col-sm-8" id="ud_created_r">...</dd>

                      <dt class="col-sm-4">Foto KTP</dt>
                      <dd class="col-sm-8" id="ud_foto_ktp_r">
                        <img id="ud_img_ktp_r" src="" alt="Foto KTP" loading="lazy" style="max-width:240px; display:none; cursor:pointer;" />
                        <a id="ud_link_ktp_r" href="#" data-title="Foto KTP" style="display:none; margin-left:8px;">Lihat</a>
                      </dd>

                      <dt class="col-sm-4">Foto Selfie</dt>
                      <dd class="col-sm-8" id="ud_foto_selfie_r">
                        <img id="ud_img_selfie_r" src="" alt="Foto Selfie" loading="lazy" style="max-width:240px; display:none; cursor:pointer;" />
                        <a id="ud_link_selfie_r" href="#" data-title="Foto Selfie" style="display:none; margin-left:8px;">Lihat</a>
                      </dd>
                    </dl>
                  </div>
                </div>
              </div>
            </div>`;

            if (!document.getElementById('ud_modal_rekap')) document.body.insertAdjacentHTML('beforeend', modalHtml);

            var modalEl = document.getElementById('ud_modal_rekap');
            // set loading placeholders
            document.getElementById('ud_id_r').textContent = '...';
            document.getElementById('ud_no_r').textContent = '...';
            document.getElementById('ud_nama_r').textContent = '...';
            document.getElementById('ud_alamat_r').textContent = '...';
            document.getElementById('ud_tgl_r').textContent = '...';
            document.getElementById('ud_created_r').textContent = '...';

            // fetch profile from API
            fetch('../../../flutter_api/get_profil.php?id_pengguna=' + encodeURIComponent(id))
              .then(async r=>{
                var text = await r.text();
                try { var j = text ? JSON.parse(text) : {}; } catch(err){ if (!r.ok) throw new Error('HTTP status ' + r.status); throw err; }
                if (!r.ok) throw new Error(j.message || ('HTTP status ' + r.status));
                return j;
              }).then(j=>{
                if (!j.success) { alert('Gagal mengambil detail: ' + (j.message||'')); return; }
                var data = j.data || {};
                document.getElementById('ud_id_r').textContent = data.id || '';
                document.getElementById('ud_no_r').textContent = data.no_hp || '';
                document.getElementById('ud_nama_r').textContent = data.nama_lengkap || '';
                document.getElementById('ud_alamat_r').textContent = data.alamat_domisili || '';
                document.getElementById('ud_tgl_r').textContent = (data.tanggal_lahir || '');
                document.getElementById('ud_created_r').textContent = (data.created_at || '');

                // KTP & Selfie via admin proxy verification_image.php
                var ktpEl = document.getElementById('ud_img_ktp_r');
                var ktpLink = document.getElementById('ud_link_ktp_r');
                var selfieEl = document.getElementById('ud_img_selfie_r');
                var selfieLink = document.getElementById('ud_link_selfie_r');

                if (data.has_ktp) {
                  (function(userId){
                    var base = '../verification_image.php?user_id=' + encodeURIComponent(userId) + '&type=ktp';
                    fetch('../kyc_token.php', {
                      method: 'POST',
                      credentials: 'same-origin',
                      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                      body: 'user_id=' + encodeURIComponent(userId) + '&type=ktp'
                    }).then(r=>r.json()).then(j=>{
                      var url = base;
                      if (j && j.success && j.token) url = base + '&token=' + encodeURIComponent(j.token);
                      ktpEl.src = url; ktpEl.style.display = 'inline-block'; ktpEl.onclick = function(){ openImageLightbox(url, 'Foto KTP'); };
                      ktpLink.href = url; ktpLink.style.display = 'inline-block'; ktpLink.dataset.url = url; ktpLink.onclick = function(e){ e.preventDefault(); openImageLightbox(url, 'Foto KTP'); };
                    }).catch(err=>{
                      // fallback to direct URL (requires admin cookie)
                      var url = base;
                      ktpEl.src = url; ktpEl.style.display = 'inline-block'; ktpLink.href = url; ktpLink.style.display = 'inline-block';
                    });
                  })(data.id);
                } else { ktpEl.style.display = 'none'; ktpLink.style.display = 'none'; }

                if (data.has_selfie) {
                  (function(userId){
                    var base = '../verification_image.php?user_id=' + encodeURIComponent(userId) + '&type=selfie';
                    fetch('../kyc_token.php', {
                      method: 'POST',
                      credentials: 'same-origin',
                      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                      body: 'user_id=' + encodeURIComponent(userId) + '&type=selfie'
                    }).then(r=>r.json()).then(j=>{
                      var url = base;
                      if (j && j.success && j.token) url = base + '&token=' + encodeURIComponent(j.token);
                      selfieEl.src = url; selfieEl.style.display = 'inline-block'; selfieEl.onclick = function(){ openImageLightbox(url, 'Foto Selfie'); };
                      selfieLink.href = url; selfieLink.style.display = 'inline-block'; selfieLink.dataset.url = url; selfieLink.onclick = function(e){ e.preventDefault(); openImageLightbox(url, 'Foto Selfie'); };
                    }).catch(err=>{
                      var url = base;
                      selfieEl.src = url; selfieEl.style.display = 'inline-block'; selfieLink.href = url; selfieLink.style.display = 'inline-block';
                    });
                  })(data.id);
                } else { selfieEl.style.display = 'none'; selfieLink.style.display = 'none'; }

                // show modal
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
              }).catch(e=>{ console.error('showUserDetail fetch error', e); alert('Error mengambil data pengguna: ' + (e.message || '')); });
          }

          // helper: open image modal
          function openImageLightbox(url, title){
            if (!url) return alert('Gambar tidak tersedia');
            var modal = document.getElementById('imagePreviewModal');
            if (!modal) {
              var html = '<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">\n  <div class="modal-dialog modal-dialog-centered modal-lg">\n    <div class="modal-content">\n      <div class="modal-body text-center">\n        <h5 id="imgPreviewTitle" style="margin-bottom:8px;"></h5>\n        <img id="imgPreview" src="" style="max-width:100%; height:auto;" alt="Preview" />\n      </div>\n      <div class="modal-footer">\n        <a id="imgPreviewLink" href="#" target="_blank" class="btn btn-light">Buka di tab baru</a>\n        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>\n      </div>\n    </div>\n  </div>\n</div>';
              document.body.insertAdjacentHTML('beforeend', html);
            }
            document.getElementById('imgPreview').src = url;
            document.getElementById('imgPreviewTitle').textContent = title || '';
            document.getElementById('imgPreviewLink').href = url;
            var modalEl = document.getElementById('imagePreviewModal');
            var modalObj = new bootstrap.Modal(modalEl);
            modalObj.show();
          }
        </script>


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

  <!-- Portal dropdown JS + CSS (page-scoped, production-ready) -->
  <style>
    /* Portal dropdown: force appended dropdowns to appear above everything and look native */
    .dropdown-portal {
      position: absolute !important;
      z-index: 99999 !important;
      min-width: 160px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.12);
      border-radius: 8px;
      background: #fff;
    }
    /* small placeholder style (hidden) */
    .dropdown-portal-placeholder { display: none; }
  </style>

  <script>
    (function(){
      // Restrict to Pengajuan Aktivasi table dropdowns only
      var selector = '#user_data .dropdown';

      function applyPortal($dropdown) {
        var $toggle = $dropdown.find('[data-bs-toggle="dropdown"]').first();
        var toggleId = $toggle.attr('id');
        if (!toggleId) {
          // ensure toggler has an id (aria-labelledby uses id)
          toggleId = 'dd_toggle_' + Math.random().toString(36).slice(2,9);
          $toggle.attr('id', toggleId);
        }
        var $menu = $dropdown.find('.dropdown-menu[aria-labelledby="' + toggleId + '"]').first();
        if (!$menu.length) return;
        // If already portal-ed, skip
        if ($menu.data('portalApplied')) return;

        // store original placement
        $menu.data('portalApplied', true);
        $menu.data('originalParent', $menu.parent());
        var $placeholder = $('<div class="dropdown-portal-placeholder" aria-hidden="true"></div>');
        $menu.before($placeholder);
        $menu.data('placeholder', $placeholder);

        // move to body
        $menu.appendTo(document.body);
        $menu.addClass('dropdown-portal');

        // position function
        function positionMenu(){
          var rect = $toggle[0].getBoundingClientRect();
          // ensure visible to measure
          $menu.css({display:'block', visibility:'hidden', position:'absolute', top:0, left:-9999});
          var menuW = $menu.outerWidth();
          var menuH = $menu.outerHeight();
          var top = rect.bottom + window.scrollY;
          var left = rect.left + window.scrollX;
          if ($menu.hasClass('dropdown-menu-end')) {
            left = rect.right + window.scrollX - menuW;
          }
          var padding = 8;
          if (left < padding) left = padding;
          var maxLeft = window.scrollX + window.innerWidth - menuW - padding;
          if (left > maxLeft) left = maxLeft;
          $menu.css({top: top + 'px', left: left + 'px', zIndex: 99999, visibility:'visible'});
        }

        positionMenu();
        var reposition = positionMenu;
        $(window).on('resize.dropdownPortal scroll.dropdownPortal', reposition);
        $menu.data('repositionHandler', reposition);
      }

      function removePortal($dropdown) {
        var $toggle = $dropdown.find('[data-bs-toggle="dropdown"]').first();
        var toggleId = $toggle.attr('id');
        var $menu = $('[aria-labelledby="' + toggleId + '"]').first();
        if (!$menu.length) return;
        var $placeholder = $menu.data('placeholder');
        var $originalParent = $menu.data('originalParent');
        var handler = $menu.data('repositionHandler');
        $(window).off('resize.dropdownPortal scroll.dropdownPortal', handler);
        if ($placeholder && $placeholder.length) {
          $menu.insertBefore($placeholder);
          $placeholder.remove();
        } else if ($originalParent && $originalParent.length) {
          $menu.appendTo($originalParent);
        } else {
          // fallback
          $dropdown.append($menu);
        }
        $menu.removeClass('dropdown-portal');
        $menu.removeAttr('style');
        $menu.removeData(['portalApplied','originalParent','placeholder','repositionHandler']);
      }

      // Use event delegation; bootstrap triggers events on the dropdown element
      $(document).on('shown.bs.dropdown', selector, function(e){
        try { applyPortal($(this)); } catch (err) { console.error('portal apply error', err); }
      });

      $(document).on('hidden.bs.dropdown', selector, function(e){
        try { removePortal($(this)); } catch (err) { console.error('portal remove error', err); }
      });

      // Defensive: if page loads with an open dropdown (rare), ensure reposition
      $(window).on('load', function(){
        $('#user_data .dropdown.show').each(function(){ applyPortal($(this)); });
      });
    })();
  </script>

  <!-- INTERNAL Notifications js -->
  <script src="../../../assets/plugins/notify/js/rainbow.js"></script>
  <!--script src="public/assets/plugins/notify/js/sample.js"></script-->
  <script src="../../../assets/plugins/notify/js/jquery.growl.js"></script>
  <script src="../../../assets/plugins/notify/js/notifIt.js"></script>




</body>

</html>