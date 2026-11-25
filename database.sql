-- FULL schema for a brand-new install (users, password reset, roles, facilities, catalog, stock, alerts).
-- If you already ran the first version of database.sql, run upgrade_roles.sql instead.
CREATE DATABASE IF NOT EXISTS drug_alert CHARACTER SET utf8mb4;
USE drug_alert;

CREATE TABLE IF NOT EXISTS facilities (
  facility_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  type ENUM('pharmacy','hospital','clinic','health_centre') NOT NULL,
  location VARCHAR(150) NOT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20),
  role ENUM('patient','facility_staff','admin') NOT NULL DEFAULT 'patient',
  facility_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_facility FOREIGN KEY (facility_id) REFERENCES facilities(facility_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS drugs_vaccines (
  drug_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category ENUM('Drug','Vaccine') NOT NULL DEFAULT 'Drug',
  manufacturer VARCHAR(150) NOT NULL DEFAULT '',
  pack_size VARCHAR(50) NOT NULL DEFAULT '',
  UNIQUE KEY uq_drug (name, manufacturer, pack_size)
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

INSERT IGNORE INTO drugs_vaccines (name, category, manufacturer) VALUES
  ('Amoxicillin 500mg', 'Drug', ''),
  ('Paracetamol 500mg', 'Drug', ''),
  ('Artemether/Lumefantrine 20/120mg', 'Drug', ''),
  ('Oral Rehydration Salts', 'Drug', ''),
  ('BCG Vaccine', 'Vaccine', ''),
  ('DTP Vaccine', 'Vaccine', ''),
  ('Measles Vaccine', 'Vaccine', '');

-- To create your first administrator: register a normal account, then run
--   UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
