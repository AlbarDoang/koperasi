<?php
require __DIR__ . '/../gas_web/flutter_api/connection.php';

// Detect whether mulai_nabung has id_jenis_tabungan column
$has_col_res = $connect->query("SHOW COLUMNS FROM mulai_nabung LIKE 'id_jenis_tabungan'");
$has_id_jenis_in_mulai = ($has_col_res && $has_col_res->num_rows > 0);

// Detect whether pengguna has id_tabungan column (schemas vary)
$has_pengguna_col_res = $connect->query("SHOW COLUMNS FROM pengguna LIKE 'id_tabungan'");
$has_id_tabungan_in_pengguna = ($has_pengguna_col_res && $has_pengguna_col_res->num_rows > 0);

// Build pengguna join clause depending on column availability
if ($has_id_tabungan_in_pengguna) {
    $pengguna_join = "LEFT JOIN pengguna p ON p.id_tabungan IS NOT NULL AND p.id_tabungan = m.id_tabungan";
} else {
    // Some schemas store the user id in m.id_tabungan directly; fall back to joining by p.id
    $pengguna_join = "LEFT JOIN pengguna p ON p.id = CAST(m.id_tabungan AS UNSIGNED)";
}

// Determine pengguna name column (various schemas use different column names)
$pengguna_cols_res = $connect->query("SHOW COLUMNS FROM pengguna");
$pengguna_columns = [];
if ($pengguna_cols_res) {
    while ($pc = $pengguna_cols_res->fetch_assoc()) {
        $pengguna_columns[] = $pc['Field'];
    }
}
$pengguna_name_col = null;
foreach (['nama', 'name', 'nama_lengkap', 'nama_pengguna', 'full_name'] as $candidate) {
    if (in_array($candidate, $pengguna_columns)) {
        $pengguna_name_col = $candidate;
        break;
    }
}
$nama_pengguna_expr = $pengguna_name_col ? "COALESCE(p.$pengguna_name_col, '')" : "''";

if ($has_id_jenis_in_mulai) {
    $sql = <<<SQL
SELECT
  agg.id_pengguna,
  {$nama_pengguna_expr} AS nama_pengguna,
  agg.id_jenis_tabungan,
  COALESCE(j.nama_jenis, '') AS nama_jenis,
  agg.total_masuk,
  agg.total_keluar,
  (agg.total_masuk - agg.total_keluar) AS saldo
FROM (
  SELECT
    t.id_pengguna,
    t.id_jenis_tabungan,
    SUM(t.total_in) AS total_masuk,
    SUM(t.total_out) AS total_keluar
  FROM (
    SELECT
      COALESCE(p.id, CAST(m.id_tabungan AS UNSIGNED), 0) AS id_pengguna,
      COALESCE(m.id_jenis_tabungan, 0) AS id_jenis_tabungan,
      SUM(m.jumlah) AS total_in,
      0 AS total_out
    FROM mulai_nabung m
    {$pengguna_join}
    WHERE m.status = 'berhasil'
    GROUP BY id_pengguna, id_jenis_tabungan

    UNION ALL

    SELECT id_pengguna, id_jenis_tabungan, 0 AS total_in, SUM(jumlah) AS total_out
    FROM tabungan_keluar
    GROUP BY id_pengguna, id_jenis_tabungan
  ) t
  GROUP BY t.id_pengguna, t.id_jenis_tabungan
) agg
LEFT JOIN pengguna p ON p.id = agg.id_pengguna
LEFT JOIN jenis_tabungan j ON j.id = agg.id_jenis_tabungan
WHERE (agg.total_masuk - agg.total_keluar) < 0
ORDER BY (agg.total_masuk - agg.total_keluar) ASC;
SQL;
} else {
    // Fallback: resolve jenis by text matching to jenis_tabungan.nama_jenis
    $sql = <<<SQL
SELECT
  agg.id_pengguna,
  {$nama_pengguna_expr} AS nama_pengguna,
  agg.id_jenis_tabungan,
  COALESCE(j.nama_jenis, '') AS nama_jenis,
  agg.total_masuk,
  agg.total_keluar,
  (agg.total_masuk - agg.total_keluar) AS saldo
FROM (
  SELECT
    t.id_pengguna,
    t.id_jenis_tabungan,
    SUM(t.total_in) AS total_masuk,
    SUM(t.total_out) AS total_keluar
  FROM (
    SELECT
      COALESCE(p.id, CAST(m.id_tabungan AS UNSIGNED), 0) AS id_pengguna,
      j.id AS id_jenis_tabungan,
      SUM(m.jumlah) AS total_in,
      0 AS total_out
    FROM mulai_nabung m
    JOIN jenis_tabungan j ON (
      m.jenis_tabungan COLLATE utf8mb4_unicode_ci = j.nama_jenis COLLATE utf8mb4_unicode_ci
      OR m.jenis_tabungan COLLATE utf8mb4_unicode_ci LIKE CONCAT('%', j.nama_jenis COLLATE utf8mb4_unicode_ci, '%')
    )
    {$pengguna_join}
    WHERE m.status = 'berhasil'
    GROUP BY id_pengguna, j.id

    UNION ALL

    SELECT id_pengguna, id_jenis_tabungan, 0 AS total_in, SUM(jumlah) AS total_out
    FROM tabungan_keluar
    GROUP BY id_pengguna, id_jenis_tabungan
  ) t
  GROUP BY t.id_pengguna, t.id_jenis_tabungan
) agg
LEFT JOIN pengguna p ON p.id = agg.id_pengguna
LEFT JOIN jenis_tabungan j ON j.id = agg.id_jenis_tabungan
WHERE (agg.total_masuk - agg.total_keluar) < 0
ORDER BY (agg.total_masuk - agg.total_keluar) ASC;
SQL;
}

$res = $connect->query($sql);
if (!$res) {
    echo "Query failed: " . $connect->error . "\n";
    exit(1);
}
$rows = [];
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
}

echo json_encode(['count' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents(__DIR__ . '/audit_negative_balances.json', json_encode(['count' => count($rows), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

?>