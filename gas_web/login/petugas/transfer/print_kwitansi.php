 <script>
window.print();
</script>

    <head>    
        <?php 
        session_start();
        if( !isset($_SESSION['saya_petugas']) )
        {
            header('location:./../../'.$_SESSION['akses']);
            exit();
        } 
            $id   = ( isset($_SESSION['id_user']) ) ? $_SESSION['id_user'] : '';
        $foto = ( isset($_SESSION['nama_foto']) ) ? $_SESSION['nama_foto'] : '';
        $nama = ( isset($_SESSION['nama_user']) ) ? $_SESSION['nama_user'] : '';
        //koneksi
        include "../../koneksi/config.php";
        include "../../koneksi/pengaturan.php";

        //fungsi tanggal
        include "../../koneksi/fungsi_indotgl.php";
        //fungsi tanggal
        include "../../koneksi/fungsi_waktu.php";
        //fungsi angka ke text
        include "../../koneksi/terbilang.php";

        function format_ribuan ($nilai){
            return number_format ($nilai, 0, ',', '.');
        }

        if($_REQUEST['no_transfer']) {
        $no_transfer = $_GET['no_transfer'];
        // mengambil data berdasarkan id
        $sql = $con->query("SELECT * FROM t_transfer WHERE no_transfer = '$no_transfer'");
        while($row = $sql->fetch_assoc()){
        $tanggal    =   tgl_indo($row['tanggal']);

        ?>
        <script src="../../../assets/js/color-modes.js"></script>

        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="description" content="" />
        <meta
        name="author"
        content="Mark Otto, Jacob Thornton, and Bootstrap contributors"
        />
        <meta name="generator" content="Hugo 0.112.5" />
        <title>Kwitansi Transfer - Sistem Koperasi GAS</title>

        <!-- FAVICON -->
        <link rel="shortcut icon" type="image/png" href="../../../assets/brand/logo.png" />


        <link
        rel="canonical"
        href="https://getbootstrap.com/docs/5.3/examples/dashboard/"
        />

        <link href="../../../assets/dist/css/bootstrap.min.css" rel="stylesheet" />

        <style>
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
            font-size: 3.5rem;
            }
        }

        .b-example-divider {
            width: 100%;
            height: 3rem;
            background-color: rgba(0, 0, 0, 0.1);
            border: solid rgba(0, 0, 0, 0.15);
            border-width: 1px 0;
            box-shadow: inset 0 0.5em 1.5em rgba(0, 0, 0, 0.1),
            inset 0 0.125em 0.5em rgba(0, 0, 0, 0.15);
        }

        .b-example-vr {
            flex-shrink: 0;
            width: 1.5rem;
            height: 100vh;
        }

        .bi {
            vertical-align: -0.125em;
            fill: currentColor;
        }

        .nav-scroller {
            position: relative;
            z-index: 2;
            height: 2.75rem;
            overflow-y: hidden;
        }

        .nav-scroller .nav {
            display: flex;
            flex-wrap: nowrap;
            padding-bottom: 1rem;
            margin-top: -1px;
            overflow-x: auto;
            text-align: center;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }

        .btn-bd-primary {
            --bd-violet-bg: #FF4C00;
            --bd-violet-rgb: 255, 76, 0;

            --bs-btn-font-weight: 600;
            --bs-btn-color: var(--bs-white);
            --bs-btn-bg: var(--bd-violet-bg);
            --bs-btn-border-color: var(--bd-violet-bg);
            --bs-btn-hover-color: var(--bs-white);
            --bs-btn-hover-bg: #dd4200;
            --bs-btn-hover-border-color: #dd4200;
            --bs-btn-focus-shadow-rgb: var(--bd-violet-rgb);
            --bs-btn-active-color: var(--bs-btn-hover-color);
            --bs-btn-active-bg: #bb3800;
            --bs-btn-active-border-color: #bb3800;
        }
        .bd-mode-toggle {
            z-index: 1500;
        }
        </style>

        <link rel="stylesheet" type="text/css" href="../../../assets/css/bootstrap.css">
        <!-- Custom styles for this template -->
        <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css"
        rel="stylesheet"
        />

        <!-- Custom styles for this template -->
        <link href="dashboard.css" rel="stylesheet" />

        <!--- FONT-ICONS CSS -->
        <link href="../../../assets/css/icons.css" rel="stylesheet"/>
        <script src="https://kit.fontawesome.com/048d18a465.js" crossorigin="anonymous"></script>

            <!-- DATA TABLE CSS -->
            <link href="../../../assets/plugins/datatable/css/dataTables.bootstrap5.css" rel="stylesheet" />
            <link href="../../../assets/plugins/datatable/css/buttons.bootstrap5.min.css"  rel="stylesheet">
            <link href="../../../assets/plugins/datatable/responsive.bootstrap5.css" rel="stylesheet" />

            <!-- SELECT2 CSS -->
            <link href="../../../assets/plugins/select2/select2.min.css" rel="stylesheet"/>  

            <!-- INTERNAL Notifications  Css -->
            <link href="../../../assets/plugins/notify/css/jquery.growl.css" rel="stylesheet" />
            <link href="../../../assets/plugins/notify/css/notifIt.css" rel="stylesheet" />

                
            <!-- WYSIWYG EDITOR CSS -->
            <link href="../../../assets/plugins/wysiwyag/richtext.css" rel="stylesheet"/>

            <!-- SUMMERNOTE CSS -->
            <link rel="stylesheet" href="../../../assets/plugins/summernote/summernote-bs4.css">

            <!-- INTERNAL Quill css -->
            <link href="../../../assets/plugins/quill/quill.snow.css" rel="stylesheet">
            <link href="../../../assets/plugins/quill/quill.bubble.css" rel="stylesheet">
    </head>

    <!-- Row -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="clearfix">
                        <div class="float-start">
                            <h5 >Kwitansi Transaksi </h5>
                            <h3 class="card-title mb-0">#<?php echo $no_transfer ?></h3>
                        </div>
                        <div class="float-end">
                            <h3 class="card-title" align="right"><img src="../../../assets/brand/logo.png" width="50%"></h3>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <table class="table invoice-summary">
                            <thead>
                                <tr>
                                    <th width="30%">
                                    <address>
                                        <h5><button type="button" class="btn btn-success btn-sm mb-1"><font color=green>Berhasil</font></button></h5>
                                        <h5><?php echo hariindo($row['tanggal']); ?></h5>
                                        <h5><?php echo $tanggal; ?></h5>
                                        <h5><?php echo $row['keterangan']; ?></h5>
                                    </address>
                                    </th>
                                    <th width="50%">
                                    <th width="20%" class="text-center">
                                    <address>
                                        <a href="#myMod" id="custId" data-bs-toggle="modal" data-id="<?php echo $row['no_transfer']; ?>" title="Klik Untuk Memperbesar">
                                        <img src="../../../assets/barcode/barcode.php?s=qr&d=<?php echo "$row[no_transfer]"?>&p=-8&h=100&w=100"></a><br>
                                        Scan Barcode
                                    </address>
                                    </th>
                                </tr>
                            </thead>
                        </tabel>
                    </div>                               
                    <div class="row">
                        <div class="col-lg-12 table-responsive">
                            <table class="table invoice-summary">
                                <thead>
                                    <tr>
                                        <th width="10%"></th>
                                        <th width="10%"></th>
                                        <th width="30%"></th>
                                        <th width="20%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <strong class="text-uppercase">ID Pengirim</strong>
                                        </td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b><?php echo $row['id_pengirim']; ?></b></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong class="text-uppercase">Nama Pengirim</strong>
                                        </td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b><?php echo $row['nama_pengirim']; ?></b></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong class="text-uppercase">Kelas Pengirim</strong>
                                        </td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b><?php echo $row['kelas_pengirim']; ?></b></td>
                                    </tr>
                                    <tr>
                                        <td class="text-center"></td>
                                        <td class="text-center"></td>
                                        <td class="text-center"></td>
                                        <td class="text-center"></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong class="text-uppercase">ID Penerima</strong>
                                        </td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b><?php echo $row['id_penerima']; ?></b></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong class="text-uppercase">Nama Penerima</strong>
                                        </td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b><?php echo $row['nama_penerima']; ?></b></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong class="text-uppercase">Kelas Penerima</strong>
                                        </td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b><?php echo $row['kelas_penerima']; ?></b></td>
                                    </tr>
                                    <tr>
                                        <td class="text-center"></td>
                                        <td class="text-center"></td>
                                        <td class="text-center"></td>
                                        <td class="text-center"></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <strong class="text-uppercase">Keterangan</strong>
                                        </td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b><?php echo $row['keterangan']; ?></b></td>
                                    </tr>
                                </tbody>
                                <thead>
                                    <tr>
                                        <td class="text-uppercase" width="20%"><b>Nominal Transfer</b></td>
                                        <td class="text-center">:</td>
                                        <td class="text-center"></td>
                                        <td class="text-left"><b>Rp. <?php echo number_format($row['nominal']) ?></b></td>
                                    </tr>
                                </thead>
                                <thead>
                                    <tr>
                                        <td colspan="4"><h4><?php echo penyebut($row['nominal']) ?> Rupiah</h4></td>
                                    </tr>
                                </thead>
                            </table>
                        Kwitansi Resmi Transaksi di Tabungan Anggota <?php echo $nama_sekolah; ?> Pada <?php echo indonesian_date_full($row['waktu']) ?>  
                                </div>
                    </div>
                </div>
            </div>
        </div><!-- COL-END -->
    </div>
    <!-- End Row -->
    
    <?php } ?>
    <?php } ?>
                        
    <script src="../../../assets/dist/js/bootstrap.bundle.min.js"></script>

    <script
      src="https://cdn.jsdelivr.net/npm/chart.js@4.2.1/dist/chart.umd.min.js"
      integrity="sha384-gdQErvCNWvHQZj6XZM0dNsAoY4v+j5P1XDpNkcM3HJG1Yx04ecqIHk7+4VBOCHOG"
      crossorigin="anonymous"
    ></script>

    <script src="dashboard.js"></script>

    <!-- JQUERY JS -->
    <script src="../../../assets/js/jquery.min.js"></script>

    