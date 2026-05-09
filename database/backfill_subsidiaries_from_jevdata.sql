-- Backfill subsidiaries from existing JEV line items (best-effort)
-- This is an OPTIONAL helper to populate tbl_subsidiaries and link existing tbl_jevdata rows.
-- Review carefully before running.

START TRANSACTION;

-- Insert payees as suppliers (common for CKD/CSD disbursements)
INSERT INTO tbl_subsidiaries (subsidiary_type, name, is_active)
SELECT 'supplier' AS subsidiary_type, t.name, 1
FROM (
  SELECT TRIM(payee) AS name
  FROM tbl_jevdata
  WHERE payee IS NOT NULL AND TRIM(payee) <> ''
  GROUP BY TRIM(payee)
) t
LEFT JOIN tbl_subsidiaries s
  ON s.subsidiary_type = 'supplier' AND s.name = t.name
WHERE s.subsidiary_id IS NULL;

-- Insert payors as taxpayers/customers (common for COL receipts)
INSERT INTO tbl_subsidiaries (subsidiary_type, name, is_active)
SELECT 'taxpayer' AS subsidiary_type, t.name, 1
FROM (
  SELECT TRIM(payor) AS name
  FROM tbl_jevdata
  WHERE payor IS NOT NULL AND TRIM(payor) <> ''
  GROUP BY TRIM(payor)
) t
LEFT JOIN tbl_subsidiaries s
  ON s.subsidiary_type = 'taxpayer' AND s.name = t.name
WHERE s.subsidiary_id IS NULL;

-- Link existing JEV data rows (payee → supplier)
UPDATE tbl_jevdata jd
JOIN tbl_subsidiaries s
  ON s.subsidiary_type = 'supplier' AND s.name = TRIM(jd.payee)
SET jd.subsidiary_id = s.subsidiary_id,
    jd.subsidiary_type = 'supplier'
WHERE (jd.subsidiary_id IS NULL OR jd.subsidiary_id = 0)
  AND jd.payee IS NOT NULL AND TRIM(jd.payee) <> '';

-- Link existing JEV data rows (payor → taxpayer)
UPDATE tbl_jevdata jd
JOIN tbl_subsidiaries s
  ON s.subsidiary_type = 'taxpayer' AND s.name = TRIM(jd.payor)
SET jd.subsidiary_id = s.subsidiary_id,
    jd.subsidiary_type = 'taxpayer'
WHERE (jd.subsidiary_id IS NULL OR jd.subsidiary_id = 0)
  AND jd.payor IS NOT NULL AND TRIM(jd.payor) <> '';

COMMIT;

