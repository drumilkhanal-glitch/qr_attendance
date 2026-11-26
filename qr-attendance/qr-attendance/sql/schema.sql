CREATE DATABASE IF NOT EXISTS qr_attendance;
USE qr_attendance;

-- Teachers Table
CREATE TABLE IF NOT EXISTS teacher (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(100),
  phone VARCHAR(20),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_is_active (is_active)
);

-- Students Table
CREATE TABLE IF NOT EXISTS student (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100),
  phone VARCHAR(20),
  password_hash VARCHAR(255) NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_student_no (student_no),
  INDEX idx_is_active (is_active),
  INDEX idx_full_name (full_name)
);

-- Class Subjects Table
CREATE TABLE IF NOT EXISTS class_subject (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_name VARCHAR(50) NOT NULL,
  subject_name VARCHAR(100) NOT NULL,
  description TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_class_subject (class_name, subject_name),
  INDEX idx_class_name (class_name),
  INDEX idx_is_active (is_active)
);

-- Student Enrollment (Students enrolled in classes)
CREATE TABLE IF NOT EXISTS student_enrollment (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_subject_id INT NOT NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_enrollment (student_id, class_subject_id),
  FOREIGN KEY (student_id) REFERENCES student(id) ON DELETE CASCADE,
  FOREIGN KEY (class_subject_id) REFERENCES class_subject(id) ON DELETE CASCADE,
  INDEX idx_student_id (student_id),
  INDEX idx_class_subject_id (class_subject_id)
);

-- Attendance Sessions Table
CREATE TABLE IF NOT EXISTS attendance_session (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_id INT NOT NULL,
  class_subject_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  qr_payload VARCHAR(255) NOT NULL,
  session_name VARCHAR(255),
  starts_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  status ENUM('active', 'expired', 'closed') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_id) REFERENCES teacher(id) ON DELETE CASCADE,
  FOREIGN KEY (class_subject_id) REFERENCES class_subject(id) ON DELETE CASCADE,
  INDEX idx_teacher_id (teacher_id),
  INDEX idx_class_subject_id (class_subject_id),
  INDEX idx_token (token),
  INDEX idx_expires_at (expires_at),
  INDEX idx_status (status),
  INDEX idx_created_at (created_at)
);

-- Attendance Table (photo path only, no blob storage)
CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  student_id INT NOT NULL,
  marked_at DATETIME NOT NULL,
  marked_by ENUM('qr_scan', 'manual') DEFAULT 'qr_scan',
  photo_path VARCHAR(255),
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_session_student (session_id, student_id),
  FOREIGN KEY (session_id) REFERENCES attendance_session(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES student(id) ON DELETE CASCADE,
  INDEX idx_session_id (session_id),
  INDEX idx_student_id (student_id),
  INDEX idx_marked_at (marked_at),
  INDEX idx_marked_by (marked_by)
);

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_type ENUM('teacher', 'student', 'admin') NOT NULL,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  description TEXT,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_type (user_type),
  INDEX idx_user_id (user_id),
  INDEX idx_action (action),
  INDEX idx_created_at (created_at)
);

-- System Settings Table
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value LONGTEXT,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ===== SEED DATA =====

-- Insert Teachers
INSERT INTO teacher (username, password_hash, email, phone) VALUES
('teacher1', '$2y$10$qE8ZYwJf5XQECChbu7Ig6efVvOGDmcy0KgP0uasdZ3b7X5eIBps/C', 'teacher1@school.edu', '9801234567'),
('teacher2', '$2y$10$e4TMsYDLKjgFY34WkzhFfeqzO/RfNwqCWlsvBtB/tkj8r/z2IUrMq', 'teacher2@school.edu', '9802345678');

