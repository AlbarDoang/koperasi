<?php
/**
 * Dashboard helper utilities to keep admin and petugas views consistent
 * and resilient against schema differences between legacy and new database versions.
 */

if (!function_exists('dashboard_table_exists')) {
    function dashboard_table_exists($con, $table)
    {
        $escaped = $con->real_escape_string($table);
        $result = $con->query("SHOW TABLES LIKE '{$escaped}'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('dashboard_column_exists')) {
    function dashboard_column_exists($con, $table, $column)
    {
        if (!dashboard_table_exists($con, $table)) {
            return false;
        }
        $escaped = $con->real_escape_string($column);
        $result = $con->query("SHOW COLUMNS FROM `{$table}` LIKE '{$escaped}'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('dashboard_find_column')) {
    function dashboard_find_column($con, $table, $candidates)
    {
        foreach ($candidates as $column) {
            if (dashboard_column_exists($con, $table, $column)) {
                return $column;
            }
        }
        return null;
    }
}

if (!function_exists('dashboard_find_table')) {
    function dashboard_find_table($con, array $candidates)
    {
        foreach ($candidates as $table) {
            if (dashboard_table_exists($con, $table)) {
                return $table;
            }
        }
        return null;
    }
}

if (!function_exists('dashboard_pick_column')) {
    function dashboard_pick_column($con, $table, array $candidates, &$matchedKey = null)
    {
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $column = $candidate['column'] ?? null;
                $key = $candidate['key'] ?? $column;
            } else {
                $column = $candidate;
                $key = $candidate;
            }

            if (!$column) {
                continue;
            }

            if (dashboard_column_exists($con, $table, $column)) {
                $matchedKey = $key;
                return $column;
            }
        }

        return null;
    }
}

if (!function_exists('dashboard_get_transaction_sources')) {
    function dashboard_get_transaction_sources()
    {
        return [
            'deposit' => [
                    ['table' => 't_masuk', 'amount' => 'jumlah', 'date' => 'tanggal'],
                    ['table' => 'tabungan_masuk', 'amount' => 'jumlah', 'date' => 'created_at'],
                    ['table' => 'tabungan', 'amount' => 'jumlah', 'date' => 'tanggal', 'where' => "jenis='masuk'"],
                    // legacy generic transaksi where jumlah_masuk represents incoming amounts
                    ['table' => 'transaksi', 'amount' => 'jumlah_masuk', 'date' => 'tanggal']
            ],
            'withdraw' => [
                    ['table' => 't_keluar', 'amount' => 'jumlah', 'date' => 'tanggal'],
                    ['table' => 'tabungan_keluar', 'amount' => 'jumlah', 'date' => 'created_at'],
                    ['table' => 'tabungan', 'amount' => 'jumlah', 'date' => 'tanggal', 'where' => "jenis='keluar'"],
                    ['table' => 'transaksi', 'amount' => 'jumlah_keluar', 'date' => 'tanggal']
            ],
            'transfer' => [
                    ['table' => 't_transfer', 'amount' => 'nominal', 'date' => 'tanggal'],
                    ['table' => 'transfer', 'amount' => 'jumlah', 'date' => 'tanggal'],
                    // transaksi table may contain transfer-type records via kegiatan/type
                    ['table' => 'transaksi', 'amount' => 'jumlah_masuk', 'date' => 'tanggal', 'where' => "kegiatan='transfer' OR kegiatan='Transfer' OR kegiatan='TF'" ]
            ]
        ];
    }
}

if (!function_exists('dashboard_build_date_clause')) {
    function dashboard_build_date_clause($field, $range, $offset = 0)
    {
        $fieldExpr = sprintf('DATE(`%s`)', $field);
        switch ($range) {
            case 'today':
                return "{$fieldExpr} = CURDATE()";
            case 'week':
                return sprintf('YEARWEEK(`%s`, 1) = YEARWEEK(CURDATE(), 1)', $field);
            case 'month':
                return sprintf("DATE_FORMAT(`%s`, '%%Y-%%m') = DATE_FORMAT(CURDATE(), '%%Y-%%m')", $field);
            case 'day_offset':
                $offset = max(0, (int)$offset);
                return "{$fieldExpr} = CURDATE() - INTERVAL {$offset} DAY";
            case 'date':
                // Expect $offset to be a YYYY-MM-DD string or DateTime. Match an explicit date.
                if ($offset instanceof DateTime) {
                    $dateStr = $offset->format('Y-m-d');
                } else {
                    $dateStr = (string)$offset;
                }
                return "{$fieldExpr} = '{$dateStr}'";
            default:
                return '1=1';
        }
    }
}

if (!function_exists('dashboard_sum_transaction')) {
    function dashboard_sum_transaction($con, $type, $range = 'today', $offset = 0)
    {
        $sources = dashboard_get_transaction_sources();
        if (!isset($sources[$type])) {
            return 0.0;
        }

        // For 'transfer' summaries we MUST use the same source as the Rekap Transfer page
        // and count each logical transfer only once. Prefer t_transfer, then transfer table.
        // If neither exists, we fallback to transaksi but strictly filter to only outgoing
        // transfer records (e.g., 'Transfer Keluar') by inspecting available columns.
        $sourcesToIterate = $sources[$type];
        if ($type === 'transfer') {
            $preferred = ['t_transfer', 'transfer'];
            $selected = [];
            foreach ($preferred as $pt) {
                if (dashboard_table_exists($con, $pt)) {
                    if ($pt === 't_transfer') {
                        $selected[] = ['table' => 't_transfer', 'amount' => 'nominal', 'date' => 'tanggal'];
                    } else {
                        $selected[] = ['table' => 'transfer', 'amount' => 'jumlah', 'date' => 'tanggal'];
                    }
                    break; // use only the first preferred table found to avoid double counting
                }
            }

            if (!empty($selected)) {
                $sourcesToIterate = $selected;
            } else {
                // Fallback to transaksi but require explicit outgoing transfer indicators
                if (!dashboard_table_exists($con, 'transaksi')) return 0.0;
                $amountCol = dashboard_find_column($con, 'transaksi', ['jumlah','nominal','jumlah_masuk','jumlah_keluar','amount']);
                $dateCol = dashboard_find_column($con, 'transaksi', ['tanggal','created_at','updated_at']);
                $whereClauses = [];
                if (dashboard_column_exists($con, 'transaksi', 'jenis_transaksi')) {
                    $whereClauses[] = "(LOWER(`jenis_transaksi`) LIKE '%transfer%' AND LOWER(`jenis_transaksi`) LIKE '%keluar%')";
                }
                if (dashboard_column_exists($con, 'transaksi', 'kegiatan')) {
                    $whereClauses[] = "(LOWER(`kegiatan`) LIKE '%transfer%' AND LOWER(`kegiatan`) LIKE '%keluar%')";
                }
                if (dashboard_column_exists($con, 'transaksi', 'keterangan')) {
                    $whereClauses[] = "(LOWER(`keterangan`) LIKE '%transfer%' AND LOWER(`keterangan`) LIKE '%keluar%')";
                }

                if (empty($whereClauses) || !$amountCol || !$dateCol) {
                    // No reliable way to count transfers safely — return zero to avoid double counting or false positives
                    return 0.0;
                }

                $sourcesToIterate = [[
                    'table' => 'transaksi',
                    'amount' => $amountCol,
                    'date' => $dateCol,
                    'where' => '(' . implode(' OR ', $whereClauses) . ')'
                ]];
            }
        }

        $totalAcrossSources = 0.0;
        foreach ($sourcesToIterate as $source) {
            $table = $source['table'];
            if (!dashboard_table_exists($con, $table)) {
                continue;
            }

            $amountFieldName = $source['amount'] ?? 'jumlah';
            $amountField = sprintf('`%s`', $amountFieldName);
            $dateField = $source['date'] ?? 'tanggal';
            if (!dashboard_column_exists($con, $table, $dateField) || !dashboard_column_exists($con, $table, $amountFieldName)) {
                continue;
            }

            $whereParts = [dashboard_build_date_clause($dateField, $range, $offset)];
            if (!empty($source['where'])) {
                $whereParts[] = $source['where'];
            }

            $whereSql = implode(' AND ', $whereParts);
            $query = sprintf(
                'SELECT COALESCE(SUM(%s), 0) AS total FROM `%s` WHERE %s',
                $amountField,
                $table,
                $whereSql
            );

            if ($result = $con->query($query)) {
                $row = $result->fetch_object();
                $totalAcrossSources += (float)$row->total;
            }
        }
        // Return aggregated total across all available sources
        return (float)$totalAcrossSources;
    }
}

if (!function_exists('dashboard_format_currency')) {
    function dashboard_format_currency($value)
    {
        return 'Rp. ' . number_format($value, 0, ',', '.');
    }
}

if (!function_exists('dashboard_sum_loan_disbursements')) {
    /**
     * Sum loan disbursements (approved loans) for a given range.
     * This represents cooperative cash outflow due to approved loans.
     */
    function dashboard_sum_loan_disbursements($con, $range = 'today', $offset = 0)
    {
        $tables = ['pinjaman_biasa', 'pinjaman', 'pinjaman_kredit'];
        $total = 0.0;

        foreach ($tables as $t) {
            if (!dashboard_table_exists($con, $t)) continue;

            $amountCol = dashboard_find_column($con, $t, ['jumlah_pinjaman', 'jumlah', 'amount', 'nominal']);
            $statusCol = dashboard_find_column($con, $t, ['status', 'state', 'approval_status']);
            if (!$amountCol || !$statusCol) continue;

            // Pick a reasonable date column for range filtering (approved_at preferred)
            $dateCol = dashboard_find_column($con, $t, ['approved_at', 'approved_on', 'updated_at', 'created_at']);

            $whereParts = ["LOWER(`{$statusCol}`) IN ('approved','disetujui','diterima','accepted')"];
            if ($range === 'day_offset' && $dateCol) {
                $whereParts[] = dashboard_build_date_clause($dateCol, 'day_offset', $offset);
            } elseif ($range === 'today' && $dateCol) {
                $whereParts[] = dashboard_build_date_clause($dateCol, 'today');
            } elseif ($range === 'week' && $dateCol) {
                $whereParts[] = dashboard_build_date_clause($dateCol, 'week');
            } elseif ($range === 'month' && $dateCol) {
                $whereParts[] = dashboard_build_date_clause($dateCol, 'month');
            } elseif ($range === 'date' && $dateCol) {
                $whereParts[] = dashboard_build_date_clause($dateCol, 'date', $offset);
            }

            $whereSql = implode(' AND ', $whereParts);
            $q = sprintf('SELECT COALESCE(SUM(`%s`),0) AS total FROM `%s` WHERE %s', $amountCol, $t, $whereSql);
            if ($r = $con->query($q)) {
                $row = $r->fetch_assoc();
                $total += floatval($row['total'] ?? 0);
            }
        }

        // Fallback: if no pinjaman tables found, attempt to catch disbursements recorded in transaksi
        if ($total == 0 && dashboard_table_exists($con, 'transaksi')) {
            $amountCol = dashboard_find_column($con, 'transaksi', ['jumlah_keluar','jumlah','nominal','amount']);
            $typeCol = dashboard_find_column($con, 'transaksi', ['jenis_transaksi','kegiatan','keterangan','type']);
            if ($amountCol && $typeCol) {
                // Use a fresh date column lookup for transaksi to avoid reusing a date column discovered for other tables
                $transDateCol = dashboard_find_column($con, 'transaksi', ['tanggal','created_at','updated_at']);
                $whereParts = ["(LOWER(`{$typeCol}`) LIKE '%pinjaman%' OR LOWER(`{$typeCol}`) LIKE '%pinjaman_approved%')"];
                if ($range === 'day_offset' && $transDateCol) {
                    $whereParts[] = dashboard_build_date_clause($transDateCol, 'day_offset', $offset);
                } elseif ($range === 'today' && $transDateCol) {
                    $whereParts[] = dashboard_build_date_clause($transDateCol, 'today');
                } elseif ($range === 'week' && $transDateCol) {
                    $whereParts[] = dashboard_build_date_clause($transDateCol, 'week');
                } elseif ($range === 'month' && $transDateCol) {
                    $whereParts[] = dashboard_build_date_clause($transDateCol, 'month');
                }
                $q = sprintf('SELECT COALESCE(SUM(`%s`),0) AS total FROM `transaksi` WHERE %s', $amountCol, implode(' AND ', $whereParts));
                if ($r = $con->query($q)) { $row = $r->fetch_assoc(); $total += floatval($row['total'] ?? 0); }
            }
        }

        return (float)$total;
    }
}

if (!function_exists('dashboard_generate_chart_payload')) {
    function dashboard_generate_chart_payload($con, $days = 6)
    {
        // Fixed week: Monday -> Sunday (7 days always)
        $labels = [];
        $deposit = [];
        $withdraw = [];
        $transfer = [];

        // Determine Monday of current week (ISO week with Monday start)
        $monday = new DateTime('monday this week');
        // Ensure we use date-only values (no time component)
        $monday->setTime(0, 0, 0);

        $dateKeys = [];
        for ($i = 0; $i < 7; $i++) {
            $d = clone $monday;
            $d->modify("+{$i} days");
            // X-axis label should be the calendar date (DD/MM/YYYY)
            $labels[] = $d->format('d/m/Y');

            $dateYmd = $d->format('Y-m-d');
            $dateKeys[] = $dateYmd;

            // Use explicit date matching to ensure exact mapping. If no data exists for that date, helpers return 0.
            $deposit[] = round(dashboard_sum_transaction($con, 'deposit', 'date', $dateYmd), 2);
            $withdraw[] = round(dashboard_sum_loan_disbursements($con, 'date', $dateYmd), 2);
            // Transfers intentionally zeroed as before
            $transfer[] = 0.0;
        }

        return [
            'labels' => $labels,
            'date_keys' => $dateKeys,
            'deposit' => $deposit,
            'withdraw' => $withdraw,
            'transfer' => $transfer,
        ];
    }
}

if (!function_exists('dashboard_count_active_members')) {
    function dashboard_count_active_members($con)
    {
        // Cek apakah tabel pengguna ada (untuk user yang sudah disetujui/approved)
        if (dashboard_table_exists($con, 'pengguna')) {
            $query = "SELECT COUNT(*) AS total FROM pengguna WHERE LOWER(status_akun) = 'approved'";
            if ($result = $con->query($query)) {
                $row = $result->fetch_assoc();
                return (int)($row['total'] ?? 0);
            }
        }
        
        // Fallback ke tabel siswa
        if (dashboard_table_exists($con, 'pengguna')) {
            $query = "SELECT COUNT(*) AS total FROM pengguna WHERE status != 'pending'";
            if ($result = $con->query($query)) {
                $row = $result->fetch_assoc();
                return (int)($row['total'] ?? 0);
            }
        }

        return 0;
    }
}

if (!function_exists('dashboard_total_koperasi_balance')) {
    /**
     * Compute Total Saldo Koperasi using the accounting-safe definition:
     *   Total Saldo Koperasi = Total Tabungan Masuk (Top-ups)
     *                           - Total Loan Disbursements (approved loans)
     *                           - External Withdrawals (none currently)
     *
     * This intentionally EXCLUDES internal movements like pencairan and transfers.
     */
    function dashboard_total_koperasi_balance($con)
    {
        // 1) Sum all top-up / tabungan masuk sources (all-time)
        $depositSources = dashboard_get_transaction_sources()['deposit'] ?? [];
        $totalDeposits = 0.0;

        foreach ($depositSources as $source) {
            $table = $source['table'];
            if (!dashboard_table_exists($con, $table)) continue;

            $amountFieldName = $source['amount'] ?? 'jumlah';
            if (!dashboard_column_exists($con, $table, $amountFieldName)) continue;

            $whereParts = [];
            // Preserve any source-specific where clauses (e.g., jenis='masuk')
            if (!empty($source['where'])) $whereParts[] = $source['where'];

            // For tabungan_masuk prefer only confirmed/topups
            if ($table === 'tabungan_masuk' && dashboard_column_exists($con, $table, 'status')) {
                $whereParts[] = "`status` = 'berhasil'";
            }

            // For generic transaksi deposit sources attempt to limit to deposit-like activities
            if ($table === 'transaksi') {
                $typeCol = dashboard_find_column($con, 'transaksi', ['jenis_transaksi','kegiatan','keterangan','type']);
                if ($typeCol) {
                    $whereParts[] = "(LOWER(`{$typeCol}`) LIKE '%topup%' OR LOWER(`{$typeCol}`) LIKE '%setoran%' OR LOWER(`{$typeCol}`) LIKE '%deposit%' OR LOWER(`{$typeCol}`) LIKE '%masuk%')";
                }
            }

            $whereSql = empty($whereParts) ? '1=1' : implode(' AND ', $whereParts);
            $q = sprintf('SELECT COALESCE(SUM(`%s`),0) AS total FROM `%s` WHERE %s', $amountFieldName, $table, $whereSql);
            if ($r = $con->query($q)) {
                $row = $r->fetch_assoc();
                $totalDeposits += floatval($row['total'] ?? 0);
            }
        }

        // 2) Sum all approved loan disbursements (all-time)
        $loanTables = ['pinjaman_biasa', 'pinjaman', 'pinjaman_kredit'];
        $totalLoans = 0.0;
        $foundLoanTable = false;

        foreach ($loanTables as $lt) {
            if (!dashboard_table_exists($con, $lt)) continue;
            $amountCol = dashboard_find_column($con, $lt, ['jumlah_pinjaman', 'jumlah', 'amount', 'nominal']);
            $statusCol = dashboard_find_column($con, $lt, ['status', 'state', 'approval_status']);
            if (!$amountCol || !$statusCol) continue;
            $foundLoanTable = true;

            $q = sprintf("SELECT COALESCE(SUM(`%s`),0) AS total FROM `%s` WHERE LOWER(`%s`) IN ('approved','disetujui','diterima','accepted')", $amountCol, $lt, $statusCol);
            if ($r = $con->query($q)) { $row = $r->fetch_assoc(); $totalLoans += floatval($row['total'] ?? 0); }
        }

        // Fallback: if no loan tables exist, attempt to infer disbursements from transaksi records
        if (!$foundLoanTable && dashboard_table_exists($con, 'transaksi')) {
            $amountCol = dashboard_find_column($con, 'transaksi', ['jumlah_keluar','jumlah','nominal','amount']);
            $typeCol = dashboard_find_column($con, 'transaksi', ['jenis_transaksi','kegiatan','keterangan','type']);
            if ($amountCol && $typeCol) {
                $q = sprintf("SELECT COALESCE(SUM(`%s`),0) AS total FROM `transaksi` WHERE (LOWER(`%s`) LIKE '%s' OR LOWER(`%s`) LIKE '%s')", $amountCol, $typeCol, '%pinjaman%', $typeCol, '%pinjaman%');
                if ($r = $con->query($q)) { $row = $r->fetch_assoc(); $totalLoans += floatval($row['total'] ?? 0); }
            }
        }

        // 3) Compute cooperative balance per rules
        $ledgerTotal = $totalDeposits - $totalLoans;

        // 4) As a safety fallback (legacy): if no deposit sources found, try previous ledger approaches
        if ($totalDeposits == 0.0) {
            // revert to earlier behavior but still prefer explicit pinjaman subtraction if available
            // Try t_masuk/t_keluar ledger calculation
            if (dashboard_table_exists($con, 't_masuk') && dashboard_table_exists($con, 't_keluar')) {
                $inAmount = dashboard_find_column($con, 't_masuk', ['jumlah', 'nominal', 'amount']);
                $outAmount = dashboard_find_column($con, 't_keluar', ['jumlah', 'nominal', 'amount']);
                if ($inAmount && $outAmount) {
                    $qIn = sprintf('SELECT COALESCE(SUM(`%s`),0) AS total_masuk FROM `t_masuk`', $inAmount);
                    $qOut = sprintf('SELECT COALESCE(SUM(`%s`),0) AS total_keluar FROM `t_keluar`', $outAmount);
                    $in = 0.0; $out = 0.0;
                    if ($r = $con->query($qIn)) { $row = $r->fetch_assoc(); $in = floatval($row['total_masuk'] ?? 0); }
                    if ($r2 = $con->query($qOut)) { $row2 = $r2->fetch_assoc(); $out = floatval($row2['total_keluar'] ?? 0); }
                    $total = $in - $out - $totalLoans;
                    return (float)$total;
                }
            }

            // fallback: transaksi totals
            if (dashboard_table_exists($con, 'transaksi')) {
                if (dashboard_column_exists($con, 'transaksi', 'jumlah_masuk') && dashboard_column_exists($con, 'transaksi', 'jumlah_keluar')) {
                    $q = "SELECT COALESCE(SUM(COALESCE(jumlah_masuk,0)),0) AS total_masuk, COALESCE(SUM(COALESCE(jumlah_keluar,0)),0) AS total_keluar FROM transaksi";
                    if ($r = $con->query($q)) {
                        $row = $r->fetch_assoc();
                        $total = floatval($row['total_masuk'] ?? 0) - floatval($row['total_keluar'] ?? 0) - $totalLoans;
                        return (float)$total;
                    }
                }
            }

            // fallback: sum pengguna.saldo
            if (dashboard_table_exists($con, 'pengguna') && dashboard_column_exists($con, 'pengguna', 'saldo')) {
                $conditions = [];
                if (dashboard_column_exists($con, 'pengguna', 'status')) {
                    $conditions[] = "status='aktif'";
                }
                $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
                $query = "SELECT COALESCE(SUM(saldo), 0) AS total_saldo FROM pengguna {$where}";
                if ($result = $con->query($query)) {
                    $row = $result->fetch_assoc();
                    return (float)($row['total_saldo'] ?? 0);
                }
            }
        }

        return (float)$ledgerTotal;
    }

    if (!function_exists('dashboard_total_koperasi_balance_details')) {
        function dashboard_total_koperasi_balance_details($con)
        {
            $ledgerTotal = dashboard_total_koperasi_balance($con);
            $penggunaTotal = 0.0;
            if (dashboard_table_exists($con, 'pengguna') && dashboard_column_exists($con, 'pengguna', 'saldo')) {
                $conditions = [];
                if (dashboard_column_exists($con, 'pengguna', 'status')) {
                    $conditions[] = "status='aktif'";
                }
                $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
                $query = "SELECT COALESCE(SUM(saldo), 0) AS total_saldo FROM pengguna {$where}";
                if ($result = $con->query($query)) {
                    $row = $result->fetch_assoc();
                    $penggunaTotal = (float)($row['total_saldo'] ?? 0);
                }
            }
            return ['ledger' => $ledgerTotal, 'pengguna' => $penggunaTotal, 'diff' => $ledgerTotal - $penggunaTotal];
        }

    }

    if (!function_exists('dashboard_total_koperasi_components')) {
        /**
         * Return explicit components used to compute Total Saldo Koperasi
         * (deposits and loan disbursements) to aid auditing and debugging.
         */
        function dashboard_total_koperasi_components($con)
        {
            $depositSources = dashboard_get_transaction_sources()['deposit'] ?? [];
            $totalDeposits = 0.0;

            foreach ($depositSources as $source) {
                $table = $source['table'];
                if (!dashboard_table_exists($con, $table)) continue;

                $amountFieldName = $source['amount'] ?? 'jumlah';
                if (!dashboard_column_exists($con, $table, $amountFieldName)) continue;

                $whereParts = [];
                if (!empty($source['where'])) $whereParts[] = $source['where'];
                if ($table === 'tabungan_masuk' && dashboard_column_exists($con, $table, 'status')) {
                    $whereParts[] = "`status` = 'berhasil'";
                }

                $whereSql = empty($whereParts) ? '1=1' : implode(' AND ', $whereParts);
                $q = sprintf('SELECT COALESCE(SUM(`%s`),0) AS total FROM `%s` WHERE %s', $amountFieldName, $table, $whereSql);
                if ($r = $con->query($q)) { $row = $r->fetch_assoc(); $totalDeposits += floatval($row['total'] ?? 0); }
            }

            // loans
            $loanTables = ['pinjaman_biasa', 'pinjaman', 'pinjaman_kredit'];
            $totalLoans = 0.0;
            foreach ($loanTables as $lt) {
                if (!dashboard_table_exists($con, $lt)) continue;
                $amountCol = dashboard_find_column($con, $lt, ['jumlah_pinjaman', 'jumlah', 'amount', 'nominal']);
                $statusCol = dashboard_find_column($con, $lt, ['status', 'state', 'approval_status']);
                if (!$amountCol || !$statusCol) continue;
                $q = sprintf("SELECT COALESCE(SUM(`%s`),0) AS total FROM `%s` WHERE LOWER(`%s`) IN ('approved','disetujui','diterima','accepted')", $amountCol, $lt, $statusCol);
                if ($r = $con->query($q)) { $row = $r->fetch_assoc(); $totalLoans += floatval($row['total'] ?? 0); }
            }

            return ['deposits' => $totalDeposits, 'loans' => $totalLoans, 'balance' => $totalDeposits - $totalLoans];
        }
    }
}

if (!function_exists('dashboard_total_transactions')) {
    function dashboard_total_transactions($con)
    {
        $tables = ['transaksi', 't_masuk', 't_keluar', 't_transfer', 'tabungan', 'tabungan_masuk', 'tabungan_keluar', 'transfer'];
        $total = 0;
        foreach ($tables as $t) {
            if (!dashboard_table_exists($con, $t)) continue;
            $q = sprintf('SELECT COUNT(*) AS cnt FROM `%s`', $t);
            if ($r = $con->query($q)) {
                $row = $r->fetch_assoc();
                $total += intval($row['cnt'] ?? 0);
            }
        }
        return $total;
    }
}

if (!function_exists('dashboard_total_pending_topups')) {
    function dashboard_total_pending_topups($con)
    {
        // Sources for pending top-ups include a few legacy and current tables. We normalize
        // various "pending"-like status values from different schemas and also ensure
        // tabungan (generic ledger) only counts masuk (incoming) rows.
        $candidates = ['pending_transactions', 'pending_transaksi', 'mulai_nabung', 'tabungan_masuk', 't_masuk', 'tabungan'];
        $totalPending = 0.0;

        // Accept several variants of pending-like states (all compared lowercase)
        $pendingStates = ['pending', 'menunggu_admin', 'menunggu_penyerahan', 'menunggu', 'waiting'];
        $pendingStatesSql = implode("','", $pendingStates);

        foreach ($candidates as $table) {
            if (!dashboard_table_exists($con, $table)) continue;

            $amountCol = dashboard_find_column($con, $table, ['nominal', 'jumlah', 'amount', 'total']);
            // status column may have different names across schemas
            $statusCol = dashboard_find_column($con, $table, ['status', 'state', 'approval_status']);

            // If we don't have an amount column we cannot meaningfully sum
            if (!$amountCol) continue;

            $whereParts = [];
            if ($statusCol) {
                $whereParts[] = sprintf("LOWER(`%s`) IN ('%s')", $statusCol, $pendingStatesSql);
            }

            // For generic 'tabungan' table only consider incoming records
            if ($table === 'tabungan' && dashboard_column_exists($con, $table, 'jenis')) {
                $whereParts[] = "`jenis` = 'masuk'";
            }

            // If no status column and no extra restriction, skip this table
            if (empty($whereParts)) continue;

            $whereSql = implode(' AND ', $whereParts);
            $sql = sprintf("SELECT COALESCE(SUM(%s), 0) AS total_pending FROM `%s` WHERE %s", $amountCol, $table, $whereSql);

            if ($r = $con->query($sql)) {
                $row = $r->fetch_assoc();
                $totalPending += floatval($row['total_pending'] ?? 0);
            }
        }

        return $totalPending;
    }
}

// New: count pending user activation requests
if (!function_exists('dashboard_count_pending_activations')) {
    function dashboard_count_pending_activations($con)
    {
        if (!dashboard_table_exists($con, 'pengguna')) return 0;
        $query = "SELECT COUNT(*) AS total FROM pengguna WHERE LOWER(status_akun) = 'pending'";
        if ($result = $con->query($query)) {
            $row = $result->fetch_assoc();
            return (int)($row['total'] ?? 0);
        }
        return 0;
    }
}



if (!function_exists('dashboard_transaction_summary')) {
    function dashboard_transaction_summary($con)
    {
        $types = ['deposit', 'withdraw', 'transfer'];
        $ranges = ['today', 'week', 'month'];
        $summary = [];

        foreach ($types as $type) {
            foreach ($ranges as $range) {
                $summary[$type][$range] = dashboard_sum_transaction($con, $type, $range);
            }
        }

        return $summary;
    }
}

if (!function_exists('dashboard_collect_kpis')) {
    function dashboard_collect_kpis($con)
    {
        $balanceDetails = dashboard_total_koperasi_balance_details($con);
        return [
            'members' => dashboard_count_active_members($con),
            'balance' => $balanceDetails['ledger'] ?? dashboard_total_koperasi_balance($con),
            'balance_pengguna' => $balanceDetails['pengguna'] ?? null,
            'balance_diff' => $balanceDetails['diff'] ?? 0,
            'pending_topups' => dashboard_total_pending_topups($con),
            
            'transactions' => dashboard_total_transactions($con),
            'staff' => 0,
        ];
    }
}
