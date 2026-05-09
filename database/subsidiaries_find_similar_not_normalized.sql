-- Find subsidiary pairs that are NOT equal after case/whitespace normalization
-- but may still be the same entity (manual review / custom merge scripts).
--
-- Strategy A: one normalized name is a substring of the other (same subsidiary_type).
--   Catches: "Jaro" vs "Municipality of Jaro", "Municipality of Jaro" vs "... Leyte"
--   May false-positive: "Maria" vs "Maria Santos" — review before merging.
--
-- Strategy B: equal after removing punctuation (periods, commas, etc.) but still
--   different after whitespace-only normalization — catches some spelling variants.
--
-- Run in phpMyAdmin or: mysql -u root -p YOUR_DB < subsidiaries_find_similar_not_normalized.sql

-- ---------------------------------------------------------------------------
-- A) Substring containment (same type, different norm, shorter contained in longer)
-- ---------------------------------------------------------------------------
SELECT
  s1.subsidiary_id AS id_1,
  s1.name AS name_1,
  s2.subsidiary_id AS id_2,
  s2.name AS name_2,
  s1.subsidiary_type,
  'substring_match' AS match_kind
FROM tbl_subsidiaries s1
INNER JOIN tbl_subsidiaries s2
  ON s1.subsidiary_type = s2.subsidiary_type
  AND s1.subsidiary_id < s2.subsidiary_id
WHERE
  LOWER(TRIM(REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' ')))
    <> LOWER(TRIM(REGEXP_REPLACE(TRIM(s2.name), '[[:space:]]+', ' ')))
  AND LEAST(
        CHAR_LENGTH(TRIM(REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' '))),
        CHAR_LENGTH(TRIM(REGEXP_REPLACE(TRIM(s2.name), '[[:space:]]+', ' ')))
      ) >= 3
  AND (
    LOWER(TRIM(REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' ')))
      LIKE CONCAT(
        '%',
        LOWER(TRIM(REGEXP_REPLACE(TRIM(s2.name), '[[:space:]]+', ' '))),
        '%'
      )
    OR LOWER(TRIM(REGEXP_REPLACE(TRIM(s2.name), '[[:space:]]+', ' ')))
      LIKE CONCAT(
        '%',
        LOWER(TRIM(REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' '))),
        '%'
      )
  )
ORDER BY s1.subsidiary_type, name_1, name_2;

-- ---------------------------------------------------------------------------
-- B) Same after stripping punctuation, different after whitespace-only norm
--    (MariaDB REGEXP_REPLACE; adjust if your version differs)
-- ---------------------------------------------------------------------------
SELECT
  s1.subsidiary_id AS id_1,
  s1.name AS name_1,
  s2.subsidiary_id AS id_2,
  s2.name AS name_2,
  s1.subsidiary_type,
  'punctuation_only_diff' AS match_kind
FROM tbl_subsidiaries s1
INNER JOIN tbl_subsidiaries s2
  ON s1.subsidiary_type = s2.subsidiary_type
  AND s1.subsidiary_id < s2.subsidiary_id
WHERE
  LOWER(TRIM(REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' ')))
    <> LOWER(TRIM(REGEXP_REPLACE(TRIM(s2.name), '[[:space:]]+', ' ')))
  AND LOWER(
        TRIM(
          REGEXP_REPLACE(
            REGEXP_REPLACE(TRIM(s1.name), '[[:space:]]+', ' '),
            '[[:punct:]]+',
            ''
          )
        )
      )
    = LOWER(
        TRIM(
          REGEXP_REPLACE(
            REGEXP_REPLACE(TRIM(s2.name), '[[:space:]]+', ' '),
            '[[:punct:]]+',
            ''
          )
        )
      )
ORDER BY s1.subsidiary_type, name_1, name_2;
