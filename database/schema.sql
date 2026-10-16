CREATE DATABASE IF NOT EXISTS rmutt_hospital_queue
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rmutt_hospital_queue;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('patient','staff','admin','executive') NOT NULL DEFAULT 'patient',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  patient_number VARCHAR(40) NULL UNIQUE,
  phone VARCHAR(30) NULL,
  date_of_birth DATE NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_patients_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  location_label VARCHAR(190) NULL,
  is_open_for_booking TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS schedules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id BIGINT UNSIGNED NOT NULL,
  service_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  capacity INT UNSIGNED NOT NULL,
  booked_count INT UNSIGNED NOT NULL DEFAULT 0,
  is_open TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_department_slot (department_id, service_date, start_time),
  CONSTRAINT fk_schedules_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_schedule_capacity CHECK (capacity > 0 AND booked_count <= capacity)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_code VARCHAR(32) NOT NULL UNIQUE,
  patient_id BIGINT UNSIGNED NOT NULL,
  department_id BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NOT NULL,
  appointment_status ENUM('booked','checked_in','waiting','in_service','completed','cancelled','no_show')
    NOT NULL DEFAULT 'booked',
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_appointment_patient (patient_id, created_at),
  INDEX idx_appointment_department_status (department_id, appointment_status),
  CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_appointments_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_appointments_schedule FOREIGN KEY (schedule_id) REFERENCES schedules(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS queues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id BIGINT UNSIGNED NOT NULL UNIQUE,
  queue_number VARCHAR(20) NOT NULL,
  queue_date DATE NOT NULL,
  queue_status ENUM('waiting','called','serving','completed','skipped','cancelled')
    NOT NULL DEFAULT 'waiting',
  called_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_queue_number (queue_date, queue_number),
  INDEX idx_queue_department_date_status (queue_date, queue_status),
  CONSTRAINT fk_queues_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  appointment_id BIGINT UNSIGNED NULL,
  channel ENUM('in_app','line','sms','email') NOT NULL DEFAULT 'in_app',
  message VARCHAR(500) NOT NULL,
  send_status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  sent_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user_status (user_id, send_status),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_notifications_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_actor_date (actor_user_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Starter departments are examples only. Confirm actual departments and service hours with the hospital.
INSERT INTO departments (name, description, location_label)
SELECT 'เวชปฏิบัติทั่วไป', 'ตัวอย่างแผนกสำหรับทดสอบระบบ', 'โปรดตรวจสอบสถานที่กับโรงพยาบาล'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'เวชปฏิบัติทั่วไป');

INSERT INTO departments (name, description, location_label)
SELECT 'ทันตกรรม', 'ตัวอย่างแผนกสำหรับทดสอบระบบ', 'โปรดตรวจสอบสถานที่กับโรงพยาบาล'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'ทันตกรรม');

INSERT INTO departments (name, description, location_label)
SELECT 'กายภาพบำบัด', 'ตัวอย่างแผนกสำหรับทดสอบระบบ', 'โปรดตรวจสอบสถานที่กับโรงพยาบาล'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE name = 'กายภาพบำบัด');

-- Demo Users (passwords: staff1234, admin1234, patient1234)
INSERT INTO users (id, full_name, email, password_hash, role, is_active)
SELECT 1, 'เจ้าหน้าที่ ประจำห้องตรวจ', 'staff@hospital.com', '$2y$10$tZ261jUe6Xj149y8G1Ww1eH7dZcfsT2a93M1g7Wc6lJ5C7v4fJcba', 'staff', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'staff@hospital.com');

INSERT INTO users (id, full_name, email, password_hash, role, is_active)
SELECT 2, 'ผู้ดูแลระบบ ไอที', 'admin@hospital.com', '$2y$10$wJ6l86h3xQy46Wl548a8a.3j555eKj1G1M4Q.O16i4K7YkK37O44W', 'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@hospital.com');

INSERT INTO users (id, full_name, email, password_hash, role, is_active)
SELECT 3, 'นายสมชาย สุขสมบูรณ์', 'patient@hospital.com', '$2y$10$rC6xK0hQ9E62eG2l9b1GSeP57g76vY40C2H9kI8P5A4S1D7F2G3H4', 'patient', 1
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'patient@hospital.com');

INSERT INTO patients (user_id, patient_number, phone, date_of_birth)
SELECT 3, 'HN-26-00101', '0891234567', '1995-05-15'
WHERE NOT EXISTS (SELECT 1 FROM patients WHERE user_id = 3);

-- Starter Schedules for Today and Tomorrow
INSERT INTO schedules (department_id, service_date, start_time, end_time, capacity, booked_count, is_open)
SELECT 1, CURDATE(), '09:00:00', '12:00:00', 15, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE department_id = 1 AND service_date = CURDATE() AND start_time = '09:00:00');

INSERT INTO schedules (department_id, service_date, start_time, end_time, capacity, booked_count, is_open)
SELECT 1, CURDATE(), '13:00:00', '16:00:00', 15, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE department_id = 1 AND service_date = CURDATE() AND start_time = '13:00:00');

INSERT INTO schedules (department_id, service_date, start_time, end_time, capacity, booked_count, is_open)
SELECT 2, CURDATE(), '09:00:00', '12:00:00', 10, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE department_id = 2 AND service_date = CURDATE() AND start_time = '09:00:00');

INSERT INTO schedules (department_id, service_date, start_time, end_time, capacity, booked_count, is_open)
SELECT 2, CURDATE(), '13:00:00', '16:00:00', 10, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE department_id = 2 AND service_date = CURDATE() AND start_time = '13:00:00');

INSERT INTO schedules (department_id, service_date, start_time, end_time, capacity, booked_count, is_open)
SELECT 3, CURDATE(), '09:00:00', '12:00:00', 12, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE department_id = 3 AND service_date = CURDATE() AND start_time = '09:00:00');

INSERT INTO schedules (department_id, service_date, start_time, end_time, capacity, booked_count, is_open)
SELECT 3, CURDATE(), '13:00:00', '16:00:00', 12, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM schedules WHERE department_id = 3 AND service_date = CURDATE() AND start_time = '13:00:00');

