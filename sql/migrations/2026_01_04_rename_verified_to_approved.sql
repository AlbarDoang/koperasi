-- Migration: Rename 'verified' / 'TERVERIFIKASI' to 'approved' and update ENUM
-- Run in a safe migration environment and backup DB before applying.
START TRANSACTION;

-- Normalize existing values
UPDATE pengguna SET status_akun = 'approved' WHERE LOWER(status_akun) IN ('verified','terverifikasi');

-- Modify ENUM to the canonical set (adjust default if necessary)
ALTER TABLE pengguna
  MODIFY COLUMN status_akun ENUM('draft','submitted','pending','approved','rejected') NOT NULL DEFAULT 'pending';

COMMIT;

-- Rollback (manual): If you need to revert, run:
-- UPDATE pengguna SET status_akun = 'verified' WHERE LOWER(status_akun) = 'approved' AND /* place conditions to limit to previously-changed rows */ 0;
-- And restore table schema from backup.
