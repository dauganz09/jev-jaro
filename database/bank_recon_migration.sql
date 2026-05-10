-- Bank Reconciliation (BRS) migration
-- Generated: 2026-05-09
--
-- Apply in phpMyAdmin / CLI. Backup first.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `tbl_bank_accounts` (
  `bank_account_id` INT(11) NOT NULL AUTO_INCREMENT,
  `brgy_id` INT(11) NOT NULL,
  `bank_name` VARCHAR(150) NOT NULL,
  `branch` VARCHAR(150) NOT NULL DEFAULT '',
  `account_no` VARCHAR(80) NOT NULL,
  `account_name` VARCHAR(150) NOT NULL,
  `cash_in_bank_acc_code` VARCHAR(20) NOT NULL DEFAULT '10102020',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`bank_account_id`),
  KEY `idx_bank_accounts_brgy_active` (`brgy_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbl_bank_recon` (
  `recon_id` INT(11) NOT NULL AUTO_INCREMENT,
  `brgy_id` INT(11) NOT NULL,
  `bank_account_id` INT(11) NOT NULL,
  `period_year` INT(11) NOT NULL,
  `period_month` INT(11) NOT NULL,
  `statement_ending_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `book_ending_balance` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `statement_as_of_date` DATE NULL DEFAULT NULL,
  `explanatory_comment` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_updated` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`recon_id`),
  UNIQUE KEY `uniq_recon_brgy_bank_period` (`brgy_id`,`bank_account_id`,`period_year`,`period_month`),
  KEY `idx_recon_bank_period` (`bank_account_id`,`period_year`,`period_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbl_bank_statement_lines` (
  `statement_line_id` INT(11) NOT NULL AUTO_INCREMENT,
  `recon_id` INT(11) NOT NULL,
  `txn_date` DATE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `reference` VARCHAR(120) DEFAULT NULL,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`statement_line_id`),
  KEY `idx_statement_recon_date` (`recon_id`,`txn_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbl_bank_recon_matches` (
  `match_id` INT(11) NOT NULL AUTO_INCREMENT,
  `recon_id` INT(11) NOT NULL,
  `statement_line_id` INT(11) NOT NULL,
  `jevdata_id` INT(11) NOT NULL,
  `matched_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`match_id`),
  UNIQUE KEY `uniq_stmt_jev_match` (`statement_line_id`,`jevdata_id`),
  KEY `idx_match_recon` (`recon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tbl_bank_recon_items` (
  `recon_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `recon_id` INT(11) NOT NULL,
  `item_type` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  `reference` VARCHAR(120) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `linked_jev_id` INT(11) DEFAULT NULL,
  `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`recon_item_id`),
  KEY `idx_recon_items` (`recon_id`,`item_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Link JEVs to bank account master (optional but recommended)
ALTER TABLE `tbl_jev`
  ADD COLUMN IF NOT EXISTS `bank_account_id` INT(11) NULL AFTER `fund`,
  ADD KEY IF NOT EXISTS `idx_jev_bank` (`bank_account_id`);

ALTER TABLE `tbl_jevdata`
  ADD COLUMN IF NOT EXISTS `bank_account_id` INT(11) NULL AFTER `bank_acct`,
  ADD KEY IF NOT EXISTS `idx_jevdata_bank` (`bank_account_id`);

COMMIT;

