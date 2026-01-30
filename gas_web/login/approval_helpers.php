<?php
/**
 * Helper functions to make the approval module resilient against
 * schema differences (missing columns, renamed tables, etc).
 */

require_once __DIR__ . '/dashboard_helpers.php';
// Optional: notification and transaksi helpers used to notify users and record a transaksi row on approval
$notif_helper = __DIR__ . '/../flutter_api/notif_helper.php';
if (file_exists($notif_helper)) require_once $notif_helper;
$tx_helper = __DIR__ . '/../flutter_api/transaksi_helper.php';
if (file_exists($tx_helper)) require_once $tx_helper;

if (!function_exists('approval_get_schema')) {
    function approval_get_schema($con)
    {
        // Default behaviour: discover a sensible pending table (including 'pinjaman')
        return approval_get_schema_for($con, null);
    }
}

if (!function_exists('approval_get_schema_for')) {
    /**
     * Attempt to return a schema for a specific pending table name, or perform discovery when
     * $tableName is null. This enables admin UI to toggle between tables such as
     * 'pinjaman' and 'pinjaman_kredit'.
     */
    function approval_get_schema_for($con, $tableName = null)
    {
        $table = null;
        $table_schema = null;

        if ($tableName) {
            // Try explicit table in current DB
            if (dashboard_table_exists($con, $tableName)) {
                $table = $tableName;
            } else {
                // Try in tabungan schema
                $check = $con->query(sprintf("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = 'tabungan' AND table_name = '%s'", $con->real_escape_string($tableName)));
                if ($check && ($r = $check->fetch_assoc()) && (int)$r['cnt'] > 0) {
                    $table = $tableName;
                    $table_schema = 'tabungan';
                }
            }
        } else {
            // Discovery: pending_transactions, pending_transaksi, pinjaman, pinjaman_biasa, pinjaman_kredit
            $table = dashboard_find_table($con, ['pending_transactions', 'pending_transaksi', 'pinjaman', 'pinjaman_biasa', 'pinjaman_kredit']);

            // As a last resort, look for any table named like 'pinjaman%'
            if (!$table) {
                $r = $con->query("SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = 'tabungan' AND TABLE_NAME LIKE 'pinjaman%' LIMIT 1");
                if ($r && ($rw = $r->fetch_assoc()) && !empty($rw['TABLE_NAME'])) {
                    $table = $rw['TABLE_NAME'];
                    $table_schema = 'tabungan';
                }
            }

            // Backwards compatibility: explicitly accept 'pinjaman' in tabungan schema if present
            if (!$table) {
                $check = $con->query("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = 'tabungan' AND table_name = 'pinjaman'");
                if ($check && ($r = $check->fetch_assoc()) && (int)$r['cnt'] > 0) {
                    $table = 'pinjaman';
                    $table_schema = 'tabungan';
                }
            }
        }

        if (!$table) {
            return [
                'error' => 'pending_table_missing',
                'message' => 'Tabel pending transaksi tidak ditemukan. Periksa migrasi database Anda.'
            ];
        }

        $columns = [];
        $columnKeys = [];

        // Helper to check column existence either in current DB or explicitly in another schema
        $hasColumn = function($col) use ($con, $table, $table_schema) {
            if ($table_schema) {
                $safeCol = $con->real_escape_string($col);
                $q = sprintf("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='%s' AND TABLE_NAME='%s' AND COLUMN_NAME='%s'", $con->real_escape_string($table_schema), $con->real_escape_string($table), $safeCol);
                if ($res = $con->query($q)) {
                    $row = $res->fetch_assoc();
                    return ((int)($row['cnt'] ?? 0)) > 0;
                }
                return false;
            }
            return dashboard_column_exists($con, $table, $col);
        };

        // Similar to dashboard_pick_column but supports external schema
        $pickColumn = function($candidates) use ($con, $hasColumn, &$columnKeys) {
            foreach ($candidates as $candidate) {
                if (is_array($candidate)) {
                    $column = $candidate['column'] ?? null;
                    $key = $candidate['key'] ?? $column;
                } else {
                    $column = $candidate;
                    $key = $candidate;
                }
                if ($column && $hasColumn($column)) {
                    $columnKeys[$key] = $key;
                    return $column;
                }
            }
            return null;
        };

        $columns['id'] = $pickColumn([
            ['column' => 'id_pending', 'key' => 'id_pending'],
            ['column' => 'id', 'key' => 'id'],
            ['column' => 'pending_id', 'key' => 'pending_id'],
        ]);

        $columns['amount'] = $pickColumn([
            ['column' => 'nominal', 'key' => 'nominal'],
            ['column' => 'jumlah_pinjaman', 'key' => 'jumlah_pinjaman'],
            ['column' => 'jumlah', 'key' => 'jumlah'],
            ['column' => 'total', 'key' => 'total'],
            ['column' => 'amount', 'key' => 'amount'],
        ]);

        $columns['member'] = $pickColumn([
            ['column' => 'id_siswa', 'key' => 'id_siswa'],
            ['column' => 'id_pengguna', 'key' => 'id_pengguna'],
            ['column' => 'id_anggota', 'key' => 'id_anggota'],
            ['column' => 'id_tabungan', 'key' => 'id_tabungan'],
            ['column' => 'siswa_id', 'key' => 'id_siswa'],
            ['column' => 'id_user', 'key' => 'id_user'],
            ['column' => 'nis', 'key' => 'nis'],
        ]);

        if (!$columns['id'] || !$columns['amount'] || !$columns['member']) {
            return [
                'error' => 'pending_column_missing',
                'message' => 'Kolom kunci (ID, nominal, atau referensi anggota) tidak ditemukan di tabel pending transaksi.'
            ];
        }

        $columns['status'] = $pickColumn(['status', 'state', 'approval_status']);
        $columns['type'] = $pickColumn(['jenis', 'tipe', 'type']);
        $columns['method'] = $pickColumn(['metode', 'method', 'cara']);
        $columns['date'] = $pickColumn(['created_at', 'tanggal', 'tgl_pengajuan', 'waktu', 'tanggal_transaksi']);
        $columns['proof'] = $pickColumn(['bukti_transfer', 'bukti', 'lampiran', 'attachment']);
        $columns['approved_at'] = $pickColumn(['approved_at', 'updated_at', 'tgl_approve']);
        $columns['approved_by'] = $pickColumn(['approved_by', 'id_petugas', 'petugas']);
        // Prefer an explicit 'keterangan' column for human-readable notes if available
$columns['reject_reason'] = $pickColumn(['keterangan','reject_reason', 'catatan_approval', 'alasan']);
        $columns['nis_fallback'] = $pickColumn(['nis', 'no_induk', 'username']);
        $columns['name_fallback'] = $pickColumn(['nama', 'nama_anggota', 'nama_siswa', 'nama_lengkap']);

        $memberTable = dashboard_find_table($con, ['pengguna', 'anggota']);
        $memberColumns = [
            'id_pengguna' => $memberTable ? dashboard_pick_column($con, $memberTable, ['id_pengguna', 'id']) : null,
            'id_anggota' => $memberTable ? dashboard_pick_column($con, $memberTable, ['id_anggota', 'id']) : null,
            'tabungan' => $memberTable ? dashboard_pick_column($con, $memberTable, ['id_tabungan', 'kode_tabungan']) : null,
            'nis' => $memberTable ? dashboard_pick_column($con, $memberTable, ['nis', 'no_induk', 'username']) : null,
            'name' => $memberTable ? dashboard_pick_column($con, $memberTable, ['nama', 'nama_lengkap', 'full_name', 'name']) : null,
            'saldo' => $memberTable ? dashboard_pick_column($con, $memberTable, ['saldo', 'saldo_tabungan', 'saldo_akhir']) : null,
            'class' => $memberTable ? dashboard_pick_column($con, $memberTable, ['kelas', 'kelas_id']) : null,
        ];

        $schema = [
            'table' => $table,
            'columns' => $columns,
            'column_keys' => $columnKeys,
            'member_table' => $memberTable,
            'member_columns' => $memberColumns,
        ];
        if ($table_schema) {
            $schema['table_schema'] = $table_schema;
        }

        return [
            'success' => true,
            'schema' => $schema,
        ];
    }
}