-- Insert 28 Students with proper credentials
-- Password: Student#2024 for all (hashed with bcrypt)
INSERT INTO student (student_no, full_name, email, phone, password_hash) VALUES
('STD001', 'Aayush Chaudhary', 'aayush@school.edu', '9819876501', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD002', 'Anish Subedi', 'anish@school.edu', '9819876502', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD003', 'Pradip Mishra', 'pradip@school.edu', '9819876503', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD004', 'Mahesh Chaudhary', 'mahesh@school.edu', '9819876504', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD005', 'Binita BK', 'binita@school.edu', '9819876505', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD006', 'Hem Bahadur Raut', 'hem@school.edu', '9819876506', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD007', 'Drumil Khanal', 'drumil@school.edu', '9819876507', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD008', 'Sanjog KC', 'sanjog@school.edu', '9819876508', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD009', 'Md. Mohatab Miya', 'mohatab@school.edu', '9819876509', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD010', 'Dikendra Ray', 'dikendra@school.edu', '9819876510', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD011', 'Manmohan Oli', 'manmohan@school.edu', '9819876511', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD012', 'Sushan Purja', 'sushan@school.edu', '9819876512', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD013', 'Roja Darnal', 'roja@school.edu', '9819876513', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD014', 'Shrayash Shrestha', 'shrayash@school.edu', '9819876514', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD015', 'Nishant Shrestha', 'nishant@school.edu', '9819876515', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD016', 'Yunik Bahadur Darji', 'yunik@school.edu', '9819876516', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD017', 'Astha Gurung', 'astha@school.edu', '9819876517', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD018', 'Bilasha Duwadi', 'bilasha@school.edu', '9819876518', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD019', 'Hardik Shrestha', 'hardik@school.edu', '9819876519', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD020', 'Jhanihong Rai', 'jhanihong@school.edu', '9819876520', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD021', 'Nilan Jirel', 'nilan@school.edu', '9819876521', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD022', 'Niruta Gurung', 'niruta@school.edu', '9819876522', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD023', 'Puja Budhathoki', 'puja@school.edu', '9819876523', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD024', 'Rejina Gurung', 'rejina@school.edu', '9819876524', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD025', 'Sabita Kumari Khanal', 'sabita@school.edu', '9819876525', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD026', 'Sangit Rai', 'sangit@school.edu', '9819876526', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD027', 'Yugal Kumar Raut', 'yugal@school.edu', '9819876527', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.'),
('STD028', 'Roshan Kumar Gautam', 'roshan@school.edu', '9819876528', '$2y$10$MaVyd83.MrqrVzx80XF/je3oYpjazoMNEEEjGQZok8gd8/PnyEvA.');

-- Insert Class Subjects
INSERT INTO class_subject (class_name, subject_name, description) VALUES
('BCA 4th sem', 'Operating Systems', 'OS Fundamentals and Concepts'),
('BCA 4th sem', 'Numerical Methods', 'Numerical Analysis and Methods'),
('BCA 4th sem', 'Scripting Languages', 'Programming with Script Languages'),
('BCA 4th sem', 'Software Engineering', 'Software Development and Design'),
('BCA 4th sem', 'Database Management Systems', 'DBMS Fundamentals'),
('BCA 4th sem', 'Web Development', 'Web Technologies and Development');

-- Enroll all 28 students in all classes
INSERT INTO student_enrollment (student_id, class_subject_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5), (2, 6),
(3, 1), (3, 2), (3, 3), (3, 4), (3, 5), (3, 6),
(4, 1), (4, 2), (4, 3), (4, 4), (4, 5), (4, 6),
(5, 1), (5, 2), (5, 3), (5, 4), (5, 5), (5, 6),
(6, 1), (6, 2), (6, 3), (6, 4), (6, 5), (6, 6),
(7, 1), (7, 2), (7, 3), (7, 4), (7, 5), (7, 6),
(8, 1), (8, 2), (8, 3), (8, 4), (8, 5), (8, 6),
(9, 1), (9, 2), (9, 3), (9, 4), (9, 5), (9, 6),
(10, 1), (10, 2), (10, 3), (10, 4), (10, 5), (10, 6),
(11, 1), (11, 2), (11, 3), (11, 4), (11, 5), (11, 6),
(12, 1), (12, 2), (12, 3), (12, 4), (12, 5), (12, 6),
(13, 1), (13, 2), (13, 3), (13, 4), (13, 5), (13, 6),
(14, 1), (14, 2), (14, 3), (14, 4), (14, 5), (14, 6),
(15, 1), (15, 2), (15, 3), (15, 4), (15, 5), (15, 6),
(16, 1), (16, 2), (16, 3), (16, 4), (16, 5), (16, 6),
(17, 1), (17, 2), (17, 3), (17, 4), (17, 5), (17, 6),
(18, 1), (18, 2), (18, 3), (18, 4), (18, 5), (18, 6),
(19, 1), (19, 2), (19, 3), (19, 4), (19, 5), (19, 6),
(20, 1), (20, 2), (20, 3), (20, 4), (20, 5), (20, 6),
(21, 1), (21, 2), (21, 3), (21, 4), (21, 5), (21, 6),
(22, 1), (22, 2), (22, 3), (22, 4), (22, 5), (22, 6),
(23, 1), (23, 2), (23, 3), (23, 4), (23, 5), (23, 6),
(24, 1), (24, 2), (24, 3), (24, 4), (24, 5), (24, 6),
(25, 1), (25, 2), (25, 3), (25, 4), (25, 5), (25, 6),
(26, 1), (26, 2), (26, 3), (26, 4), (26, 5), (26, 6),
(27, 1), (27, 2), (27, 3), (27, 4), (27, 5), (27, 6),
(28, 1), (28, 2), (28, 3), (28, 4), (28, 5), (28, 6);
