-- List duplicate subsidiaries (same type + same name after case/whitespace normalize)
-- Run in phpMyAdmin or: mysql -u root -p YOUR_DB < subsidiaries_find_duplicates.sql
--
-- Uses MariaDB/MySQL REGEXP_REPLACE to collapse internal spaces (10.0.5+ / MySQL 8+).
-- "Jaro" vs "Municipality of Jaro" will NOT appear as one group — merge those manually
-- (see subsidiaries_merge_manual_map.example.sql or edit tbl_subsidiaries / re-point jevdata).

SET @norm := 'LOWER(TRIM(REGEXP_REPLACE(TRIM(name), ''[[:space:]]+'', '' '')))';

-- Summary: groups with more than one row
SELECT
  subsidiary_type,
  LOWER(TRIM(REGEXP_REPLACE(TRIM(name), '[[:space:]]+', ' '))) AS norm_name,
  COUNT(*) AS cnt,
  GROUP_CONCAT(subsidiary_id ORDER BY subsidiary_id) AS ids,
  GROUP_CONCAT(name ORDER BY subsidiary_id SEPARATOR ' | ') AS names
FROM tbl_subsidiaries
GROUP BY subsidiary_type, LOWER(TRIM(REGEXP_REPLACE(TRIM(name), '[[:space:]]+', ' ')))
HAVING COUNT(*) > 1
ORDER BY subsidiary_type, norm_name;

-- Detail: every row that belongs to a duplicate group
SELECT s.subsidiary_id, s.subsidiary_type, s.name,
  LOWER(TRIM(REGEXP_REPLACE(TRIM(s.name), '[[:space:]]+', ' '))) AS norm_name
FROM tbl_subsidiaries s
WHERE (s.subsidiary_type, LOWER(TRIM(REGEXP_REPLACE(TRIM(s.name), '[[:space:]]+', ' ')))) IN (
  SELECT subsidiary_type, LOWER(TRIM(REGEXP_REPLACE(TRIM(name), '[[:space:]]+', ' ')))
  FROM tbl_subsidiaries
  GROUP BY subsidiary_type, LOWER(TRIM(REGEXP_REPLACE(TRIM(name), '[[:space:]]+', ' ')))
  HAVING COUNT(*) > 1
)
ORDER BY s.subsidiary_type, norm_name, s.subsidiary_id;
