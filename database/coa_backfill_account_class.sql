-- Backfill tbl_accounts.account_class from account code (Philippine UACS / COA convention)
-- -----------------------------------------------------------------------------
-- Maps the first digit of the UACS-style account code (after TRIM) to the same
-- keys used in application/config/coa_accounts.php:
--   1 → Assets
--   2 → Liabilities
--   3 → Net_Assets_Equity  (Net Assets / Equity)
--   4 → Revenue
--   5 → Expenses
--   other → Memorandum (includes 6–9, 0, or non-numeric leading character)
--
-- Run database/coa_crud_migration.sql first if account_class column is missing.
-- Backup tbl_accounts before running. Safe to re-run only empty classifications;
-- change the WHERE clause if you want to overwrite existing values.

START TRANSACTION;

-- Preview (run separately if you want to inspect before updating):
-- SELECT account_id, code, name, account_class AS current_class,
--   CASE LEFT(TRIM(`code`), 1)
--     WHEN '1' THEN 'Assets'
--     WHEN '2' THEN 'Liabilities'
--     WHEN '3' THEN 'Net_Assets_Equity'
--     WHEN '4' THEN 'Revenue'
--     WHEN '5' THEN 'Expenses'
--     ELSE 'Memorandum'
--   END AS derived_class
-- FROM tbl_accounts;

UPDATE `tbl_accounts`
SET `account_class` = CASE LEFT(TRIM(`code`), 1)
  WHEN '1' THEN 'Assets'
  WHEN '2' THEN 'Liabilities'
  WHEN '3' THEN 'Net_Assets_Equity'
  WHEN '4' THEN 'Revenue'
  WHEN '5' THEN 'Expenses'
  ELSE 'Memorandum'
END
WHERE (`account_class` IS NULL OR TRIM(`account_class`) = '')
  AND TRIM(`code`) <> '';

COMMIT;