if (!function_exists('approval_normalize_row')) {
    function approval_normalize_row($schema, $row)
    {
        $columns = $schema['columns'];

        $statusLabel = $columns['status'] && isset($row[$columns['status']])
            ? (string)$row[$columns['status']]
            : 'pending';

        return [
            'id' => $row[$columns['id']],
            'amount' => (float)($row[$columns['amount']] ?? 0),
            'status' => strtolower($statusLabel),
            'status_label' => $statusLabel,
            'date' => $columns['date'] && isset($row[$columns['date']]) ? $row[$columns['date']] : null,
            'member_value' => $row[$columns['member']],
            'type' => $columns['type'] && isset($row[$columns['type']]) ? $row[$columns['type']] : null,
            // Prefer explicit cicilan_per_bulan when present
            'cicilan_per_bulan' => isset($row['cicilan_per_bulan']) ? (float)$row['cicilan_per_bulan'] : null,
            'method' => $columns['method'] && isset($row[$columns['method']]) ? $row[$columns['method']] : null,
            'proof' => $columns['proof'] && isset($row[$columns['proof']]) ? $row[$columns['proof']] : null,
            'nis_fallback' => $columns['nis_fallback'] && isset($row[$columns['nis_fallback']]) ? $row[$columns['nis_fallback']] : null,
            'name_fallback' => $columns['name_fallback'] && isset($row[$columns['name_fallback']]) ? $row[$columns['name_fallback']] : null,
            'raw' => $row,
        ];
    }
}

