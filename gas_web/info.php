
<!DOCTYPE html>
<html lang="id">
<?php include 'data/head.php'; ?>
<body>
    <?php include 'data/svg.php'; ?>    
    <?php include 'data/header.php'; ?>

    <style>
        .info-page {
            background: #f8fafc;
        }

        .simple-hero {
            background: #fff;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
        }

        .simple-badge {
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #ff6b00;
            background: rgba(255, 107, 0, 0.1);
            padding: 6px 14px;
            border-radius: 999px;
            display: inline-block;
        }

        .simple-list {
            list-style: disc;
            padding-left: 20px;
            margin: 0;
            color: #1f2937;
        }

        .simple-list li {
            margin-bottom: 10px;
        }

        .simple-panel {
            background: rgba(15, 23, 42, 0.04);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .simple-card {
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(15, 23, 42, 0.08);
            padding: 24px;
            height: 100%;
        }

        .simple-steps {
            padding-left: 18px;
            margin: 0;
        }

        .simple-steps li {
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .simple-hero {
                padding: 28px;
            }
        }
    </style>

    <div class="info-page pb-5">
        <section class="container py-5">
            <div class="simple-hero" id="beranda">
                <span class="simple-badge">Tabungan koperasi perusahaan</span>
                <h1 class="mt-3">Tabungan karyawan dan mitra dalam satu sistem.</h1>
                <p class="lead text-muted mt-3 mb-4">Platform koperasi internal PT. Gusti Global Group membantu tim keuangan mencatat setoran, penarikan, dan saldo anggota secara rapi tanpa spreadsheet terpisah.</p>
                <div class="row g-4 align-items-center">
                    <div class="col-lg-6">
                        <ul class="simple-list">
                            <li>Data anggota terhubung langsung dengan dashboard petugas.</li>
                            <li>Approval penarikan berbasis hak akses perusahaan.</li>
                            <li>Laporan bulanan bisa diunduh ke Excel.</li>
                        </ul>
                        <div class="d-flex flex-wrap gap-3 mt-4">
                            <a href="login/" class="btn btn-dark px-4 py-2">Masuk Dashboard</a>
                            <a href="#hubungi" class="btn btn-outline-dark px-4 py-2">Hubungi Tim Koperasi</a>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="simple-panel">
                            <div>
                                <h4 class="mb-1">3.200+</h4>
                                <p class="text-muted mb-3">Anggota koperasi perusahaan aktif</p>
                            </div>
                            <div>
                                <h4 class="mb-1">100%</h4>
                                <p class="text-muted mb-0">Setoran tercatat otomatis dari aplikasi mobile</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="container py-4">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="simple-card">
                        <h5 class="fw-bold mb-2">Fokus pada perusahaan</h5>
                        <p class="text-muted mb-0">Struktur akun mengikuti organisasi (divisi, cabang, atau proyek) sehingga lebih mudah mengaudit kontribusi karyawan.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="simple-card">
                        <h5 class="fw-bold mb-2">Setoran dan penarikan tertib</h5>
                        <p class="text-muted mb-0">Limit transaksi, bukti upload, hingga approval keuangan semuanya berada di jalur digital yang sama.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="simple-card">
                        <h5 class="fw-bold mb-2">Satu database</h5>
                        <p class="text-muted mb-0">Aplikasi mobile karyawan dan web admin memakai sumber data yang sama, sehingga tidak ada duplikasi input.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="container py-4">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="simple-card">
                        <h4 class="fw-bold mb-3">Alur singkat</h4>
                        <ol class="simple-steps">
                            <li>HR atau finance mendaftarkan karyawan melalui dashboard.</li>
                            <li>Karyawan aktivasi akun via OTP di aplikasi mobile.</li>
                            <li>Semua transaksi tercatat dan bisa ditinjau dari laporan akhir bulan.</li>
                        </ol>
                    </div>
                </div>
                <div class="col-lg-6" id="hubungi">
                    <div class="simple-card">
                        <h4 class="fw-bold mb-3">Hubungi kami</h4>
                        <p class="mb-1"><strong>Alamat</strong><br>Dusun Simbar, RT06/RW002, Panjalu, Kab. Ciamis, Jawa Barat 46264</p>
                        <p class="mb-1"><strong>Telp</strong> 0265-2466780</p>
                        <p class="mb-1"><strong>WhatsApp</strong> 0821-2337-6300</p>
                        <p class="mb-0"><strong>Email</strong> support@simtek.co.id</p>
                    </div>
                </div>
            </div>
            <p class="text-center text-muted mt-4">&copy; PT. Gusti Global Group</p>
        </section>
    </div>

    <?php include 'data/footer.php'; ?> 
    <?php include 'data/js.php'; ?> 
    <script src="assets/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
