-- Create table for wallet ledger
-- Run once: mysql -u <user> -p <dbname> < create_wallet_ledger_table.sql

CREATE TABLE IF NOT EXISTS `wallet_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `related_user_id` INT NULL,
  `payment_request_id` INT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `type` ENUM('debit','credit') NOT NULL,
  `balance_before` DECIMAL(15,2) NOT NULL,
  `balance_after` DECIMAL(15,2) NOT NULL,
  `description` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`user_id`),
  INDEX (`payment_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
