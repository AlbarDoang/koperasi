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
    require_once __DIR__ . '/../../approval_helpers.php';

    $approvalData = approval_fetch_rows($con);
    $approvalRows = isset($approvalData['rows']) ? $approvalData['rows'] : [];
    $approvalError = isset($approvalData['error']) ? ($approvalData['message'] ?? 'Data approval tidak dapat dimuat karena konfigurasi database belum lengkap.') : null;
    ?>

    <div class="container-fluid">
      <div class="row">

      <?php include "../dashboard/menu.php"; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div class="btn-toolbar mb-2 mb-md-0">
              <h5>Approval Pinjaman</h5>
            </div>
          </div>

          <style>
            .card .card-header { background-color: #f8f9fa; border-bottom: 2px solid #dee2e6; }
            #approval_table th, #approval_table td { padding: .65rem .75rem; vertical-align: middle; }
            #approval_table thead th { font-weight:600; }
            #approval_table td:last-child { text-align:center; }
          </style>

          <!-- ISI HALAMAN -->
          <div class="row row-sm">
              <div class="col-lg-12">
                  <div class="card">
                      <div class="card-header">
                          <div class="btn-group" role="group">
                              <button type="button" class="btn btn-warning" onclick="filterStatus('pending')">
                                  <i class="fe fe-clock me-2"></i>Pending
                              </button>
                              <button type="button" class="btn btn-success" onclick="filterStatus('approved')">
                                  <i class="fe fe-check me-2"></i>Approved
                              </button>
                              <button type="button" class="btn btn-danger" onclick="filterStatus('rejected')">
                                  <i class="fe fe-x me-2"></i>Rejected
                              </button>
                              <button type="button" class="btn btn-secondary" onclick="filterStatus('all')">
                                  <i class="fe fe-list me-2"></i>Semua
                              </button>
                          </div>
                      </div>
                      <div class="card-body">
                          <?php if ($approvalError): ?>
                            <div class="alert alert-warning mb-0">
                              <?php echo htmlspecialchars($approvalError, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                          <?php else: ?>
                            <div class="table-responsive">
                              <table class="table table-bordered table-hover mb-0 text-nowrap border-bottom w-100" id="approval_table">
                                  <thead>
                                      <tr>
                                          <th class="text-center">No</th>
                                          <th class="text-center">Tanggal</th>
                                          <th class="text-center">NIS</th>
                                          <th>Nama Anggota</th>
                                          <th class="text-center">Jenis</th>
                                          <th class="text-center">Nominal</th>
                                          <th class="text-center">Metode</th>
                                          <th class="text-center">Bukti</th>
                                          <th class="text-center">Status</th>
                                          <th class="text-center">Aksi</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php
                                      $no = 1;
                                      foreach ($approvalRows as $row):
                                        $statusSlug = $row['status'] ?? 'pending';
                                        $statusLabel = ucfirst($row['status_label'] ?? $statusSlug);
                                        $badgeClass = 'bg-warning';
                                        if ($statusSlug === 'approved') {
                                            $badgeClass = 'bg-success';
                                        } elseif ($statusSlug === 'rejected') {
                                            $badgeClass = 'bg-danger';
                                        }

                                        $dateLabel = '-';
                                        if (!empty($row['date'])) {
                                            $timestamp = strtotime($row['date']);
                                            if ($timestamp) {
                                                $dateLabel = tgl_indo(date('Y-m-d', $timestamp)) . ' ' . date('H:i', $timestamp);
                                            } else {
                                                $dateLabel = htmlspecialchars($row['date'], ENT_QUOTES, 'UTF-8');
                                            }
                                        }

                                        $nis = $row['nis'] ?: '-';
                                        $name = $row['name'] ?: '-';
                                        $type = $row['type'] ? ucfirst($row['type']) : '-';
                                        $method = $row['method'] ?: '-';
                                        $amount = dashboard_format_currency($row['amount'] ?? 0);
                                        $proofLink = '-';
                                        if (!empty($row['proof'])) {
                                            $proofPath = ltrim($row['proof'], '/');
                                            $proofLink = '<a href="../../../' . htmlspecialchars($proofPath, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm btn-info">Lihat</a>';
                                        }
                                      ?>
                                      <tr data-status="<?php echo htmlspecialchars($statusSlug, ENT_QUOTES, 'UTF-8'); ?>">
                                          <td><?php echo $no++; ?></td>
                                          <td><?php echo $dateLabel; ?></td>
                                          <td><?php echo htmlspecialchars($nis, ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td><?php echo htmlspecialchars($amount, ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td><?php echo htmlspecialchars($method, ENT_QUOTES, 'UTF-8'); ?></td>
                                          <td><?php echo $proofLink; ?></td>
                                          <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                          <td>
                                              <?php if ($statusSlug === 'pending'): ?>
                                                  <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-light dropdown-toggle" id="approvalMenu<?php echo intval($row['id']); ?>" data-bs-toggle="dropdown" aria-expanded="false">⋮</button>
                                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="approvalMenu<?php echo intval($row['id']); ?>">
                                                      <li><a class="dropdown-item" href="#" onclick="showApprovalDetail(event,this)" data-id="<?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?>">Detail</a></li>
                                                      <li><a class="dropdown-item" href="#" onclick="approveTransaction('<?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?>')">Setujui</a></li>
                                                      <li><a class="dropdown-item text-danger" href="#" onclick="rejectTransaction('<?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?>')">Tolak</a></li>
                                                    </ul>
                                                  </div>
                                              <?php else: ?>
                                                  <span class="text-muted">-</span>
                                              <?php endif; ?>
                                          </td>
                                      </tr>
                                      <?php endforeach; ?>
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

    <?php include "../dashboard/js.php"; ?>

    <!-- DATA TABLE JS-->
    <script src="../../../assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
    <script src="../../../assets/plugins/datatable/js/dataTables.bootstrap5.js"></script>
    <script src="../../../assets/plugins/datatable/dataTables.responsive.min.js"></script>

    <script>
    // Initialize DataTable
    var approvalTable = null;
    $(document).ready(function() {
        if ($('#approval_table').length) {
            approvalTable = $('#approval_table').DataTable({
                order: [[1, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
                }
            });
        }
    });

    // Filter by status
    function filterStatus(status) {
        if (!approvalTable) return;
        if (status === 'all') {
            approvalTable.column(8).search('').draw();
        } else {
            approvalTable.column(8).search(status).draw();
        }
    }

    // Approve transaction
    function approveTransaction(id) {
        if (confirm('Terima pengajuan pinjaman ini?')) {
            $.ajax({
                url: 'approve_process.php',
                method: 'POST',
                dataType: 'json',
                data: { id_pending: id, action: 'approve' },
                success: function(data) {
                    if (data && data.success) {
                        alert('Pengajuan berhasil diterima!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data && data.message ? data.message : 'Tidak diketahui'));
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan');
                }
            });
        }
    }

    // Reject transaction
    function rejectTransaction(id) {
        var reason = prompt('Alasan penolakan:');
        if (reason) {
            $.ajax({
                url: 'approve_process.php',
                method: 'POST',
                dataType: 'json',
                data: { id_pending: id, action: 'reject', reason: reason },
                success: function(data) {
                    if (data && data.success) {
                        alert('Transaksi berhasil di-reject!');
                        location.reload();
                    } else {
                        alert('Error: ' + (data && data.message ? data.message : 'Tidak diketahui'));
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan');
                }
            });
        }
    }

    // Open detail in admin detail page (in a new tab) — fallback if no AJAX detail endpoint exists
    function showApprovalDetail(e, el){
        e.preventDefault();
        var id = el && el.getAttribute ? el.getAttribute('data-id') : null;
        if (!id) return;
        var url = '/gas/gas_web/login/admin/pinjaman_kredit/detail.php?id=' + encodeURIComponent(id);
        window.open(url, '_blank');
    }

    // Prevent dropdowns from being clipped: append to body while shown (same pattern used elsewhere)
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('#approval_table .dropdown').forEach(function(dropdownEl){
        var toggle = dropdownEl.querySelector('.dropdown-toggle');
        var menu = dropdownEl.querySelector('.dropdown-menu');
        if (!toggle || !menu) return;
        dropdownEl.addEventListener('show.bs.dropdown', function(){
          try{ menu.__orig_parent = menu.parentNode; document.body.appendChild(menu); menu.style.position='absolute'; menu.style.zIndex=3000; var rect = toggle.getBoundingClientRect(); menu.style.left = (rect.left + window.scrollX) + 'px'; menu.style.top = (rect.bottom + window.scrollY) + 'px'; menu.setAttribute('data-appended-to-body','1'); }catch(e){}
        });
        dropdownEl.addEventListener('hide.bs.dropdown', function(){ try{ if (menu.getAttribute('data-appended-to-body')==='1'){ menu.removeAttribute('data-appended-to-body'); menu.style.position=''; menu.style.left=''; menu.style.top=''; menu.style.zIndex=''; if (menu.__orig_parent) menu.__orig_parent.appendChild(menu); } }catch(e){} });
      });
    });
    </script>

  </body>
</html>
