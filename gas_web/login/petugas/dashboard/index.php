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

  $highlightCards = [
    [
      'icon' => 'fa fa-users',
      'title' => 'Total Anggota',
      'value' => number_format($kpis['members'] ?? 0, 0, ',', '.'),
      'meta' => 'Aktif',
      'metaIcon' => 'fa fa-arrow-up',
      'metaTone' => 'positive'
    ],
    [
      'icon' => 'fa fa-money',
      'title' => 'Total Saldo Koperasi',
      'value' => dashboard_format_currency($kpis['balance'] ?? 0),
      'valueClass' => 'fs-5',
      // show DB sum and diff to help spot mismatches
      'meta' => sprintf("DB: %s | Diff: %s", dashboard_format_currency($kpis['balance_pengguna'] ?? 0), dashboard_format_currency($kpis['balance_diff'] ?? 0)),
      'metaIcon' => 'fa fa-check-circle',
      'metaTone' => (isset($kpis['balance_diff']) && $kpis['balance_diff'] < 0) ? 'negative' : 'positive'
    ],
    // Pending Top-Ups card (sum of pending mulai_nabung or pending_transactions)
    [
      'icon' => 'fa fa-clock-o',
      'title' => 'Pending Top-Ups',
      'value' => dashboard_format_currency($kpis['pending_topups'] ?? 0),
      'meta' => 'Belum di-approve',
      'metaIcon' => 'fa fa-hourglass-half',
      'metaTone' => 'neutral'
    ],
    [
      'icon' => 'fa fa-user',
      'title' => 'Total Petugas',
      'value' => number_format($kpis['staff'] ?? 0, 0, ',', '.'),
      'meta' => 'Tim Aktif',
      'metaIcon' => 'fa fa-users',
      'metaTone' => 'positive'
    ],
    [
      'icon' => 'fa fa-dollar',
      'title' => 'Total Transaksi',
      'value' => number_format($kpis['transactions'] ?? 0, 0, ',', '.'),
      'meta' => 'Keseluruhan',
      'metaIcon' => 'fa fa-line-chart',
      'metaTone' => 'positive'
    ],
  ];

  $transactionCards = [
    [
      'title' => 'Transaksi Tabungan Masuk',
      'icon' => 'zmdi zmdi-money',
      'iconClass' => 'text-success text-success-shadow',
      'label' => 'Masuk',
      'stats' => [
        ['label' => 'Hari Ini', 'value' => $depositStats['today']],
        ['label' => 'Minggu Ini', 'value' => $depositStats['week']],
        ['label' => 'Bulan Ini', 'value' => $depositStats['month']],
      ],
    ],
    [
      'title' => 'Transaksi Penarikan Tabungan',
      'icon' => 'zmdi zmdi-money-off',
      'iconClass' => 'text-danger text-danger-shadow',
      'label' => 'Keluar',
      'stats' => [
        ['label' => 'Hari Ini', 'value' => $withdrawStats['today']],
        ['label' => 'Minggu Ini', 'value' => $withdrawStats['week']],
        ['label' => 'Bulan Ini', 'value' => $withdrawStats['month']],
      ],
    ],
    [
      'title' => 'Transaksi Transfer Tabungan',
      'icon' => 'zmdi zmdi-money-box',
      'iconClass' => 'text-primary text-primary-shadow',
      'label' => 'Transfer',
      'stats' => [
        ['label' => 'Hari Ini', 'value' => $transferStats['today']],
        ['label' => 'Minggu Ini', 'value' => $transferStats['week']],
        ['label' => 'Bulan Ini', 'value' => $transferStats['month']],
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
            <h5>Selamat datang [Petugas GAS] di Sistem Koperasi <span style="font-family: 'Segoe UI Emoji','Noto Color Emoji','Apple Color Emoji',sans-serif;">👋</span></h5>
          </div>
        </div>

        <!-- ROW: chart -->
        <div class="row">
          <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12">
            <div class="card">
              <div class="card-header" align="center">
                <h5 class="card-title">Grafik Transaksi Tabungan Per-Tujuh Hari</h5>
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

        <!-- ROW: highlight cards -->
        <div class="row">
          <?php foreach ($highlightCards as $card) : ?>
            <div class="col-sm-12 col-md-6 col-lg-6 col-xl-3 mb-3">
              <div class="stat-card">
                <div class="stat-card-icon">
                  <i class="<?php echo $card['icon']; ?>"></i>
                </div>
                <h6 class="stat-card-title"><?php echo $card['title']; ?></h6>
                <div class="stat-card-value <?php echo $card['valueClass'] ?? ''; ?>"><?php echo $card['value']; ?></div>
                <?php if (!empty($card['meta'])) : ?>
                  <div class="stat-card-change <?php echo $card['metaTone'] ?? ''; ?>">
                    <i class="<?php echo $card['metaIcon'] ?? ''; ?>"></i> <?php echo $card['meta']; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <br>

        <!-- ROW: transaction cards -->
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
                        <?php echo $stat['label']; ?><span class="float-end"><?php echo $stat['value']; ?></span><br>
                      <?php endforeach; ?>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      </main>


    </div>
  </div>

  <?php include "js.php"; ?>

  <script src="../../../assets/plugins/chart/Chart.bundle.js?v=2"></script>

  <script>
    (function() {
      const ctx = document.getElementById('chartLine');
      if (!ctx) return;

      const chartLabels = <?php echo json_encode($chartPayload['labels']); ?>;
      const depositData = <?php echo json_encode($chartPayload['deposit'], JSON_NUMERIC_CHECK); ?>;
      const withdrawData = <?php echo json_encode($chartPayload['withdraw'], JSON_NUMERIC_CHECK); ?>;
      const transferData = <?php echo json_encode($chartPayload['transfer'], JSON_NUMERIC_CHECK); ?>;

      const chartCtx = ctx.getContext('2d');

      const gradientIn = chartCtx.createLinearGradient(0, 0, 0, 300);
      gradientIn.addColorStop(0, 'rgba(255,122,0,0.32)');
      gradientIn.addColorStop(1, 'rgba(255,122,0,0.04)');

      const gradientOut = chartCtx.createLinearGradient(0, 0, 0, 300);
      gradientOut.addColorStop(0, 'rgba(150,150,150,0.18)');
      gradientOut.addColorStop(1, 'rgba(150,150,150,0.04)');

      const gradientTransfer = chartCtx.createLinearGradient(0, 0, 0, 300);
      gradientTransfer.addColorStop(0, 'rgba(98,89,202,0.16)');
      gradientTransfer.addColorStop(1, 'rgba(98,89,202,0.02)');

      const formatCurrency = (value) => 'Rp ' + Number(value || 0).toLocaleString('id-ID');

      new Chart(chartCtx, {
        type: 'line',
        data: {
          labels: chartLabels,
          datasets: [{
              label: 'Tabungan Masuk',
              data: depositData,
              borderColor: '#ff7a00',
              backgroundColor: gradientIn,
              borderWidth: 3,
              pointBackgroundColor: '#ffffff',
              pointBorderColor: '#ff7a00',
              pointRadius: 4,
              fill: true,
              tension: 0.35
            },
            {
              label: 'Penarikan Tabungan',
              data: withdrawData,
              borderColor: '#9aa0a6',
              backgroundColor: gradientOut,
              borderWidth: 3,
              pointBackgroundColor: '#ffffff',
              pointBorderColor: '#9aa0a6',
              pointRadius: 4,
              fill: true,
              tension: 0.35
            },
            {
              label: 'Transfer',
              data: transferData,
              borderColor: '#6259ca',
              backgroundColor: gradientTransfer,
              borderWidth: 3,
              pointBackgroundColor: '#ffffff',
              pointBorderColor: '#6259ca',
              pointRadius: 4,
              fill: true,
              tension: 0.35
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
              labels: {
                usePointStyle: true,
                color: '#4b5563'
              }
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return `${context.dataset.label}: ${formatCurrency(context.parsed.y)}`;
                }
              }
            }
          },
          scales: {
            x: {
              ticks: {
                color: '#6b7280'
              },
              grid: {
                display: false
              }
            },
            y: {
              ticks: {
                color: '#6b7280',
                callback: function(value) {
                  return formatCurrency(value);
                }
              },
              grid: {
                color: 'rgba(0,0,0,0.05)'
              }
            }
          }
        }
      });
    })();
  </script>

</body>

</html>
