-- Manual merge: retire subsidiary_id 43 and 44, keep 42.
-- Backup first. Comment out tbl_begbal UPDATE if that column does not exist.

START TRANSACTION;

UPDATE tbl_jevdata SET subsidiary_id = 42 WHERE subsidiary_id IN (43, 44);

UPDATE tbl_begbal SET subsidiary_id = 42 WHERE subsidiary_id IN (43, 44);

DELETE FROM tbl_subsidiaries WHERE subsidiary_id IN (43, 44);

COMMIT;
