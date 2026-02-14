-- =========================================================
-- Migration: Add no_transaksi column to transaksi table
-- Database: tabungan
-- Date: 2026-02-12
-- =========================================================
-- IMPORTANT: This does NOT modify any existing column.
-- It ONLY adds a new column: no_transaksi
-- =========================================================

ALTER TABLE `transaksi`
ADD COLUMN `no_transaksi` VARCHAR(50) DEFAULT NULL AFTER `id_transaksi`;

-- Add unique index (allow NULL for existing rows without no_transaksi)
ALTER TABLE `transaksi`
ADD UNIQUE INDEX `idx_no_transaksi` (`no_transaksi`);

-- =========================================================
-- Backfill existing rows with generated no_transaksi
-- =========================================================
UPDATE `transaksi`
SET `no_transaksi` = CONCAT(
    CASE `jenis_transaksi`
        WHEN 'setoran'           THEN 'SAV'
        WHEN 'penarikan'         THEN 'WDR'
        WHEN 'transfer_keluar'   THEN 'KRM'
        WHEN 'transfer_masuk'    THEN 'KRM'
        WHEN 'pinjaman_biasa'    THEN 'LON'
        WHEN 'pinjaman_kredit'   THEN 'CRD'
        ELSE 'TRX'
    END,
    '-',
    DATE_FORMAT(`tanggal`, '%Y%m%d'),
    '-',
    LPAD(`id_transaksi`, 6, '0')
)
WHERE `no_transaksi` IS NULL;

-- =========================================================
-- END OF MIGRATION
-- =========================================================
