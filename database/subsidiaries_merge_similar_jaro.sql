-- Merge subsidiaries that refer to the same LGU but were entered under different text:
--   Jaro
--   Municipality of Jaro
--   Municipality of Jaro. Leyte
--
-- Canonical name after merge: Municipality of Jaro
-- Keeps MIN(subsidiary_id) within each subsidiary_type among the matched rows.
--
-- BEFORE RUNNING: backup the database.
-- Run only if these names exist; harmless if no rows match (no-op).

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS t_jaro_ids;
CREATE TEMPORARY TABLE t_jaro_ids AS
SELECT subsidiary_id, subsidiary_type, name
FROM tbl_subsidiaries
WHERE LOWER(TRIM(REGEXP_REPLACE(TRIM(name), '[[:space:]]+', ' '))) IN (
    'jaro',
    'municipality of jaro',
    'municipality of jaro.',
    'municipality of jaro. leyte',
    'municipality of jaro leyte'
  );

-- Nothing to do
-- (If your server errors on empty temp table in later steps, skip running this file when no matches.)

DROP TEMPORARY TABLE IF EXISTS t_jaro_map;
CREATE TEMPORARY TABLE t_jaro_map (
  old_id INT NOT NULL PRIMARY KEY,
  new_id INT NOT NULL,
  subsidiary_type VARCHAR(50) NOT NULL
);

INSERT INTO t_jaro_map (old_id, new_id, subsidiary_type)
SELECT
  j.subsidiary_id AS old_id,
  (
    SELECT MIN(j2.subsidiary_id)
    FROM t_jaro_ids j2
    WHERE j2.subsidiary_type = j.subsidiary_type
  ) AS new_id,
  j.subsidiary_type
FROM t_jaro_ids j;

UPDATE tbl_jevdata jd
INNER JOIN t_jaro_map m ON jd.subsidiary_id = m.old_id AND m.old_id <> m.new_id
SET jd.subsidiary_id = m.new_id;

-- Remove if tbl_begbal has no subsidiary_id column
UPDATE tbl_begbal bb
INNER JOIN t_jaro_map m ON bb.subsidiary_id = m.old_id AND m.old_id <> m.new_id
SET bb.subsidiary_id = m.new_id;

-- Canonical display name for the keeper row(s)
UPDATE tbl_subsidiaries s
INNER JOIN (
  SELECT DISTINCT subsidiary_type, MIN(subsidiary_id) AS keeper_id
  FROM t_jaro_ids
  GROUP BY subsidiary_type
) k ON s.subsidiary_id = k.keeper_id
SET s.name = 'Municipality of Jaro';

DELETE s FROM tbl_subsidiaries s
INNER JOIN t_jaro_map m ON s.subsidiary_id = m.old_id
WHERE m.old_id <> m.new_id;

DROP TEMPORARY TABLE IF EXISTS t_jaro_map;
DROP TEMPORARY TABLE IF EXISTS t_jaro_ids;

COMMIT;
