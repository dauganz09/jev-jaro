-- Manual merge: retire subsidiary_id 38 and 39, keep 40 (KML / duplicate group).
-- Backup first. Comment out tbl_begbal UPDATE if that column does not exist.

START TRANSACTION;

UPDATE tbl_jevdata SET subsidiary_id = 40 WHERE subsidiary_id IN (38, 39);

UPDATE tbl_begbal SET subsidiary_id = 40 WHERE subsidiary_id IN (38, 39);

DELETE FROM tbl_subsidiaries WHERE subsidiary_id IN (38, 39);

COMMIT;
