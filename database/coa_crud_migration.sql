-- Chart of Accounts CRUD — extend tbl_accounts (Philippine COA / PPSAS-friendly metadata)
-- Backup first. Safe to run once.

START TRANSACTION;

ALTER TABLE `tbl_accounts`
  ADD COLUMN IF NOT EXISTS `account_class` VARCHAR(80) NULL DEFAULT NULL COMMENT 'COA/PPSAS grouping' AFTER `name`,
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `account_class`,
  ADD COLUMN IF NOT EXISTS `notes` VARCHAR(255) NULL DEFAULT NULL AFTER `is_active`;

COMMIT;
