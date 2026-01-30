-- Migration: Add api_token column to pengguna table
-- Run: mysql -u user -p your_database < 2025_12_27_add_pengguna_api_token.sql

ALTER TABLE `pengguna`
  ADD COLUMN `api_token` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  ADD UNIQUE INDEX `ux_pengguna_api_token` (`api_token`);

-- Notes:
-- 1) Ensure you have a backup before running this migration.
-- 2) Index enforces uniqueness; if you prefer non-unique tokens, remove UNIQUE.
-- 3) You may also want to add a `api_token_created_at` DATETIME column in the future.
