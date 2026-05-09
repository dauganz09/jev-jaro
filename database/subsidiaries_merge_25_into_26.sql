-- Manual merge: retire subsidiary_id 48, 50, and 51; keep 49 (Liga ng mga Barangay group).
-- Backup first. Comment out tbl_begbal UPDATE if that column does not exist.

START TRANSACTION;

UPDATE tbl_jevdata SET subsidiary_id = 49 WHERE subsidiary_id IN (48, 50, 51);

UPDATE tbl_begbal SET subsidiary_id = 49 WHERE subsidiary_id IN (48, 50, 51);

DELETE FROM tbl_subsidiaries WHERE subsidiary_id IN (48, 50, 51);

COMMIT;
