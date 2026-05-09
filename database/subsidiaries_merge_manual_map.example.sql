-- Example: manually merge subsidiaries that are the same entity but different text
-- ("Jaro" vs "Municipality of Jaro" will not match automatic normalization.)
--
-- Steps:
-- 1. Pick the canonical subsidiary_id you want to keep (e.g. the one with the best name).
-- 2. List the duplicate ids to retire.
-- 3. Run UPDATEs, then DELETE the retired rows.
--
-- Example: keep id 10, merge 25 and 31 into it

/*
START TRANSACTION;

UPDATE tbl_jevdata SET subsidiary_id = 10 WHERE subsidiary_id IN (25, 31);
UPDATE tbl_begbal SET subsidiary_id = 10 WHERE subsidiary_id IN (25, 31);

DELETE FROM tbl_subsidiaries WHERE subsidiary_id IN (25, 31);

COMMIT;
*/
