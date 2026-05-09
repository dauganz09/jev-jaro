-- Manual merge: retire subsidiary_id 45, keep 46.
-- Backup first. Comment out tbl_begbal UPDATE if that column does not exist.

START TRANSACTION;

UPDATE tbl_jevdata SET subsidiary_id = 46 WHERE subsidiary_id = 45;

UPDATE tbl_begbal SET subsidiary_id = 46 WHERE subsidiary_id = 45;

DELETE FROM tbl_subsidiaries WHERE subsidiary_id = 45;

COMMIT;
