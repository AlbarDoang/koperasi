-- Migration: Normalize pinjaman columns
-- Purpose: Ensure `jumlah_pinjaman` and `status` are used consistently.
-- IMPORTANT: Take a backup before running.

-- Example manual steps (MySQL 8+ supports IF NOT EXISTS but older versions may not):
-- 1) Rename columns if needed (uncomment if you are sure):
-- ALTER TABLE pinjaman CHANGE COLUMN `jumlah` `jumlah_pinjaman` BIGINT NULL;
-- ALTER TABLE pinjaman CHANGE COLUMN `status_pinjaman` `status` VARCHAR(32) NULL;

-- 2) Copy values if both columns exist:
UPDATE pinjaman SET jumlah_pinjaman = jumlah WHERE (jumlah_pinjaman IS NULL OR jumlah_pinjaman = 0) AND (jumlah IS NOT NULL AND jumlah <> 0);
UPDATE pinjaman SET status = status_pinjaman WHERE (status IS NULL OR TRIM(status) = '') AND (status_pinjaman IS NOT NULL AND TRIM(status_pinjaman) <> '');

-- 3) Normalize empty status to 'pending'
UPDATE pinjaman SET status = 'pending' WHERE status IS NULL OR TRIM(status) = '';

-- 4) Optional: inspect legacy columns
SELECT 'legacy jumlah rows' as note, COUNT(*) as cnt FROM pinjaman WHERE jumlah IS NOT NULL AND (jumlah_pinjaman IS NULL OR jumlah_pinjaman = 0);
SELECT 'legacy status_pinjaman rows' as note, COUNT(*) as cnt FROM pinjaman WHERE status_pinjaman IS NOT NULL AND (status IS NULL OR TRIM(status) = '');

-- 5) After review, you may drop legacy columns (manual step):
-- ALTER TABLE pinjaman DROP COLUMN jumlah;
-- ALTER TABLE pinjaman DROP COLUMN status_pinjaman;

-- Notes:
-- Run using mysql client: mysql -u user -p your_database < 2025_12_28_normalize_pinjaman_columns.sql
-- Alternatively run the PHP helper script: php scripts/migrate_normalize_pinjaman.php
