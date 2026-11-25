-- Run once in phpMyAdmin (database drug_alert > SQL tab) if the system is already installed.
-- Adds a pack size to each drug or vaccine, e.g. '10 x 3' (3 strips of 10 tablets).
ALTER TABLE drugs_vaccines
  ADD COLUMN pack_size VARCHAR(50) NOT NULL DEFAULT '' AFTER manufacturer,
  DROP INDEX uq_drug,
  ADD UNIQUE KEY uq_drug (name, manufacturer, pack_size);
