-- Upgrade for databases created with the first version (users + password_resets only).
-- Run this ONCE in phpMyAdmin -> drug_alert -> SQL tab. Existing accounts become patients.
-- If you see "Duplicate column name 'role'", it has already been run.
USE drug_alert;

CREATE TABLE IF NOT EXISTS facilities (
  facility_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type ENUM('pharmacy','hospital','clinic','health_centre') NOT NULL,
  location VARCHAR(150) NOT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE users
  ADD COLUMN role ENUM('patient','facility_staff','admin') NOT NULL DEFAULT 'patient' AFTER phone,
  ADD COLUMN facility_id INT NULL AFTER role,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER facility_id,
  ADD CONSTRAINT fk_users_facility FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS drugs_vaccines (
  drug_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category ENUM('Drug','Vaccine') NOT NULL DEFAULT 'Drug',
  manufacturer VARCHAR(150) NOT NULL DEFAULT '',
  UNIQUE KEY uq_drug (name, manufacturer)
);

CREATE TABLE IF NOT EXISTS inventory (
  inventory_id INT AUTO_INCREMENT PRIMARY KEY,
  facility_id INT NOT NULL,
  drug_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_facility_drug (facility_id, drug_id),
  FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE CASCADE,
  FOREIGN KEY (drug_id) REFERENCES drugs_vaccines(drug_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS alerts (
  alert_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  drug_id INT NOT NULL,
  facility_id INT NULL,
  status ENUM('pending','available','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (drug_id) REFERENCES drugs_vaccines(drug_id) ON DELETE CASCADE,
  FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  alert_id INT NOT NULL,
  channel ENUM('in_app','sms','email') NOT NULL DEFAULT 'in_app',
  sent_at DATETIME NULL,
  delivery_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  FOREIGN KEY (alert_id) REFERENCES alerts(alert_id) ON DELETE CASCADE
);

-- Procurement / stock history (used for the facility log and admin reports)
CREATE TABLE IF NOT EXISTS stock_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  facility_id INT NOT NULL,
  drug_id INT NOT NULL,
  user_id INT NULL,
  old_quantity INT NULL,
  new_quantity INT NOT NULL,
  supplier VARCHAR(150) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE CASCADE,
  FOREIGN KEY (drug_id) REFERENCES drugs_vaccines(drug_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Sample catalog so you can test straight away (the admin can edit it later)
INSERT IGNORE INTO drugs_vaccines (name, category, manufacturer) VALUES
  ('Amoxicillin 500mg', 'Drug', ''),
  ('Paracetamol 500mg', 'Drug', ''),
  ('Artemether/Lumefantrine 20/120mg', 'Drug', ''),
  ('Oral Rehydration Salts', 'Drug', ''),
  ('BCG Vaccine', 'Vaccine', ''),
  ('DTP Vaccine', 'Vaccine', ''),
  ('Measles Vaccine', 'Vaccine', '');
