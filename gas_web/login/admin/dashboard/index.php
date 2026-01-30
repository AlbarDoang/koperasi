<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<?php include "head.php"; ?>

<body>

  <?php include "icon.php"; ?>

  <?php include "header.php"; ?>

  <?php
  // Koneksi sudah ada dari head.php, tidak perlu include lagi

  //fungsi tanggal
  include "../../koneksi/fungsi_indotgl.php";
  //fungsi tanggal
  include "../../koneksi/fungsi_waktu.php";

  require_once __DIR__ . '/../../dashboard_helpers.php';

  $kpis = dashboard_collect_kpis($con);
  $transactionSummary = dashboard_transaction_summary($con);

  $depositStats = array_map('dashboard_format_currency', $transactionSummary['deposit']);
  $withdrawStats = array_map('dashboard_format_currency', $transactionSummary['withdraw']);
  $transferStats = array_map('dashboard_format_currency', $transactionSummary['transfer']);

  $chartPayload = dashboard_generate_chart_payload($con, 6);

  // Pending activation count (for optional alert card)
  $pendingActivationCount = dashboard_count_pending_activations($con);

  $highlightCards = [
    [
      'key' => 'members',
      'icon' => 'fa fa-users',
      'title' => 'Total Anggota',
      'value' => number_format($kpis['members'] ?? 0, 0, ',', '.'),
      'meta' => 'Aktif',
      'metaIcon' => 'fa fa-arrow-up',
      'metaTone' => 'positive'
    ],
    [
      'key' => 'balance',
      'icon' => 'fa fa-money',
      'title' => 'Total Saldo Koperasi',
      'value' => dashboard_format_currency($kpis['balance'] ?? 0),
      'valueClass' => 'fs-5',
      'meta' => 'DB: ' . (isset($kpis['balance_pengguna']) ? dashboard_format_currency($kpis['balance_pengguna']) : 'N/A') . ' | Diff: ' . (isset($kpis['balance_diff']) ? dashboard_format_currency($kpis['balance_diff']) : ''),
      'metaIcon' => 'fa fa-check-circle',
      'metaTone' => 'positive'
    ],

    [
      'key' => 'transactions',
      'icon' => 'fa fa-dollar',
      'title' => 'Total Transaksi',
      'value' => number_format($kpis['transactions'] ?? 0, 0, ',', '.'),
      'meta' => 'Keseluruhan',
      'metaIcon' => 'fa fa-line-chart',
      'metaTone' => 'positive'
    ],
    [
      'key' => 'pending_topups',
      'icon' => 'fa fa-clock',
      'title' => 'Pending Top-ups',
      'value' => dashboard_format_currency($kpis['pending_topups'] ?? 0),
      'valueClass' => 'fs-5',
      'meta' => 'Belum di-approve',
      'metaIcon' => 'fa fa-hourglass-half',
      'metaTone' => 'neutral'
    ],
  ];

  $transactionCards = [
    [
      'key' => 'deposit',
      'title' => 'Transaksi Tabungan Masuk',
      'icon' => 'zmdi zmdi-money',
      'iconClass' => 'text-success text-success-shadow',
      'label' => 'Masuk',
      'stats' => [
        ['label' => 'Hari Ini', 'code' => 'today', 'value' => $depositStats['today']],
        ['label' => 'Minggu Ini', 'code' => 'week', 'value' => $depositStats['week']],
        ['label' => 'Bulan Ini', 'code' => 'month', 'value' => $depositStats['month']],
      ],
    ],
    [
      'key' => 'withdraw',
      'title' => 'Transaksi Penarikan Tabungan',
      'icon' => 'zmdi zmdi-money-off',
      'iconClass' => 'text-danger text-danger-shadow',
      'label' => 'Keluar',
      'stats' => [
        ['label' => 'Hari Ini', 'code' => 'today', 'value' => $withdrawStats['today']],
        ['label' => 'Minggu Ini', 'code' => 'week', 'value' => $withdrawStats['week']],
        ['label' => 'Bulan Ini', 'code' => 'month', 'value' => $withdrawStats['month']],
      ],
    ],
    [
      'key' => 'transfer',
      'title' => 'Transaksi Transfer Tabungan',
      'icon' => 'zmdi zmdi-money-box',
      'iconClass' => 'text-primary text-primary-shadow',
      'label' => 'Transfer',
      'stats' => [
        ['label' => 'Hari Ini', 'code' => 'today', 'value' => $transferStats['today']],
        ['label' => 'Minggu Ini', 'code' => 'week', 'value' => $transferStats['week']],
        ['label' => 'Bulan Ini', 'code' => 'month', 'value' => $transferStats['month']],
      ],
    ],
  ];

  ?>

                    <div class="container-fluid">
                      <div class="row">

                        <?php include "menu.php"; ?>


                        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                            <div class="btn-toolbar mb-2 mb-md-0">
                              <h5>Selamat datang [Admin GAS] di Sistem Koperasi <span style="font-family: 'Segoe UI Emoji','Noto Color Emoji','Apple Color Emoji',sans-serif;">👋</span></h5>
                            </div>
                          </div>

                          <!-- ISI HALAMAN -->

                          <!-- Pending activations notification (display-only) -->
                          <?php if (!empty($pendingActivationCount) && intval($pendingActivationCount) > 0) : ?>
                          <div class="row mb-3">
                            <div class="col-12">
                              <div class="alert alert-warning d-flex justify-content-between align-items-center" role="alert">
                                <div>
                                  <strong>⚠️ <?php echo intval($pendingActivationCount); ?> pengajuan aktivasi akun menunggu persetujuan admin</strong>
                                </div>
                                <div>
                                  <a href="../rekap/" class="btn btn-sm btn-outline-dark">Lihat Pengajuan</a>
                                </div>
                              </div>
                            </div>
                          </div>
                          <?php endif; ?>

                          <!-- ROW -->
                          <div class="row">
                            <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
                              <div class="card">
                                <?php
                                  $weekStartLabel = date('d/m/Y', strtotime('monday this week'));
                                  $weekEndLabel = date('d/m/Y', strtotime('sunday this week'));
                                ?> 
                                <div class="card-header" align="center">
                                  <h5 class="card-title">Grafik Transaksi Tabungan (<?php echo $weekStartLabel . ' — ' . $weekEndLabel; ?>)</h5>
                                </div>
                                <div class="card-body">
                                  <div class="chart-container">
                                    <canvas id="chartLine" class="h-275"></canvas>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>

                          <br>

                          <!-- ROW -->
                          <div class="row">
                            <?php foreach ($highlightCards as $card) : ?>
                              <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 mb-3">
                                <div class="stat-card">
                                  <div class="stat-card-icon">
                                    <i class="<?php echo $card['icon']; ?>"></i>
                                  </div>
                                  <h6 class="stat-card-title"><?php echo $card['title']; ?></h6>
                                  <div class="stat-card-value <?php echo $card['valueClass'] ?? ''; ?>" id="kpi-<?php echo $card['key'] ?? 'unknown'; ?>"><?php echo $card['value']; ?></div>
                                  <?php if (!empty($card['meta'])) : ?>
                                    <div class="stat-card-change <?php echo $card['metaTone'] ?? ''; ?>" id="kpi-<?php echo $card['key'] ?? 'unknown'; ?>-meta">
                                      <i class="<?php echo $card['metaIcon'] ?? ''; ?>"></i> <?php echo $card['meta']; ?>
                                    </div>
                                  <?php endif; ?>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                          <!-- ROW -->
                          <br>
                          <!-- ROW -->
                          <div class="row">
                            <?php foreach ($transactionCards as $card) : ?>
                              <div class="col-sm-12 col-md-6 col-lg-6 col-xl-4 mb-3">
                                <div class="card h-100">
                                  <div class="card-body">
                                    <div class="card-widget">
                                      <h6 class="mb-2"><?php echo $card['title']; ?></h6>
                                      <h2 class="text-end">
                                        <i class="icon-size <?php echo $card['icon']; ?> float-start <?php echo $card['iconClass']; ?>"></i><span><?php echo $card['label']; ?></span>
                                      </h2>
                                      <p class="mb-0">
                                        <?php foreach ($card['stats'] as $stat) : ?>
                                          <?php echo $stat['label']; ?><span class="float-end" id="<?php echo $card['key'] . '-' . ($stat['code'] ?? 'value'); ?>"><?php echo $stat['value']; ?></span><br>
                                        <?php endforeach; ?>
                                      </p>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                          <!-- ROW END -->
        <br>

                        </main>


                      </div>
                    </div>

                    <?php include "js.php"; ?>

                    <!-- CHARTJS JS (local bundle) - cache-bust for testing -->
                    <script src="../../../assets/plugins/chart/Chart.bundle.js?v=2"></script>
                    <!-- <script src="../../../assets/js/chart.js"></script> -->

                    <script>
                      (function() {
                        const ctx = document.getElementById("chartLine");
                        if (!ctx) return;

                        // initial payload from server
                        let chartLabels = <?php echo json_encode($chartPayload['labels']); ?>;
                        // date_keys map label indices -> actual calendar date (YYYY-MM-DD) for exact tooltip dates
                        let chartDates = <?php echo json_encode($chartPayload['date_keys']); ?>;
                        let depositData = <?php echo json_encode($chartPayload['deposit'], JSON_NUMERIC_CHECK); ?>;
                        let withdrawData = <?php echo json_encode($chartPayload['withdraw'], JSON_NUMERIC_CHECK); ?>;
                        let transferData = <?php echo json_encode($chartPayload['transfer'], JSON_NUMERIC_CHECK); ?>;

                        const chartCtx = ctx.getContext('2d');

                        const gradientIn = chartCtx.createLinearGradient(0, 0, 0, 300);
                        gradientIn.addColorStop(0, 'rgba(255,122,0,0.32)');
                        gradientIn.addColorStop(1, 'rgba(255,122,0,0.04)');

                        // Small helpers for formatting
                        const formatCurrency = (value) => {
                          return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
                        };

                        const formatCurrencyShort = (value) => {
                          const v = Number(value || 0);
                          const abs = Math.abs(v);
                          if (abs >= 1000000) {
                            const millions = v / 1000000;
                            // show one decimal for values under 10 million (e.g., 1.5 jt), otherwise integer (e.g., 15 jt)
                            const display = Math.abs(millions) >= 10 ? Math.round(millions) : parseFloat(millions.toFixed(1));
                            return display + ' jt';
                          }
                          if (abs >= 1000) {
                            return Math.round(v / 1000) + ' rb';
                          }
                          // small values show the full rupiah with separators for clarity
                          return 'Rp ' + v.toLocaleString('id-ID');
                        };

                        // Convert YYYY-MM-DD to DD/MM/YYYY for tooltip display
                        const formatDateYmdToDmy = (d) => {
                          if (!d) return '';
                          const parts = d.split('-');
                          if (parts.length !== 3) return d;
                          return `${parts[2]}/${parts[1]}/${parts[0]}`;
                        };

                        // create chart and keep reference for updates
                        window.dashboardChart = new Chart(chartCtx, {
                          type: 'line',
                          data: {
                            labels: chartLabels,
                            datasets: [{
                                label: 'Tabungan Masuk',
                                data: depositData,
                                borderColor: '#16a34a',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#16a34a',
                                pointRadius: 2,
                                pointHoverRadius: 4,
                                fill: false,
                                tension: 0.3
                              },
                              {
                                label: 'Penarikan Tabungan',
                                data: withdrawData,
                                borderColor: '#dc2626',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#dc2626',
                                pointRadius: 2,
                                pointHoverRadius: 4,
                                fill: false,
                                tension: 0.3
                              },
                              {
                                label: 'Transfer',
                                data: transferData,
                                borderColor: '#2563eb',
                                backgroundColor: 'transparent',
                                borderWidth: 2,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: '#2563eb',
                                pointRadius: 2,
                                pointHoverRadius: 4,
                                fill: false,
                                tension: 0.3
                              }
                            ]
                          },
                          options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                              mode: 'index',
                              intersect: false
                            },
                            plugins: {
                              legend: {
                                position: 'top',
                                labels: {
                                  usePointStyle: true,
                                  boxWidth: 10,
                                  padding: 8,
                                  color: '#374151',
                                  font: { size: 12, weight: '600' }
                                }
                              },
                              tooltip: {
                                callbacks: {
                                  title: function(items) {
                                    if (!items || !items.length) return '';
                                    const idx = items[0].dataIndex;
                                    const date = (Array.isArray(chartDates) && chartDates[idx]) ? formatDateYmdToDmy(chartDates[idx]) : items[0].label;
                                    return 'Tanggal: ' + date;
                                  },
                                  label: function(context) {
                                    return `${context.dataset.label}: ${formatCurrency(context.parsed.y)}`;
                                  }
                                },
                                bodyFont: { weight: '600', size: 13 }
                              }
                            },
                            scales: {
                              x: {
                                ticks: {
                                  color: '#6b7280'
                                },
                                grid: {
                                  color: 'rgba(0,0,0,0.05)',
                                  drawBorder: false,
                                  borderDash: [2,2]
                                }
                              },
                              y: {
                                ticks: {
                                  color: '#6b7280',
                                  callback: function(value) {
                                    return formatCurrencyShort(value);
                                  }
                                },
                                grid: {
                                  color: 'rgba(0,0,0,0.05)',
                                  drawBorder: false,
                                  borderDash: [2,2]
                                }
                              }
                            }
                          }
                        });

                        // Update DOM from API payload
                        function updateFromApi(payload) {
                          if (!payload) return;
                          const k = payload.kpis || {};
                          // KPIs
                          const setText = (id, text) => {
                            const el = document.getElementById(id);
                            if (el) el.textContent = text;
                          };

                          setText('kpi-members', (k.members !== undefined) ? Number(k.members).toLocaleString('id-ID') : (document.getElementById('kpi-members') ? document.getElementById('kpi-members').textContent : ''));
                          setText('kpi-balance', formatCurrency(k.balance));
                          setText('kpi-pending_topups', formatCurrency(k.pending_topups));
                          setText('kpi-transactions', (k.transactions !== undefined) ? Number(k.transactions).toLocaleString('id-ID') : (document.getElementById('kpi-transactions') ? document.getElementById('kpi-transactions').textContent : ''));

                          // Transaction summaries
                          const ts = payload.transaction_summary || {};
                          if (ts.deposit) {
                            ['today','week','month'].forEach(code => {
                              const el = document.getElementById('deposit-' + code);
                              if (el) el.textContent = formatCurrency(ts.deposit[code]);
                            });
                          }
                          if (ts.withdraw) {
                            ['today','week','month'].forEach(code => {
                              const el = document.getElementById('withdraw-' + code);
                              if (el) el.textContent = formatCurrency(ts.withdraw[code]);
                            });
                          }
                          if (ts.transfer) {
                            ['today','week','month'].forEach(code => {
                              const el = document.getElementById('transfer-' + code);
                              if (el) el.textContent = formatCurrency(ts.transfer[code]);
                            });
                          }

                          // Chart
                          if (payload.chart) {
                            try {
                              window.dashboardChart.data.labels = payload.chart.labels;
                              window.dashboardChart.data.datasets[0].data = payload.chart.deposit;
                              window.dashboardChart.data.datasets[1].data = payload.chart.withdraw;
                              window.dashboardChart.data.datasets[2].data = payload.chart.transfer;
                              // update date key mapping (for tooltip titles)
                              if (payload.chart.date_keys && Array.isArray(payload.chart.date_keys)) {
                                chartDates = payload.chart.date_keys;
                              }
                              window.dashboardChart.update();
                            } catch (e) {
                              console.warn('Chart update error', e);
                            }
                          }
                        }

                        // Poll the API every 10 seconds
                        async function fetchDashboard() {
                          try {
                            const res = await fetch('api.php', {cache: 'no-store'});
                            if (!res.ok) throw new Error('Network response was not ok');
                            const json = await res.json();
                            updateFromApi(json);
                          } catch (err) {
                            console.warn('Dashboard polling failed', err);
                          }
                        }

                        // initial refresh after a short delay, then polling
                        setTimeout(fetchDashboard, 2000);
                        setInterval(fetchDashboard, 10000);

                        // Real-time updates via local WebSocket broadcaster (best-effort)
                        (function() {
                          const wsUrl = (location.protocol === 'https:') ? 'wss://192.168.1.8:6001' : 'ws://192.168.1.8:6001';
                          let ws;
                          function connectWS() {
                            try {
                              ws = new WebSocket(wsUrl);
                            } catch (e) {
                              console.warn('WS connect failed', e);
                              return setTimeout(connectWS, 3000);
                            }
                            ws.onopen = () => console.log('Dashboard WS connected');
                            ws.onmessage = (evt) => {
                              try {
                                const msg = JSON.parse(evt.data);
                                if (msg && msg.event === 'user-approved' && typeof msg.totalMembers !== 'undefined') {
                                  const el = document.getElementById('kpi-members');
                                  if (el) el.textContent = Number(msg.totalMembers).toLocaleString('id-ID');
                                }
                                // If server requests full refresh, fetch latest dashboard data
                                if (msg && msg.event === 'refresh-dashboard') {
                                  fetchDashboard();
                                }
                              } catch (e) { console.warn('WS message parse error', e); }
                            };
                            ws.onclose = () => { console.warn('WS disconnected, reconnecting in 2s'); setTimeout(connectWS, 2000); };
                            ws.onerror = (e) => { console.warn('WS error', e); ws.close(); };
                          }
                          connectWS();
                        })();

                      })();
                    </script>

</body>

</html>