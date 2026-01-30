<?php
        include "../../koneksi/config.php";
		
        function detail_transfer_table(mysqli $conn, array $candidates): ?string {
            foreach ($candidates as $candidate) {
                $stmt = $conn->prepare('SHOW TABLES LIKE ?');
                if (!$stmt) {
                    continue;
                }
                $stmt->bind_param('s', $candidate);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result && $result->num_rows > 0;
                $stmt->close();
                if ($exists) {
                    return $candidate;
                }
            }
            return null;
        }

        function detail_transfer_first(mysqli $conn, string $table, array $candidates): ?string {
            foreach ($candidates as $candidate) {
                $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
                if (!$stmt) {
                    continue;
                }
                $stmt->bind_param('s', $candidate);
                $stmt->execute();
                $result = $stmt->get_result();
                $exists = $result && $result->num_rows > 0;
                $stmt->close();
                if ($exists) {
                    return $candidate;
                }
            }
            return null;
        }

        if($_REQUEST['no_transfer']) {
        $no_transfer = $_POST['no_transfer'];

        $table = detail_transfer_table($koneksi, ['t_transfer', 'transfer']);
        if ($table === null) {
            echo '<div class="alert alert-danger">Data transfer tidak ditemukan.</div>';
            exit;
        }

        $noColumn = detail_transfer_first($koneksi, $table, ['no_transfer', 'kode_transfer']);
        if ($noColumn === null) {
            echo '<div class="alert alert-danger">Kolom nomor transfer tidak ditemukan.</div>';
            exit;
        }

        $selectColumns = [
            detail_transfer_first($koneksi, $table, ['nama_pengirim', 'dari_nama', 'pengirim_nama']) => 'nama_pengirim',
            detail_transfer_first($koneksi, $table, ['kelas_pengirim', 'dari_kelas', 'pengirim_kelas']) => 'kelas_pengirim',
            detail_transfer_first($koneksi, $table, ['nominal', 'jumlah', 'jumlah_transfer']) => 'nominal',
            detail_transfer_first($koneksi, $table, ['nama_penerima', 'ke_nama', 'penerima_nama']) => 'nama_penerima',
            detail_transfer_first($koneksi, $table, ['kelas_penerima', 'ke_kelas', 'penerima_kelas']) => 'kelas_penerima',
            detail_transfer_first($koneksi, $table, ['keterangan', 'catatan']) => 'keterangan',
        ];

        $selectList = [];
        foreach ($selectColumns as $column => $alias) {
            if ($column !== null) {
                $selectList[] = "`$column` AS `$alias`";
            }
        }
        $selectSql = $selectList ? implode(',', $selectList) : '*';

        $stmt = $koneksi->prepare("SELECT $selectSql FROM `$table` WHERE `$noColumn` = ? LIMIT 1");
        if (!$stmt) {
            echo '<div class="alert alert-danger">Gagal memuat data transfer.</div>';
            exit;
        }
        $stmt->bind_param('s', $no_transfer);
        $stmt->execute();
        $result = $stmt->get_result();
        if (!$result || $result->num_rows === 0) {
            echo '<div class="alert alert-warning">Data transfer tidak ditemukan.</div>';
            $stmt->close();
            exit;
        }
        $row = $result->fetch_assoc();
        $stmt->close();

        $namaPengirim = isset($row['nama_pengirim']) ? $row['nama_pengirim'] : '';
        $kelasPengirim = isset($row['kelas_pengirim']) ? $row['kelas_pengirim'] : '';
        $nominal = isset($row['nominal']) ? (float)$row['nominal'] : 0;
        $namaPenerima = isset($row['nama_penerima']) ? $row['nama_penerima'] : '';
        $kelasPenerima = isset($row['kelas_penerima']) ? $row['kelas_penerima'] : '';
        $keterangan = isset($row['keterangan']) ? $row['keterangan'] : '';
        ?>
 
        <!-- MEMBUAT FORM -->
        <form>
        <font color=black>
            <div class="form-group">
 				<table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>No Transaksi</label></td>
                <td>&nbsp;</td>
                </tr>
				<tr><td>
                <input class="form-control" value="<?php echo $row['no_transfer']; ?>" readonly>             
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
			</div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Nama Pengirim</label></td>
                <td>&nbsp;</td>
                <td><label>Kelas Pengirim</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo htmlspecialchars($namaPengirim, ENT_QUOTES, 'UTF-8'); ?>" readonly>          
                </td>
                <td>&nbsp;</td><td>
                <input class="form-control" value="<?php echo htmlspecialchars($kelasPengirim, ENT_QUOTES, 'UTF-8'); ?>" readonly>          
                </td>
                <td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Nominal Transfer</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="Rp <?php echo number_format($nominal, 0, ',', '.'); ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Nama Penerima</label></td>
                <td>&nbsp;</td>
                <td><label>Kelas Penerima</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo htmlspecialchars($namaPenerima, ENT_QUOTES, 'UTF-8'); ?>" readonly>          
                </td>
                <td>&nbsp;</td><td>
                <input class="form-control" value="<?php echo htmlspecialchars($kelasPenerima, ENT_QUOTES, 'UTF-8'); ?>" readonly>          
                </td>
                <td>&nbsp;</td>
                </tr>
                </table>
            </div>
    <hr/>
            <div class="form-group">
                <table width="100%" border="0" cellpadding="5px" cellspacing="5px">
                <tr>
                <td><label>Keterangan</label></td>
                <td>&nbsp;</td>
                </tr>
                <tr><td>
                <input class="form-control" value="<?php echo htmlspecialchars($keterangan, ENT_QUOTES, 'UTF-8'); ?>" readonly>                       
                </td>
                </td><td>&nbsp;</td>
                </tr>
                </table>
            </div>
        </font>
        </form>
                      <?php } ?>