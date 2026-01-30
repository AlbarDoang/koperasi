-- =====================================================
-- SQL: Tambah/Fix kolom jenis_tabungan ke tabel mulai_nabung
-- Database: tabungan
-- =====================================================

-- Option 1: Jika kolom belum ada sama sekali
ALTER TABLE `mulai_nabung` 
ADD COLUMN IF NOT EXISTS `jenis_tabungan` VARCHAR(100) DEFAULT 'Tabungan Reguler' AFTER `jumlah`;

-- Option 2: Jika kolom sudah ada tapi tipe INT (salah), ubah ke VARCHAR
ALTER TABLE `mulai_nabung` 
MODIFY COLUMN `jenis_tabungan` VARCHAR(100) DEFAULT 'Tabungan Reguler';

-- Update data lama yang nilai 0 menjadi default
UPDATE `mulai_nabung` 
SET `jenis_tabungan` = 'Tabungan Reguler' 
WHERE `jenis_tabungan` = '0' OR `jenis_tabungan` IS NULL OR `jenis_tabungan` = '';

-- Verifikasi kolom sudah benar
DESCRIBE `mulai_nabung` `jenis_tabungan`;
SELECT * FROM `mulai_nabung` LIMIT 3;
