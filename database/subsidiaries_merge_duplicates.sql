-- Merge duplicate subsidiaries: same subsidiary_type + normalized name (case + whitespace)
-- Keeps the row with MIN(subsidiary_id), repoints tbl_jevdata and tbl_begbal, then deletes extras.
-- Canonical display name becomes the longest trimmed name in the group (often the most complete).
--
-- BEFORE RUNNING: backup your database. Run subsidiaries_find_duplicates.sql first to review.
--
-- Does NOT merge different spellings (e.g. "Jaro" vs "Municipality of Jaro") — fix those manually.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS t_sub_remap;
CREATE TEMPORARY TABLE t_sub_remap (
  old_id INT NOT NULL PRIMARY KEY,
  new_id INT NOT NULL,
  KEY idx_new (new_id)
);

INSERT INTO t_sub_remap (old_id, new_id)
SELECT s.subsidiary_id AS old_id,
  (
    SELECT MIN(s2.subsidiary_id)
    FROM tbl_subsidiaries s2
    WHERE s2.subsidiary_type = s.subsidiary_type
      AND LOWER(TRIM(REGEXP_REPLACE(TRIM(s2.name), '[[:space:]]+', ' ')))
        = LOWER(TRIM(REGEXP_REPLACE(TRIM(s.name), '[[:space:]]+', ' ')))
  ) AS new_id
FROM tbl_subsidiaries s;

-- Point JEV lines to the keeper
UPDATE tbl_jevdata jd
INNER JOIN t_sub_remap m ON jd.subsidiary_id = m.old_id AND m.old_id <> m.new_id
SET jd.subsidiary_id = m.new_id;

-- Point beginning balances (skip this block if tbl_begbal has no subsidiary_id column yet)
UPDATE tbl_begbal bb
INNER JOIN t_sub_remap m ON bb.subsidiary_id = m.old_id AND m.old_id <> m.new_id
SET bb.subsidiary_id = m.new_id;

-- Set keeper's name to the longest variant in the group (prefer more complete label)
UPDATE tbl_subsidiaries s
INNER JOIN (
  SELECT
    s1.subsidiary_type,
    LOWER(TRIM(REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' '))) AS nk,
    MIN(s1.subsidiary_id) AS keeper_id,
    SUBSTRING_INDEX(
      GROUP_CONCAT(s1.name ORDER BY CHAR_LENGTH(TRIM(s1.name)) DESC, s1.subsidiary_id ASC SEPARATOR '|||'),
      '|||',
      1
    ) AS best_name
  FROM tbl_subsidiaries s1
  GROUP BY s1.subsidiary_type, LOWER(TRIM(REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' ')))
  HAVING COUNT(*) > 1
) g ON s.subsidiary_id = g.keeper_id
SET s.name = g.best_name;

-- Remove duplicate master rows
DELETE s FROM tbl_subsidiaries s
INNER JOIN t_sub_remap m ON s.subsidiary_id = m.old_id
WHERE m.old_id <> m.new_id;

DROP TEMPORARY TABLE IF EXISTS t_sub_remap;

COMMIT;
