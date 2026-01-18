-- Migration 001: normalize carrier codes to UNDERSCORE format + add schedule_item_gates
-- Safe to run multiple times (uses IF NOT EXISTS / IGNORE where possible)

START TRANSACTION;

-- 1) Create schedule_item_gates (normalized gates list for schedule_items)
CREATE TABLE IF NOT EXISTS schedule_item_gates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_item_id INT NOT NULL,
  gate_code VARCHAR(32) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_schedule_gate (schedule_item_id, gate_code),
  KEY idx_gate_code (gate_code),
  CONSTRAINT fk_sig_item FOREIGN KEY (schedule_item_id) REFERENCES schedule_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) Normalize carrier codes to UNDERSCORE (UPPER + spaces/hyphens -> underscore)
UPDATE carriers
SET code = REPLACE(REPLACE(UPPER(TRIM(code)), ' ', '_'), '-', '_')
WHERE code IS NOT NULL AND code <> '';

UPDATE schedule_items
SET carrier = REPLACE(REPLACE(UPPER(TRIM(carrier)), ' ', '_'), '-', '_')
WHERE carrier IS NOT NULL AND carrier <> '';

UPDATE statuses
SET carrier = REPLACE(REPLACE(UPPER(TRIM(carrier)), ' ', '_'), '-', '_')
WHERE carrier IS NOT NULL AND carrier <> '';

-- 3) Migrate schedule_items.allowed_gates CSV into schedule_item_gates
-- Supports separators: comma or semicolon. Adjust the list (1..40) if you have more than 40 gates per row.
INSERT IGNORE INTO schedule_item_gates (schedule_item_id, gate_code)
SELECT
  si.id,
  TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(si.allowed_gates,';',','), ',', n.n), ',', -1)) AS gate_code
FROM schedule_items si
JOIN (
  SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
  UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 UNION ALL SELECT 10
  UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15
  UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
  UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24 UNION ALL SELECT 25
  UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30
  UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34 UNION ALL SELECT 35
  UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39 UNION ALL SELECT 40
) n
WHERE si.allowed_gates IS NOT NULL
  AND si.allowed_gates <> ''
  AND n.n <= 1 + LENGTH(REPLACE(si.allowed_gates,';',',')) - LENGTH(REPLACE(REPLACE(si.allowed_gates,';',','), ',', ''))
  AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(si.allowed_gates,';',','), ',', n.n), ',', -1)) <> '';

COMMIT;
