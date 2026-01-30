-- SQL: Add missing columns to tabungan_masuk table (PRODUCTION READY)
-- For admin setor manual feature to work properly with get_riwayat_tabungan API
-- 
-- Usage: 
--   mysql -u root tabungan < add_columns_tabungan_masuk.sql
-- OR:
--   Copy-paste ke phpMyAdmin SQL tab, then Execute

-- Add tanggal column if not exists
ALTER TABLE `tabungan_masuk` 
ADD COLUMN IF NOT EXISTS `tanggal` DATE NULL AFTER `jumlah`;

-- Add jenis_tabungan column (string name, not ID) if not exists
ALTER TABLE `tabungan_masuk` 
ADD COLUMN IF NOT EXISTS `jenis_tabungan` VARCHAR(100) NULL AFTER `tanggal`;

-- Add sumber column if not exists (for tracking: admin_manual, mulai_nabung, etc)
ALTER TABLE `tabungan_masuk` 
ADD COLUMN IF NOT EXISTS `sumber` VARCHAR(50) NULL DEFAULT 'admin_manual' AFTER `jenis_tabungan`;

-- Add status column if not exists (approved, berhasil, pending, etc)
ALTER TABLE `tabungan_masuk` 
ADD COLUMN IF NOT EXISTS `status` VARCHAR(50) NULL DEFAULT 'approved' AFTER `sumber`;

-- Add admin_id column if not exists (track which admin did the setor)
ALTER TABLE `tabungan_masuk` 
ADD COLUMN IF NOT EXISTS `admin_id` BIGINT NULL AFTER `status`;

-- Add index untuk performance if not exists
ALTER TABLE `tabungan_masuk` 
ADD INDEX IF NOT EXISTS `idx_jenis_tabungan_tanggal` (`jenis_tabungan`, `tanggal`);

-- Verify columns exist
DESCRIBE `tabungan_masuk`;

-- Show sample data structure after update
SELECT 
    id, 
    id_pengguna, 
    id_jenis_tabungan, 
    jumlah, 
    tanggal, 
    jenis_tabungan, 
    sumber, 
    status, 
    admin_id, 
    created_at, 
    updated_at
FROM `tabungan_masuk` 
LIMIT 1;
