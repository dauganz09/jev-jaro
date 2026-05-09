-- COA-grade Subsidiary Ledger support migration
-- Generated: 2026-05-09
--
-- Apply this in your MariaDB database (phpMyAdmin / CLI).
-- Recommended: take a DB backup first.

START TRANSACTION;

-- 1) Master list of subsidiaries (employees, suppliers, agencies, taxpayers, funds, etc.)
CREATE TABLE IF NOT EXISTS `tbl_subsidiaries` (
  `subsidiary_id` INT(11) NOT NULL AUTO_INCREMENT,
  `subsidiary_type` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `external_id` VARCHAR(100) DEFAULT NULL,
  `tin` VARCHAR(50) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subsidiary_id`),
  KEY `idx_subsidiaries_type_name` (`subsidiary_type`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Link JEV line items to subsidiaries (for SL/SS)
ALTER TABLE `tbl_jevdata`
  ADD COLUMN IF NOT EXISTS `subsidiary_id` INT(11) NULL AFTER `acc_code`,
  ADD COLUMN IF NOT EXISTS `subsidiary_type` VARCHAR(50) NULL AFTER `subsidiary_id`,
  ADD COLUMN IF NOT EXISTS `subsidiary_ref` VARCHAR(100) NULL AFTER `subsidiary_type`,
  ADD KEY IF NOT EXISTS `idx_jevdata_subsidiary` (`subsidiary_id`),
  ADD KEY IF NOT EXISTS `idx_jevdata_acc_code_sub` (`acc_code`, `subsidiary_id`);

-- 3) Standardize beginning balance table to support brgy/year-based lookup
-- (Some installs already have these columns due to savebb(). This is idempotent.)
ALTER TABLE `tbl_begbal`
  ADD COLUMN IF NOT EXISTS `brgy_id` INT(11) NULL AFTER `year`,
  ADD COLUMN IF NOT EXISTS `subsidiary_id` INT(11) NULL AFTER `brgy_id`,
  ADD KEY IF NOT EXISTS `idx_begbal_brgy_year_code` (`brgy_id`,`year`,`acc_code`),
  ADD KEY IF NOT EXISTS `idx_begbal_brgy_year_code_sub` (`brgy_id`,`year`,`acc_code`,`subsidiary_id`);

COMMIT;

