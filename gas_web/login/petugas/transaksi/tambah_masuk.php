<?php
        include "../../koneksi/config.php";
        $date = date('Y-m-d');

        function tableExists(mysqli $conn, string $table): bool {
            $stmt = $conn->prepare('SHOW TABLES LIKE ?');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;
            $stmt->close();
            return $exists;
        }

        function columnExists(mysqli $conn, string $table, string $column): bool {
            $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('s', $column);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result && $result->num_rows > 0;
            $stmt->close();
            return $exists;
        }

        function firstExisting(mysqli $conn, string $table, array $candidates): ?string {
            foreach ($candidates as $candidate) {
                if ($candidate && columnExists($conn, $table, $candidate)) {
                    return $candidate;
                }
            }
            return null;
        }

        $query1      = "SELECT max(id_masuk) as maxKode FROM t_masuk";
        $hasil1      = mysqli_query($con,$query1);
        $data1       = mysqli_fetch_array($hasil1);
        $idmasuk  = $data1['maxKode'];

        $idmasuk = $idmasuk + 1;

        $char   = "TM-";
        $acak   = date('is');
        $newID  = $char . $idmasuk . ("($acak)");

        if($_REQUEST['id_siswa']) {
        $idParam = $_POST['id_siswa'];

        $idColumn = firstExisting($koneksi, 'pengguna', ['id', 'id_siswa', 'id_pengguna']);
        if ($idColumn === null) {
            echo '<div class="alert alert-danger">Data pengguna tidak ditemukan.</div>';
            exit;
        }

        $selectExtra = '';
        $joinClause = '';
        if (columnExists($koneksi, 'pengguna', 'id_kelas') && tableExists($koneksi, 'kelas') && columnExists($koneksi, 'kelas', 'id_kelas')) {
            $selectExtra = ', k.nama_kelas AS rel_kelas';
            $joinClause = ' LEFT JOIN kelas k ON s.id_kelas = k.id_kelas';
        }

        $sqlText = "SELECT s.*$selectExtra FROM pengguna s$joinClause WHERE s.`$idColumn` = ? LIMIT 1";
        if (!($stmt = $koneksi->prepare($sqlText))) {
            echo '<div class="alert alert-danger">Gagal memuat data pengguna.</div>';
            exit;
        }
        $stmt->bind_param('s', $idParam);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo '<div class="alert alert-warning">Data pengguna tidak ditemukan.</div>';
            $stmt->close();
            exit;
        }
        $row = $result->fetch_assoc();
        $stmt->close();

        $idDisplay = '';
        foreach (['id_tabungan', 'nis', 'no_pengenal', 'username'] as $candidate) {
            if (!empty($row[$candidate])) {
                $idDisplay = $row[$candidate];
                break;
            }
        }
        if ($idDisplay === '') {
            $idDisplay = $row[$idColumn];
        }

        $kelasDisplay = '-';
        if (!empty($row['kelas'])) {
            $kelasDisplay = $row['kelas'];
        } elseif (!empty($row['rel_kelas'])) {
            $kelasDisplay = $row['rel_kelas'];
        }

        $saldoValue = 0;
        foreach (['saldo', 'saldo_tabungan', 'saldo_akhir'] as $saldoCandidate) {
            if (isset($row[$saldoCandidate])) {
                $saldoValue = (float)$row[$saldoCandidate];
                break;
            }
        }

        ?>
 
        <!-- MEMBUAT FORM -->
        <form method="post" name="frm" action="../transaksi/" enctype="multipart/form-data">
        <font color=black>
            <input type="hidden" name="user" value="<?php echo $nama; ?>" readonly>
             <input type="hidden" class="form-control" name="kegiatan" value="Tabungan Masuk" readonly>
            <input type="hidden" name="pengguna_key_column" value="<?php echo htmlspecialchars($idColumn, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="pengguna_key_value" value="<?php echo htmlspecialchars($row[$idColumn], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>No Masuk</label></td>
                <td>&nbsp;</td>
                <td><label>Tanggal</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" name="no" value="<?php echo $newID; ?>" readonly="readonly">                
                </td><td>&nbsp;</td><td>
                <input class="form-control show-tick" name="tanggal" type="date" value="<?php echo $date; ?>" readonly="readonly"/>
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>ID</label></td>
                <td>&nbsp;</td>
                <td><label>Nama</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" name="id_tabungan" value="<?php echo htmlspecialchars($idDisplay, ENT_QUOTES, 'UTF-8'); ?>" readonly="readonly">                
                </td><td>&nbsp;</td><td>
                <input class="form-control" name="nama_pengguna" value="<?php echo htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'); ?>" readonly="readonly">                
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Saldo</label></td>
                <td>&nbsp;</td>
                <td><label>Jumlah</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" name="saldo" value="Rp <?php echo number_format($saldoValue, 0, ',', '.'); ?>" readonly="readonly">             
                </td><td>&nbsp;</td><td>
                <div class="form-line">
                <input class="form-control show-tick" name="jumlah" onkeypress="return hanyaAngka(event)" placeholder="Masukan Angka Rupiah" required>
                </div>              
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Kelas</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control show-tick" name="nama_kelas" value="<?php echo htmlspecialchars($kelasDisplay, ENT_QUOTES, 'UTF-8'); ?>" readonly="readonly">    
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
                <div class="modal-footer">
                <input type="submit" name="tmasuk" value="Simpan" class="btn btn-primary">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Tutup</button>
                </div>
        </font>
        </form>
                      <?php } ?>
                      
    <script>
    function hanyaAngka(evt) {
      var charCode = (evt.which) ? evt.which : event.keyCode
       if (charCode > 31 && (charCode < 48 || charCode > 57))
 
        return false;
      return true;
    }
    </script>
