<?php
include '../../koneksi/config.php';
include '../../koneksi/fungsi_indotgl.php';
include '../../koneksi/fungsi_waktu.php';
include '../../koneksi/terbilang.php';
include '../../koneksi/pengaturan.php';

function transfer_kwitansi_table(mysqli $conn, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $escaped = $conn->real_escape_string($candidate);
        $query = $conn->query("SHOW TABLES LIKE '{$escaped}'");
        if ($query && $query->num_rows > 0) {
            $query->free();
            return $candidate;
        }
        if ($query) {
            $query->free();
        }
    }
    return null;
}

function transfer_kwitansi_first(mysqli $conn, string $table, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        $escaped = $conn->real_escape_string($candidate);
        $query = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$escaped}'");
        if ($query && $query->num_rows > 0) {
            $query->free();
            return $candidate;
        }
        if ($query) {
            $query->free();
        }
    }
    return null;
}

function transfer_kwitansi_safe(array $row, string $key): string
{
    return htmlspecialchars(isset($row[$key]) ? (string)$row[$key] : '', ENT_QUOTES, 'UTF-8');
}

$no_transfer = isset($_GET['no_transfer']) ? trim((string)$_GET['no_transfer']) : '';
if ($no_transfer === '') {
    die('Nomor transfer tidak ditemukan.');
}

$table = transfer_kwitansi_table($koneksi, ['t_transfer', 'transfer']);
if ($table === null) {
    die('Data transfer tidak ditemukan.');
}

$noColumn = transfer_kwitansi_first($koneksi, $table, ['no_transfer', 'kode_transfer']);
if ($noColumn === null) {
    $noColumn = transfer_kwitansi_first($koneksi, $table, ['id_transfer', 'id']);
}
if ($noColumn === null) {
    die('Kolom nomor transfer tidak ditemukan.');
}

$candidateMap = [
    ['aliases' => ['tanggal', 'created_at', 'tgl_transfer'], 'as' => 'tanggal'],
    ['aliases' => ['nominal', 'jumlah', 'jumlah_transfer', 'total'], 'as' => 'nominal'],
    ['aliases' => ['nama_pengirim', 'dari_nama', 'pengirim_nama'], 'as' => 'nama_pengirim'],
    ['aliases' => ['id_pengirim', 'dari_id_tabungan', 'id_tabungan_pengirim', 'id_siswa_pengirim'], 'as' => 'id_pengirim'],
    ['aliases' => ['kelas_pengirim', 'dari_kelas', 'pengirim_kelas'], 'as' => 'kelas_pengirim'],
    ['aliases' => ['nama_penerima', 'ke_nama', 'penerima_nama'], 'as' => 'nama_penerima'],
    ['aliases' => ['id_penerima', 'ke_id_tabungan', 'id_tabungan_penerima', 'id_siswa_penerima'], 'as' => 'id_penerima'],
    ['aliases' => ['kelas_penerima', 'ke_kelas', 'penerima_kelas'], 'as' => 'kelas_penerima'],
    ['aliases' => ['keterangan', 'catatan', 'deskripsi'], 'as' => 'keterangan'],
    ['aliases' => ['waktu', 'updated_at', 'created_at', 'timestamp'], 'as' => 'waktu'],
];

$selectParts = [];
foreach ($candidateMap as $candidate) {
    $column = transfer_kwitansi_first($koneksi, $table, $candidate['aliases']);
    if ($column !== null) {
        $alias = $candidate['as'];
        $selectParts[] = "`{$column}` AS `{$alias}`";
    }
}

if (!$selectParts) {
    die('Kolom data transfer tidak ditemukan.');
}

$selectSql = implode(', ', $selectParts);
$stmt = $koneksi->prepare("SELECT {$selectSql} FROM `{$table}` WHERE `{$noColumn}` = ? LIMIT 1");
if (!$stmt) {
    die('Gagal memuat data transfer.');
}
$stmt->bind_param('s', $no_transfer);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    $stmt->close();
    die('Data transfer tidak ditemukan.');
}
$row = $result->fetch_assoc();
$stmt->close();

$tanggalRaw = isset($row['tanggal']) ? (string)$row['tanggal'] : '';
$tanggalDasar = substr($tanggalRaw, 0, 10);
if ($tanggalDasar === '' || $tanggalDasar === '0000-00-00') {
    $hariTransfer = '-';
    $tanggalTransfer = '-';
} else {
    $hariTransfer = hariindo($tanggalRaw);
    $tanggalTransfer = tgl_indo($tanggalDasar);
}

$nominalValue = isset($row['nominal']) ? (float)$row['nominal'] : 0;
$nominalFormatted = number_format($nominalValue, 0, ',', '.');
$terbilangNominal = penyebut((int)round($nominalValue));