if (!function_exists('approval_seek_member')) {
    function approval_seek_member($con, $schema, $identifier)
    {
        static $cache = [];

        if (!$schema['member_table'] || $identifier === null || $identifier === '') {
            return null;
        }

        if (isset($cache[$identifier])) {
            return $cache[$identifier];
        }

        $memberTable = $schema['member_table'];
        $searchOrder = [];
        $pendingColumn = $schema['columns']['member'];

        if ($pendingColumn && dashboard_column_exists($con, $memberTable, $pendingColumn)) {
            $searchOrder[] = $pendingColumn;
        }

        foreach (['id_pengguna', 'id_anggota', 'tabungan', 'nis'] as $key) {
            $columnName = $schema['member_columns'][$key] ?? null;
            if ($columnName && !in_array($columnName, $searchOrder, true)) {
                $searchOrder[] = $columnName;
            }
        }

        foreach ($searchOrder as $column) {
            $safe = $con->real_escape_string((string)$identifier);
            $sql = sprintf(
                "SELECT * FROM `%s` WHERE `%s` = '%s' LIMIT 1",
                $memberTable,
                $column,
                $safe
            );
            if ($result = $con->query($sql)) {
                if ($result->num_rows > 0) {
                    $cache[$identifier] = [
                        'column' => $column,
                        'data' => $result->fetch_assoc(),
                    ];
                    return $cache[$identifier];
                }
            }
        }

        $cache[$identifier] = null;
        return null;
    }
}

if (!function_exists('approval_fetch_rows')) {
    function approval_fetch_rows($con, $tableOverride = null)
    {
        // Allow callers to request a specific pending table (e.g., 'pinjaman_kredit')
        $schemaResult = $tableOverride ? approval_get_schema_for($con, $tableOverride) : approval_get_schema($con);
        if (isset($schemaResult['error'])) {
            return $schemaResult;
        }

        $schema = $schemaResult['schema'];
        $columns = $schema['columns'];
        $orderField = $columns['date'] ?? $columns['id'];

        $qualified = approval_qualified_table($con, $schema);
        // For pinjaman-like tables (loan requests) prefer oldest-first (ASC) so long-waiting requests appear on top
        $orderDir = (isset($schema['table']) && is_string($schema['table']) && stripos($schema['table'], 'pinjaman') === 0) ? 'ASC' : 'DESC';
        $sql = sprintf('SELECT * FROM %s ORDER BY `%s` %s', $qualified, $orderField, $orderDir);
        $result = $con->query($sql);
        if (!$result) {
            return [
                'error' => 'query_failed',
                'message' => 'Gagal mengambil data pending transaksi: ' . $con->error,
            ];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $normalized = approval_normalize_row($schema, $row);
            $memberInfo = approval_seek_member($con, $schema, $normalized['member_value']);

            if ($memberInfo) {
                $memberData = $memberInfo['data'];
                $nisColumn = $schema['member_columns']['nis'] ?? null;
                $nameColumn = $schema['member_columns']['name'] ?? null;
                $normalized['nis'] = $nisColumn && isset($memberData[$nisColumn]) ? $memberData[$nisColumn] : $normalized['nis_fallback'];
                $normalized['name'] = $nameColumn && isset($memberData[$nameColumn]) ? $memberData[$nameColumn] : $normalized['name_fallback'];
            } else {
                $normalized['nis'] = $normalized['nis_fallback'];
                $normalized['name'] = $normalized['name_fallback'];
            }

            $rows[] = $normalized;
        }

        return [
            'success' => true,
            'schema' => $schema,
            'rows' => $rows,
        ];
    }
}

if (!function_exists('approval_fetch_pending_row')) {
    function approval_fetch_pending_row($con, $schema, $id)
    {
        $idColumn = $schema['columns']['id'];
        $safeId = $con->real_escape_string((string)$id);
        $qualified = approval_qualified_table($con, $schema);
        $sql = sprintf('SELECT * FROM %s WHERE `%s` = \'%s\' LIMIT 1', $qualified, $idColumn, $safeId);
        $result = $con->query($sql);
        if (!$result || $result->num_rows === 0) {
            return null;
        }

        $row = $result->fetch_assoc();
        return approval_normalize_row($schema, $row);
    }
}

