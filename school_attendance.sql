DROP DATABASE IF EXISTS school_attendance_system;
CREATE DATABASE school_attendance_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE school_attendance_system;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'student', 'parent', 'lecturer', 'systemadmin') NOT NULL DEFAULT 'student',
  fingerprint_credential_id VARCHAR(255) NULL,
  fingerprint_public_key_jwk TEXT NULL,
  fingerprint_sign_count INT UNSIGNED NOT NULL DEFAULT 0,
  fingerprint_registered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  title VARCHAR(255) NOT NULL,
  credits TINYINT NOT NULL,
  semester TINYINT NOT NULL,
  progress TINYINT NOT NULL DEFAULT 0,
  status ENUM('ongoing', 'completed') NOT NULL DEFAULT 'ongoing',
  lecturer_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  course_id INT NOT NULL,
  attendance_percent TINYINT NOT NULL DEFAULT 0,
  absences TINYINT NOT NULL DEFAULT 0,
  last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE parent_child (
  parent_id INT NOT NULL,
  student_id INT NOT NULL,
  PRIMARY KEY (parent_id, student_id),
  FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(255) NOT NULL,
  user_name VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', '$2y$10$vZOhQaQ/qZJe..IFyHF14OE7TXPdyEhoMU2iYCit2L.kBfR7iMPsG', 'admin'),
('Student User', 'student@example.com', '$2y$10$LlOPzIc9r/dx/2IvYG3b/.c3Qergne.v.D3R1DPrRIlKO44kyzviW', 'student'),
('Parent User', 'parent@example.com', '$2y$10$BBZLCaSzlKp7324JQUcm1Ormp99.ZWCFQjZNgRxfIlIETrAueg9Qm', 'parent'),
('Lecturer User', 'lecturer@example.com', '$2y$10$4B6sRn17ZvU6kCplmOGY5OwnGEdYyYw2DqXdF8Jol91mB586tB2le', 'lecturer'),
('System Admin', 'sysadmin@example.com', '$2y$10$TajjRx6.ONCfZnrZ9VV8h.Hipo8O5mocA8L4XKrAzA0BstifQhqmO', 'systemadmin');

INSERT INTO courses (code, title, credits, semester, progress, status, lecturer_id) VALUES
('CSC301', 'Object Oriented Programming', 3, 1, 78, 'ongoing', 4),
('CSC305', 'Database Management Systems', 3, 1, 65, 'ongoing', 4),
('MAT211', 'Discrete Mathematics', 4, 1, 100, 'completed', 4),
('ENG205', 'Technical Communication', 2, 1, 100, 'completed', 4),
('CSC307', 'Data Structures & Algorithms', 3, 1, 54, 'ongoing', 4);

INSERT INTO attendance (student_id, course_id, attendance_percent, absences) VALUES
(2, 1, 92, 1),
(2, 2, 88, 2),
(2, 5, 95, 0);

INSERT INTO parent_child (parent_id, student_id) VALUES
(3, 2);

INSERT INTO system_logs (action, user_name) VALUES
('Database seeded for school attendance system', 'system');
