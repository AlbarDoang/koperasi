-- Migration: add cicilan_per_bulan to pinjaman_biasa
-- Safe: only adds column if it does not exist (requires MySQL 8+)
ALTER TABLE `pinjaman_biasa`
  ADD COLUMN IF NOT EXISTS `cicilan_per_bulan` BIGINT UNSIGNED NOT NULL DEFAULT 0;

-- Fallback: if your MySQL does not support ADD COLUMN IF NOT EXISTS, run the following only if the column does not exist:
-- ALTER TABLE `pinjaman_biasa` ADD COLUMN `cicilan_per_bulan` BIGINT UNSIGNED NOT NULL DEFAULT 0;