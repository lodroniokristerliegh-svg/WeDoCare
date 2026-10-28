-- ============================================================
--  WeDoCare: Web-Based Patient Management System
--  with Online Consultation and E-Prescription for Dr. Deguito
--
--  Database schema (MySQL 5.7+ / MariaDB 10.3+)
--  Northeastern Mindanao Colleges — Capstone Project and Research 1
-- ============================================================

CREATE DATABASE IF NOT EXISTS wedocare_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE wedocare_db;

-- ------------------------------------------------------------
-- 1. USERS  (login accounts — doctor and patients)
-- ------------------------------------------------------------
CREATE TABLE users (
  user_id        INT AUTO_INCREMENT PRIMARY KEY,
  role           ENUM('doctor','patient') NOT NULL DEFAULT 'patient',
  full_name      VARCHAR(150) NOT NULL,
  email          VARCHAR(150) NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  patient_id     INT NULL,                       -- link to patients table (NULL for doctor)
  photo          VARCHAR(255) NULL,               -- doctor profile picture path
  license_no     VARCHAR(50)  NULL,               -- doctor PRC license number
  specialization VARCHAR(100) NULL,               -- e.g. "General Practitioner"
  clinic_address VARCHAR(255) NULL,
  contact_no     VARCHAR(20)  NULL,
  bio            VARCHAR(500) NULL,               -- short "About the Doctor" description for the homepage
  consultation_fee DECIMAL(10,2) NULL,            -- fixed consultation fee set by the doctor
  medcert_fee    DECIMAL(10,2) NULL,              -- medical certificate fee
  gcash_number   VARCHAR(20)  NULL,
  gcash_qr       VARCHAR(255) NULL,               -- uploaded GCash QR code image path
  maya_number    VARCHAR(20)  NULL,
  maya_qr        VARCHAR(255) NULL,               -- uploaded Maya QR code image path
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. PATIENTS  (medical / demographic records)
-- ------------------------------------------------------------
CREATE TABLE patients (
  patient_id   INT AUTO_INCREMENT PRIMARY KEY,
  last_name    VARCHAR(80) NOT NULL,
  first_name   VARCHAR(80) NOT NULL,
  middle_name  VARCHAR(80) NULL,
  dob          DATE NOT NULL,
  gender       ENUM('Male','Female') NOT NULL,
  civil_status VARCHAR(30) NULL,
  contact_no   VARCHAR(20) NOT NULL,
  email        VARCHAR(150) NULL,
  address      VARCHAR(255) NOT NULL,
  blood_type   VARCHAR(5)  NULL,
  allergies    VARCHAR(255) NULL DEFAULT 'Not yet assessed',
  history      TEXT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE users
  ADD CONSTRAINT fk_users_patient
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
  ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 3. APPOINTMENTS
-- ------------------------------------------------------------
CREATE TABLE appointments (
  appointment_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id     INT NOT NULL,
  appt_date      DATE NOT NULL,
  appt_time      TIME NOT NULL,
  appt_type      ENUM('walkin','online') NOT NULL DEFAULT 'walkin',
  concern        VARCHAR(255) NOT NULL,
  doctor_id      INT NULL,                        -- doctor the patient booked
  gmeet_link     VARCHAR(255) NULL,
  status         ENUM('pending','confirmed','done','cancelled') NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. PRESCRIPTIONS (header) + ITEMS (drug lines)
-- ------------------------------------------------------------
CREATE TABLE prescriptions (
  prescription_id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id      INT NOT NULL,
  appointment_id  INT NULL,
  date_issued     DATE NOT NULL,
  status          ENUM('pending','issued') NOT NULL DEFAULT 'issued',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE prescription_items (
  item_id         INT AUTO_INCREMENT PRIMARY KEY,
  prescription_id INT NOT NULL,
  drug_name       VARCHAR(150) NOT NULL,
  dosage          VARCHAR(80)  NOT NULL,
  frequency       VARCHAR(80)  NOT NULL,
  duration        VARCHAR(50)  NOT NULL,
  FOREIGN KEY (prescription_id) REFERENCES prescriptions(prescription_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. LAB REFERRALS (header) + TESTS (checklist items)
-- ------------------------------------------------------------
CREATE TABLE lab_referrals (
  referral_id     INT AUTO_INCREMENT PRIMARY KEY,
  patient_id      INT NOT NULL,
  appointment_id  INT NULL,
  date_issued     DATE NOT NULL,
  result_file     VARCHAR(255) NULL,          -- patient-uploaded lab result (image/PDF)
  result_notes    VARCHAR(255) NULL,          -- optional note from the patient about the result
  status          ENUM('pending','issued','completed') NOT NULL DEFAULT 'issued',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lab_referral_tests (
  test_id      INT AUTO_INCREMENT PRIMARY KEY,
  referral_id  INT NOT NULL,
  test_name    VARCHAR(150) NOT NULL,
  FOREIGN KEY (referral_id) REFERENCES lab_referrals(referral_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 6. PAYMENTS  (manual verification via uploaded proof of payment)
-- ------------------------------------------------------------
CREATE TABLE payments (
  payment_id     INT AUTO_INCREMENT PRIMARY KEY,
  patient_id     INT NOT NULL,
  appointment_id INT NULL,
  service        VARCHAR(150) NOT NULL,
  amount         DECIMAL(10,2) NOT NULL,
  method         ENUM('Cash','GCash','Maya') NOT NULL DEFAULT 'Cash',
  reference_no   VARCHAR(100) NULL,          -- GCash/Maya transaction reference or OR number
  sender_name    VARCHAR(150) NULL,          -- name on the sending GCash/Maya account
  date_paid      DATE NULL,                  -- date the patient says they paid
  proof_image    VARCHAR(255) NULL,          -- uploaded screenshot/receipt path
  status         ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  paid_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  verified_by    INT NULL,                   -- users.user_id of the doctor who verified it
  FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
  FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE SET NULL,
  FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- SEED DATA — Doctor account
--   Do NOT insert a plain/guessed password hash here.
--   Run setup_admin.php ONCE in your browser after importing this
--   schema — it safely hashes the password with PHP's own
--   password_hash() and inserts the doctor account for you.
--   (See backend/setup_admin.php)
-- ------------------------------------------------------------

-- ------------------------------------------------------------
-- OPTIONAL SAMPLE DATA — a few patients so the dashboard/reports
-- have something to show right after setup. Safe to delete anytime.
-- ------------------------------------------------------------
INSERT INTO patients (last_name, first_name, middle_name, dob, gender, civil_status, contact_no, email, address, blood_type, allergies, history) VALUES
('Reyes', 'Maria', 'Santos', '1990-04-12', 'Female', 'Married', '09171234567', 'maria.reyes@email.com', 'Brgy. Washington, Surigao City', 'O+', 'NKDA', 'Hypertension, maintenance medication.'),
('Cruz', 'Juan', 'Dela', '1985-09-23', 'Male', 'Single', '09181234567', 'juan.cruz@email.com', 'Brgy. Luna, Surigao City', 'A+', 'Penicillin', 'Type 2 Diabetes, on Metformin.'),
('Lim', 'Ana', '', '1998-01-05', 'Female', 'Single', '09191234567', 'ana.lim@email.com', 'Brgy. Taft, Surigao City', 'B+', 'NKDA', 'No significant medical history.');

INSERT INTO appointments (patient_id, appt_date, appt_time, appt_type, concern, status) VALUES
(1, CURDATE(), '09:00:00', 'walkin', 'Follow-up check for blood pressure', 'pending'),
(2, CURDATE(), '10:30:00', 'online', 'Diabetes maintenance consultation', 'pending'),
(3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00:00', 'walkin', 'General check-up', 'pending');