$waktuRaw = isset($row['waktu']) ? (string)$row['waktu'] : $tanggalRaw;
if ($waktuRaw === '') {
    $waktuRaw = $tanggalRaw !== '' ? $tanggalRaw : date('Y-m-d H:i:s');
}
$waktuDisplay = indonesian_date_full($waktuRaw);

$noTransferSafe = htmlspecialchars($no_transfer, ENT_QUOTES, 'UTF-8');
$noTransferUrl = rawurlencode($no_transfer);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

  <?php include "../dashboard/head.php"; ?>

  <body>
    
    <?php include "../dashboard/icon.php"; ?>
    
    <?php include "../dashboard/header.php"; ?>

    <div class="container-fluid">
                                        </tbody>
                                        <thead>
                                            <tr>
                                                <td class="text-uppercase" width="20%"><b>Nominal</b></td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b>Rp. <?php echo htmlspecialchars($nominalFormatted, ENT_QUOTES, 'UTF-8'); ?></b></td>
                                            </tr>
                                        </thead>
                                        <thead>
                                            <tr>
                                                <td colspan="4"><h4><?php echo htmlspecialchars($terbilangNominal, ENT_QUOTES, 'UTF-8'); ?> Rupiah</h4></td>
                                            </tr>
                                        </thead>
                                    </table>
                        Kwitansi Resmi Transaksi di Tabungan Siswa <?php echo htmlspecialchars($nama_sekolah, ENT_QUOTES, 'UTF-8'); ?> Pada <?php echo htmlspecialchars($waktuDisplay, ENT_QUOTES, 'UTF-8'); ?>
            class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom"
          >
            <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="card-footer text-end no-print">

                            <a href="print_kwitansi?no_transfer=<?php echo htmlspecialchars($noTransferUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                            <button type="button" class="btn btn-primary"><i class="si si-printer me-2"></i>Print</button>
                            </a>

                            <a href="../transfer/">
                            <button type="button" class="btn btn-danger"><i class="fe fe-x-square me-2"></i>Kembali</button>
                            </a>
                        </div>
              <h5>Kwitansi Transaksi Tabungan</h5>
            </div>
          </div>

          <!-- ISI HALAMAN -->
          
            <!-- Row -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="clearfix">
                                <div class="float-start">
                                    <h5 >Kwitansi Transaksi</h5>
                                    <h3 class="card-title mb-0">#<?php echo $noTransferSafe; ?></h3>
                                </div>
                                <div class="float-end">
                                    <h3 class="card-title" align="right"><img src="../../../assets/brand/logo.png" width="50%"></h3>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-lg-6 ">
                                    <address>
                                        <h5><button type="button" class="btn btn-success btn-sm mb-1">Berhasil</button></h5>
                                        <h5><?php echo htmlspecialchars($hariTransfer, ENT_QUOTES, 'UTF-8'); ?></h5>
                                        <h5><?php echo htmlspecialchars($tanggalTransfer, ENT_QUOTES, 'UTF-8'); ?></h5>
                                        <h5>Transfer Tabungan</h5>
                                    </address>
                                </div>
                                <div class="col-lg-6 text-end">
                                    <address>
                                        <a href="#myMod" id="custId" data-bs-toggle="modal" data-id="<?php echo $noTransferSafe; ?>" title="Klik Untuk Memperbesar">
                                        <img src="../../../assets/barcode/barcode.php?s=qr&amp;d=<?php echo htmlspecialchars($noTransferUrl, ENT_QUOTES, 'UTF-8'); ?>&amp;p=-8&amp;h=100&amp;w=100" alt="Barcode"></a><br>
                                        Scan Barcode
                                    </address>
                                </div>
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
                                                <td class="text-left"><b><?php echo transfer_kwitansi_safe($row, 'id_pengirim'); ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Nama Pengirim</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                   <td class="text-left"><b><?php echo transfer_kwitansi_safe($row, 'nama_pengirim'); ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Kelas Pengirim</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo transfer_kwitansi_safe($row, 'kelas_pengirim'); ?></b></td>
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
                                                <td class="text-left"><b><?php echo transfer_kwitansi_safe($row, 'id_penerima'); ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Nama Penerima</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo transfer_kwitansi_safe($row, 'nama_penerima'); ?></b></td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong class="text-uppercase">Kelas Penerima</strong>
                                                </td>
                                                <td class="text-center">:</td>
                                                <td class="text-center"></td>
                                                <td class="text-left"><b><?php echo transfer_kwitansi_safe($row, 'kelas_penerima'); ?></b></td>
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
                                                <td class="text-left"><b><?php echo transfer_kwitansi_safe($row, 'keterangan'); ?></b></td>
                                            </tr>
                                        <?php
                                        ?>
                            </a>

                            <a href="../transfer/">
                            <button type="button" class="btn btn-danger"><i class="fe fe-x-square me-2"></i>Kembali</button>
                            </a>
                    </div>
                </div><!-- COL-END -->
            </div>
            <!-- End Row -->

        </main>


      </div>
    </div>

    <?php include "../dashboard/js.php"; ?>
        
    <!-- INTERNAL Notifications js -->
    <script src="../../../assets/plugins/notify/js/rainbow.js"></script>
    <!--script src="public/assets/plugins/notify/js/sample.js"></script-->
    <script src="../../../assets/plugins/notify/js/jquery.growl.js"></script>
    <script src="../../../assets/plugins/notify/js/notifIt.js"></script>

    <!-- FILE UPLOADES JS -->
    <script src="../../../assets/plugins/fileuploads/js/fileupload.js"></script>
    <script src="../../../assets/plugins/fileuploads/js/file-upload.js"></script>

    <!-- INTERNAL Bootstrap-Datepicker js-->
    <script src="../../../assets/plugins/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- INTERNAL File-Uploads Js-->
    <script src="../../../assets/plugins/fancyuploder/jquery.ui.widget.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.fileupload.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.iframe-transport.js"></script>
    <script src="../../../assets/plugins/fancyuploder/jquery.fancy-fileupload.js"></script>
    <script src="../../../assets/plugins/fancyuploder/fancy-uploader.js"></script>

    <!-- SELECT2 JS -->
    <script src="../../../assets/plugins/select2/select2.full.min.js"></script>

    <!-- BOOTSTRAP-DATERANGEPICKER JS -->
    <script src="../../../assets/plugins/bootstrap-daterangepicker/moment.min.js"></script>
    <script src="../../../assets/plugins/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- INTERNAL Bootstrap-Datepicker js-->
    <script src="../../../assets/plugins/bootstrap-datepicker/bootstrap-datepicker.js"></script>

    <!-- INTERNAL Sumoselect js-->
    <script src="../../../assets/plugins/sumoselect/jquery.sumoselect.js"></script>

    <!-- TIMEPICKER JS -->
    <script src="../../../assets/plugins/time-picker/jquery.timepicker.js"></script>
    <script src="../../../assets/plugins/time-picker/toggles.min.js"></script>

    <!-- INTERNAL intlTelInput js-->
    <script src="../../../assets/plugins/intl-tel-input-master/intlTelInput.js"></script>
    <script src="../../../assets/plugins/intl-tel-input-master/country-select.js"></script>
    <script src="../../../assets/plugins/intl-tel-input-master/utils.js"></script>

    <!-- INTERNAL jquery transfer js-->
    <script src="../../../assets/plugins/jQuerytransfer/jquery.transfer.js"></script>

    <!-- INTERNAL multi js-->
    <script src="../../../assets/plugins/multi/multi.min.js"></script>

    <!-- DATEPICKER JS -->
    <script src="../../../assets/plugins/date-picker/date-picker.js"></script>
    <script src="../../../assets/plugins/date-picker/jquery-ui.js"></script>
    <script src="../../../assets/plugins/input-mask/jquery.maskedinput.js"></script>

    <!-- MULTI SELECT JS-->
    <script src="../../../assets/plugins/multipleselect/multiple-select.js"></script>
    <script src="../../../assets/plugins/multipleselect/multi-select.js"></script>

    <!-- FORMELEMENTS JS -->
    <script src="../../../assets/js/formelementadvnced.js"></script>
    <script src="../../../assets/js/form-elements.js"></script>
    
    <script src="../../../assets/plugins/bootstrap/js/popper.min.js"></script>
    <script src="../../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>

    <!-- WYSIWYG Editor JS -->
    <script src="../../../assets/plugins/wysiwyag/jquery.richtext.js"></script>
    <script src="../../../assets/plugins/wysiwyag/wysiwyag.js"></script>

    <!-- SUMMERNOTE JS -->
    <script src="../../../assets/plugins/summernote/summernote-bs4.js"></script>

    <!-- FORMEDITOR JS -->
    <script src="../../../assets/plugins/quill/quill.min.js"></script>
    <script src="../../../assets/js/form-editor2.js"></script>

    
    <!-- modal detail -->
    <div id="myMod" class="modal fade" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
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
      $(document).ready(function(){
          $('#myMod').on('show.bs.modal', function (e) {
              var rowid = $(e.relatedTarget).data('id');
              //menggunakan fungsi ajax untuk pengambilan data
              $.ajax({
                  type : 'post',
                  url : 'barcode_transaksi.php',
                  data :  'no_transfer='+ rowid,
                  success : function(data){
                  $('.fetched-data').html(data);//menampilkan data ke dalam modal
                  }
              });
           });
      });
    </script>
    <!-- END Data Modal-->
  </body>
</html>