if (!function_exists('approval_update_status')) {
    function approval_update_status($con, $schema, $id, $status, $userId, $reason = null)
    {
        $columns = $schema['columns'];
        $updates = [];

        if ($columns['status']) {
            $updates[] = sprintf("`%s` = '%s'", $columns['status'], $con->real_escape_string($status));
        }
        if ($columns['approved_at']) {
            $updates[] = sprintf("`%s` = NOW()", $columns['approved_at']);
        }
        if ($columns['approved_by']) {
            $updates[] = sprintf("`%s` = '%s'", $columns['approved_by'], $con->real_escape_string((string)$userId));
        }
        if ($columns['reject_reason']) {
            if ($status === 'rejected') {
                $updates[] = sprintf("`%s` = '%s'", $columns['reject_reason'], $con->real_escape_string($reason ?? 'Ditolak'));
            } else {
                $updates[] = sprintf("`%s` = NULL", $columns['reject_reason']);
            }
        }

        if (empty($updates)) {
            return true;
        }

        $idColumn = $columns['id'];
        $safeId = $con->real_escape_string((string)$id);
        $qualified = approval_qualified_table($con, $schema);
        $sql = sprintf(
            'UPDATE %s SET %s WHERE `%s` = \'%s\'',
            $qualified,
            implode(', ', $updates),
            $idColumn,
            $safeId
        );
        return (bool)$con->query($sql);
    }
}

if (!function_exists('approval_insert_row')) {
    function approval_insert_row($con, $table, $data)
    {
        $columns = [];
        $values = [];

        foreach ($data as $column => $value) {
            if ($value === null) {
                continue;
            }

            $columns[] = sprintf('`%s`', $column);
            if ($value === '__NOW__') {
                $values[] = 'NOW()';
            } else {
                $values[] = sprintf("'%s'", $con->real_escape_string((string)$value));
            }
        }

        if (empty($columns)) {
            throw new RuntimeException("Tidak ada data yang dapat disimpan ke tabel {$table}");
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(',', $columns),
            implode(',', $values)
        );

        if (!$con->query($sql)) {
            throw new RuntimeException("Gagal menyimpan ke {$table}: " . $con->error);
        }
    }
}

if (!function_exists('approval_qualified_table')) {
    function approval_qualified_table($con, $schema)
    {
        $table = $schema['table'];
        if (!empty($schema['table_schema'])) {
            return sprintf('`%s`.`%s`', $con->real_escape_string($schema['table_schema']), $con->real_escape_string($table));
        }
        return sprintf('`%s`', $con->real_escape_string($table));
    }
}

