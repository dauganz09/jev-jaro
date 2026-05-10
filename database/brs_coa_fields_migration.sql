-- COA-aligned BRS fields (apply after bank_recon_migration.sql)
-- Backup first.

START TRANSACTION;

ALTER TABLE `tbl_bank_accounts`
  ADD COLUMN IF NOT EXISTS `branch` VARCHAR(150) NOT NULL DEFAULT '' AFTER `bank_name`;

ALTER TABLE `tbl_bank_recon`
  ADD COLUMN IF NOT EXISTS `statement_as_of_date` DATE NULL DEFAULT NULL AFTER `book_ending_balance`,
  ADD COLUMN IF NOT EXISTS `explanatory_comment` TEXT NULL AFTER `statement_as_of_date`;

COMMIT;