if (!function_exists('approval_record_credit')) {
    function approval_insert_row_qualified($con, $schemaName, $table, $data)
    {
        $columns = [];
        $values = [];

        foreach ($data as $column => $value) {
            if ($value === null) {
                continue;
            }

            $columns[] = sprintf('`%s`', $column);
            if ($value === '__NOW__') {
                $values[] = 'NOW()';
            } else {
                $values[] = sprintf("'%s'", $con->real_escape_string((string)$value));
            }
        }

        if (empty($columns)) {
            throw new RuntimeException("Tidak ada data yang dapat disimpan ke tabel {$table}");
        }

        if ($schemaName) {
            $tbl = sprintf('`%s`.`%s`', $con->real_escape_string($schemaName), $con->real_escape_string($table));
        } else {
            $tbl = sprintf('`%s`', $con->real_escape_string($table));
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tbl,
            implode(',', $columns),
            implode(',', $values)
        );

        if (!$con->query($sql)) {
            throw new RuntimeException("Gagal menyimpan ke {$tbl}: " . $con->error);
        }
    }

    function approval_record_credit($con, $schema, $pendingRow, $memberContext, $userId)
    {
        $amount = $pendingRow['amount'];
        $memberColumns = $schema['member_columns'];
        $memberData = $memberContext['data'];
        $generatedCode = 'TM' . date('YmdHis') . mt_rand(100, 999);

        // Helper to find a table either in current DB or in the external 'tabungan' schema
        $findTable = function($name) use ($con) {
            // current DB
            if (dashboard_table_exists($con, $name)) return ['schema' => null, 'table' => $name];
            // Prefer a schema literally named 'tabungan' if present
            $qpref = sprintf("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = 'tabungan' AND TABLE_NAME = '%s'", $con->real_escape_string($name));
            if ($r = $con->query($qpref)) {
                $row = $r->fetch_assoc();
                if (isset($row['cnt']) && (int)$row['cnt'] > 0) return ['schema' => 'tabungan', 'table' => $name];
            }
            // As a fallback, search for the table across all non-system schemas and return first match
            $q = sprintf("SELECT TABLE_SCHEMA FROM information_schema.tables WHERE TABLE_NAME = '%s' AND TABLE_SCHEMA NOT IN ('mysql','information_schema','performance_schema','sys') LIMIT 1", $con->real_escape_string($name));
            if ($r2 = $con->query($q)) {
                if ($rw = $r2->fetch_assoc()) {
                    return ['schema' => $rw['TABLE_SCHEMA'], 'table' => $name];
                }
            }
            return null;
        };

        $hasColumnIn = function($table, $col, $schemaName = null) use ($con) {
            if ($schemaName) {
                $q = sprintf("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'", $con->real_escape_string($schemaName), $con->real_escape_string($table), $con->real_escape_string($col));
                if ($r = $con->query($q)) {
                    $row = $r->fetch_assoc();
                    return ((int)($row['cnt'] ?? 0)) > 0;
                }
                return false;
            }
            return dashboard_column_exists($con, $table, $col);
        };

        // Prefer t_masuk (local or external) then fallback to tabungan
        $tmasukTarget = $findTable('t_masuk');
        if ($tmasukTarget) {
            $data = [];
            if ($hasColumnIn('t_masuk', 'no_masuk', $tmasukTarget['schema'])) {
                $data['no_masuk'] = $generatedCode;
            }
            if ($hasColumnIn('t_masuk', 'nis', $tmasukTarget['schema'])) {
                $data['nis'] = $memberColumns['nis'] && isset($memberData[$memberColumns['nis']])
                    ? $memberData[$memberColumns['nis']]
                    : ($pendingRow['nis_fallback'] ?? null);
            }
            if ($hasColumnIn('t_masuk', 'nama', $tmasukTarget['schema'])) {
                $data['nama'] = $memberColumns['name'] && isset($memberData[$memberColumns['name']])
                    ? $memberData[$memberColumns['name']]
                    : ($pendingRow['name_fallback'] ?? null);
            }
            if ($hasColumnIn('t_masuk', 'id_tabungan', $tmasukTarget['schema']) && $memberColumns['tabungan']) {
                $data['id_tabungan'] = $memberData[$memberColumns['tabungan']] ?? null;
            }
            if ($hasColumnIn('t_masuk', 'kelas', $tmasukTarget['schema']) && $memberColumns['class']) {
                $data['kelas'] = $memberData[$memberColumns['class']] ?? null;
            }
            if ($hasColumnIn('t_masuk', 'tanggal', $tmasukTarget['schema'])) {
                $data['tanggal'] = '__NOW__';
            }
            $amountColumn = $hasColumnIn('t_masuk', 'jumlah', $tmasukTarget['schema']) ? 'jumlah' : ($hasColumnIn('t_masuk', 'nominal', $tmasukTarget['schema']) ? 'nominal' : null);
            if ($amountColumn) {
                $data[$amountColumn] = $amount;
            }
            if ($hasColumnIn('t_masuk', 'id_pending', $tmasukTarget['schema'])) {
                $data['id_pending'] = $pendingRow['id'];
            }
            if ($hasColumnIn('t_masuk', 'kegiatan', $tmasukTarget['schema'])) {
                $data['kegiatan'] = 'Setoran Mobile';
            }
            if ($hasColumnIn('t_masuk', 'created_from', $tmasukTarget['schema'])) {
                $data['created_from'] = 'mobile';
            }
            if ($hasColumnIn('t_masuk', 'id_petugas', $tmasukTarget['schema'])) {
                $data['id_petugas'] = $userId;
            }
            if ($hasColumnIn('t_masuk', 'created_at', $tmasukTarget['schema'])) {
                $data['created_at'] = '__NOW__';
            }

            if (!empty($data)) {
                approval_insert_row_qualified($con, $tmasukTarget['schema'], 't_masuk', $data);
                return;
            }
        }

        $tabunganTarget = $findTable('tabungan');
        if ($tabunganTarget) {
            $data = [];
            if ($hasColumnIn('tabungan', 'id_anggota', $tabunganTarget['schema']) && $memberColumns['id_anggota'] && isset($memberData[$memberColumns['id_anggota']])) {
                $data['id_anggota'] = $memberData[$memberColumns['id_anggota']];
            } elseif ($hasColumnIn('tabungan', 'id_siswa', $tabunganTarget['schema']) && $memberColumns['id_siswa'] && isset($memberData[$memberColumns['id_siswa']])) {
                $data['id_siswa'] = $memberData[$memberColumns['id_siswa']];
            } elseif ($hasColumnIn('tabungan', 'id_tabungan', $tabunganTarget['schema']) && $memberColumns['tabungan'] && isset($memberData[$memberColumns['tabungan']])) {
                $data['id_tabungan'] = $memberData[$memberColumns['tabungan']];
            }

            if ($hasColumnIn('tabungan', 'tanggal', $tabunganTarget['schema'])) {
                $data['tanggal'] = '__NOW__';
            }
            if ($hasColumnIn('tabungan', 'jenis', $tabunganTarget['schema'])) {
                $data['jenis'] = 'masuk';
            }
            $amountColumn = $hasColumnIn('tabungan', 'jumlah', $tabunganTarget['schema']) ? 'jumlah' : ($hasColumnIn('tabungan', 'nominal', $tabunganTarget['schema']) ? 'nominal' : null);
            if ($amountColumn) {
                $data[$amountColumn] = $amount;
            }
            if ($hasColumnIn('tabungan', 'keterangan', $tabunganTarget['schema'])) {
                $data['keterangan'] = 'Approved from mobile';
            }
            if ($hasColumnIn('tabungan', 'id_petugas', $tabunganTarget['schema'])) {
                $data['id_petugas'] = $userId;
            }
            if ($hasColumnIn('tabungan', 'created_at', $tabunganTarget['schema'])) {
                $data['created_at'] = '__NOW__';
            }

            if (!empty($data)) {
                approval_insert_row_qualified($con, $tabunganTarget['schema'], 'tabungan', $data);
                return;
            }
        }

        throw new RuntimeException('Tabel pencatatan setoran (t_masuk/tabungan) tidak ditemukan.');
    }
}

if (!function_exists('approval_update_member_balance')) {
    function approval_update_member_balance($con, $schema, $memberContext, $amount)
    {
        if (!$schema['member_table']) {
            throw new RuntimeException('Tabel anggota tidak tersedia.');
        }

        $saldoColumn = $schema['member_columns']['saldo'] ?? null;
        if (!$saldoColumn) {
            throw new RuntimeException('Kolom saldo tidak ditemukan pada tabel anggota.');
        }

        $idColumn = $memberContext['column'];
        $idValue = $memberContext['data'][$idColumn] ?? null;
        if ($idValue === null) {
            throw new RuntimeException('Kolom identitas anggota tidak valid.');
        }

        $amountSql = number_format($amount, 2, '.', '');
        $sql = sprintf(
            "UPDATE `%s` SET `%s` = COALESCE(`%s`,0) + %s WHERE `%s` = '%s'",
            $schema['member_table'],
            $saldoColumn,
            $saldoColumn,
            $amountSql,
            $idColumn,
            $con->real_escape_string((string)$idValue)
        );

        if (!$con->query($sql)) {
            throw new RuntimeException('Gagal memperbarui saldo anggota: ' . $con->error);
        }
    }
}

if (!function_exists('approval_apply_action')) {
    function approval_apply_action($con, $schema, $pendingRow, $action, $userId, $reason = null)
    {
        if ($action === 'approve') {
            // Special handling for loan approvals: when the pending table is 'pinjaman',
            // we must update the pinjaman status AND add the amount to the user's saldo
            // only *after* approval. If deposit tables exist, try to record a t_masuk/tabungan
            // row for audit (but do not fail the approval if that recording fails).
            if (isset($schema['table']) && $schema['table'] === 'pinjaman') {
                // Find member context (expects id_pengguna in member_value)
                $memberContext = approval_seek_member($con, $schema, $pendingRow['member_value']);
                if (!$memberContext) {
                    return ['success' => false, 'message' => 'Data anggota tidak ditemukan di database.'];
                }

                $con->begin_transaction();
                try {
                    $depositRecorded = false;
                    // Attempt to record a deposit for audit; the function will internally find the appropriate table (local or external)
                    try {
                        approval_record_credit($con, $schema, $pendingRow, $memberContext, $userId);
                        $depositRecorded = true;
                    } catch (Throwable $e) {
                        // Log and continue — approval should proceed even if audit recording fails
                        @file_put_contents(__DIR__ . '/../../api/pinjaman/debug.log', date('Y-m-d H:i:s') . " approval_record_credit failed: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                    }

                    // Update member saldo (this will throw if member table or saldo column is missing)
                    approval_update_member_balance($con, $schema, $memberContext, $pendingRow['amount']);

                    // Update pinjaman status to approved
                    approval_update_status($con, $schema, $pendingRow['id'], 'approved', $userId);

                    $con->commit();
                    $msg = 'Pengajuan pinjaman berhasil di-approve';
                    if ($depositRecorded) $msg .= ' dan dicatat sebagai setoran.';

                    // Create notification and transaksi record for approval if helpers available
                    try {
                        // Determine a sensible user id from member context
                        $notif_user_id = null;
                        foreach (['id_pengguna','id','id_user','id_anggota'] as $k) {
                            if (isset($memberContext['data'][$k])) { $notif_user_id = (int)$memberContext['data'][$k]; break; }
                        }

                        if ($notif_user_id && function_exists('safe_create_notification')) {
                            $title = 'Pengajuan pinjaman disetujui';
                            $message_n = 'Pengajuan pinjaman Anda sebesar ' . number_format($pendingRow['amount'], 0, ',', '.') . ' telah disetujui oleh admin.';
                            @safe_create_notification($con, $notif_user_id, 'pinjaman', $title, $message_n, json_encode(['application_id' => $pendingRow['id'], 'amount' => $pendingRow['amount']]));
                        }

                        if (function_exists('record_transaction')) {
                            $txPayload = [
                                'jenis_transaksi' => 'pinjaman_approved',
                                'jumlah' => $pendingRow['amount'],
                                'jumlah_masuk' => $pendingRow['amount'],
                                'keterangan' => 'Pinjaman disetujui oleh admin',
                            ];
                            if (!empty($notif_user_id)) {
                                $txPayload['id_anggota'] = $notif_user_id;
                                $txPayload['id_pengguna'] = $notif_user_id;
                            }
                            @record_transaction($con, $txPayload);
                        }
                    } catch (Throwable $e) {
                        // non-fatal: log and continue
                        @file_put_contents(__DIR__ . '/../../api/pinjaman/debug.log', date('Y-m-d H:i:s') . " approval_notify_tx failed: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                    }

                    return ['success' => true, 'message' => $msg];
                } catch (Throwable $e) {
                    $con->rollback();
                    return ['success' => false, 'message' => $e->getMessage()];
                }
            }

            if ($pendingRow['amount'] <= 0) {
                return ['success' => false, 'message' => 'Nominal transaksi tidak valid.'];
            }

            $memberContext = approval_seek_member($con, $schema, $pendingRow['member_value']);
            if (!$memberContext) {
                return ['success' => false, 'message' => 'Data anggota tidak ditemukan di database.'];
            }

            $con->begin_transaction();
            try {
                $depositRecorded = false;
                // Try to record deposit but do not fail approval if it fails
                try {
                    approval_record_credit($con, $schema, $pendingRow, $memberContext, $userId);
                    $depositRecorded = true;
                } catch (Throwable $e) {
                    @file_put_contents(__DIR__ . '/../../api/pinjaman/debug.log', date('Y-m-d H:i:s') . " approval_record_credit failed: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                }

                approval_update_member_balance($con, $schema, $memberContext, $pendingRow['amount']);
                approval_update_status($con, $schema, $pendingRow['id'], 'approved', $userId);
                
                // CRITICAL FIX: Also update the corresponding transaksi record if approving tabungan_masuk
                // This ensures that Riwayat Transaksi shows the transaction after approval
                try {
                    $notif_user_id = null;
                    foreach (['id_pengguna','id','id_user','id_anggota'] as $k) {
                        if (isset($memberContext['data'][$k])) { $notif_user_id = (int)$memberContext['data'][$k]; break; }
                    }
                    
                    // If this is from tabungan_masuk table, also update the transaksi table
                    if ($notif_user_id && isset($schema['table']) && $schema['table'] === 'tabungan_masuk') {
                        // Find and update corresponding transaksi record by keterangan match
                        // NOTE: transaksi is created with status='approved' by setor_manual_admin.php
                        // So we don't check for status='pending' - just update it to ensure consistency
                        $search_keterangan = '%tabungan_masuk ' . intval($pendingRow['id']) . '%';
                        $keterangan_approved = 'Setoran tabungan Anda telah disetujui. Saldo otomatis akan masuk ke rekening sesuai jenis tabungan yang dipilih.';
                        $update_stmt = $con->prepare(
                            "UPDATE transaksi SET status = 'approved', keterangan = ? WHERE id_anggota = ? AND keterangan LIKE ?"
                        );
                        if ($update_stmt) {
                            $update_stmt->bind_param('sis', $keterangan_approved, $notif_user_id, $search_keterangan);
                            @$update_stmt->execute();
                            $update_stmt->close();
                        }
                    }
                } catch (Throwable $e) {
                    // non-fatal
                }
                
                $con->commit();
                $msg = 'Transaksi berhasil di-approve';
                if ($depositRecorded) $msg .= ' dan dicatat sebagai setoran.';

                // Create notification and transaksi record for approval if helpers available
                try {
                    $notif_user_id = null;
                    foreach (['id_pengguna','id','id_user','id_anggota'] as $k) {
                        if (isset($memberContext['data'][$k])) { $notif_user_id = (int)$memberContext['data'][$k]; break; }
                    }

                    if ($notif_user_id && function_exists('safe_create_notification')) {
                        $title = 'Pengajuan disetujui';
                        $message_n = 'Permintaan Anda sebesar ' . number_format($pendingRow['amount'], 0, ',', '.') . ' telah di-approve oleh admin.';
                        @safe_create_notification($con, $notif_user_id, 'transaksi', $title, $message_n, json_encode(['application_id' => $pendingRow['id'], 'amount' => $pendingRow['amount']]));
                    }

                    if (function_exists('record_transaction')) {
                        $txPayload = [
                            'jenis_transaksi' => 'pending_approved',
                            'jumlah' => $pendingRow['amount'],
                            'jumlah_masuk' => $pendingRow['amount'],
                            'keterangan' => 'Transaksi disetujui oleh admin',
                        ];
                        if (!empty($notif_user_id)) {
                            $txPayload['id_anggota'] = $notif_user_id;
                            $txPayload['id_pengguna'] = $notif_user_id;
                        }
                        @record_transaction($con, $txPayload);
                    }
                } catch (Throwable $e) {
                    @file_put_contents(__DIR__ . '/../../api/pinjaman/debug.log', date('Y-m-d H:i:s') . " approval_notify_tx failed: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                }

                return ['success' => true, 'message' => $msg];
            } catch (Throwable $e) {
                $con->rollback();
                return ['success' => false, 'message' => $e->getMessage()];
            }
        }

        if ($action === 'reject') {
            $reason = trim($reason ?? '');
            if ($reason === '') {
                $reason = 'Tidak ada alasan';
            }

            if (approval_update_status($con, $schema, $pendingRow['id'], 'rejected', $userId, $reason)) {
                // CRITICAL FIX: Also update the corresponding transaksi record if rejecting tabungan_masuk
                try {
                    $memberContext = approval_seek_member($con, $schema, $pendingRow['member_value']);
                    $notif_user_id = null;
                    if ($memberContext && isset($memberContext['data']) && is_array($memberContext['data'])) {
                        foreach (['id_pengguna','id','id_user','id_anggota'] as $k) {
                            if (isset($memberContext['data'][$k])) { $notif_user_id = (int)$memberContext['data'][$k]; break; }
                        }
                    }
                    
                    // If this is from tabungan_masuk table, also update the transaksi table
                    if ($notif_user_id && isset($schema['table']) && $schema['table'] === 'tabungan_masuk') {
                        // Find and update corresponding transaksi record by keterangan match
                        // NOTE: transaksi is created with status='approved' by setor_manual_admin.php
                        // So we don't check for status='pending' - just update it to 'rejected'
                        $search_keterangan = '%tabungan_masuk ' . intval($pendingRow['id']) . '%';
                        $keterangan_rejected = 'Setoran tabungan Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut atau mengecek kemungkinan kesalahan.';
                        $update_stmt = $con->prepare(
                            "UPDATE transaksi SET status = 'rejected', keterangan = ? WHERE id_anggota = ? AND keterangan LIKE ?"
                        );
                        if ($update_stmt) {
                            $update_stmt->bind_param('sis', $keterangan_rejected, $notif_user_id, $search_keterangan);
                            @$update_stmt->execute();
                            $update_stmt->close();
                        }
                    }
                } catch (Throwable $e) {
                    // non-fatal
                }
                
                // Try to notify the user about rejection (non-fatal)
                try {
                    $memberContext = approval_seek_member($con, $schema, $pendingRow['member_value']);
                    $notif_user_id = null;
                    if ($memberContext && isset($memberContext['data']) && is_array($memberContext['data'])) {
                        foreach (['id_pengguna','id','id_user','id_anggota'] as $k) {
                            if (isset($memberContext['data'][$k])) { $notif_user_id = (int)$memberContext['data'][$k]; break; }
                        }
                    }

                    if (!empty($notif_user_id) && function_exists('safe_create_notification')) {
                        $isPinjaman = (isset($schema['table']) && stripos($schema['table'], 'pinjaman') !== false);
                        $title = $isPinjaman ? 'Pengajuan pinjaman ditolak' : 'Pengajuan ditolak';
                        $amount = isset($pendingRow['amount']) ? number_format($pendingRow['amount'], 0, ',', '.') : null;
                        $message_n = $isPinjaman && $amount ? ('Pengajuan pinjaman Anda sebesar ' . $amount . ' telah ditolak oleh admin.') : 'Pengajuan Anda telah ditolak.';
                        if (!empty($reason)) $message_n .= ' Alasan: ' . $reason;
                        @safe_create_notification($con, $notif_user_id, $isPinjaman ? 'pinjaman' : 'transaksi', $title, $message_n, json_encode(['application_id' => $pendingRow['id'], 'amount' => $pendingRow['amount'] ?? null]));
                        @file_put_contents(__DIR__ . '/../../api/pinjaman/debug.log', date('Y-m-d H:i:s') . " rejection_notif user={$notif_user_id} id={$pendingRow['id']}\n", FILE_APPEND | LOCK_EX);
                    }
                } catch (Throwable $e) {
                    // non-fatal: continue
                }

                return ['success' => true, 'message' => 'Transaksi berhasil di-reject'];
            }

            return ['success' => false, 'message' => 'Gagal memperbarui status penolakan.'];
        }

        return ['success' => false, 'message' => 'Aksi tidak dikenal.'];
    }
}
