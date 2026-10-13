-- ============================================================================
--  Web-Based Talent Mapping System for FSTP Lecturers
--  Database schema + sample data
--
--  UPNM | FSTP | PSM TSK 3306
--  Student : Lailati binti Mohd (2241073)
--  Database: fstp_talent_map      Engine: InnoDB      Charset: utf8mb4
--  Target  : MariaDB 10.4.32 (XAMPP 8.2.12)
--
--  HOW TO IMPORT
--    phpMyAdmin : Import -> choose this file -> Go
--    CLI        : "C:\xampp\mysql\bin\mysql.exe" -u root < fstp_talent_map.sql
--
--  !! RE-RUNNING THIS FILE RESETS THE DATABASE AND ALL SAMPLE DATA !!
--
--  SEED LOGINS (change before any demo you hand over)
--    Pentadbir : admin           / Admin@123
--    Pensyarah : aisyah, harun, farnad, ahmad, nurul, zulkifli,
--                sitiaminah, firdaus, norhayati, khairul, rahimah, syafiq
--                all with   Pensyarah@123
-- ============================================================================

CREATE DATABASE IF NOT EXISTS fstp_talent_map
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fstp_talent_map;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS activity_log, documents, achievements, consultancy, training,
                     certifications, publications, research, lecturer_expertise,
                     expertise, expertise_categories, lecturers, users, departments;
SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================================
--  1. departments
-- ============================================================================
CREATE TABLE departments (
  department_id   INT AUTO_INCREMENT PRIMARY KEY,
  department_code VARCHAR(20)  NOT NULL UNIQUE,
  department_name VARCHAR(100) NOT NULL UNIQUE,
  description     TEXT         NULL,
  status          ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  2. users  (login accounts - Pentadbir and Pensyarah share this table)
-- ============================================================================
CREATE TABLE users (
  user_id          INT AUTO_INCREMENT PRIMARY KEY,
  username         VARCHAR(50)  NOT NULL UNIQUE,
  email            VARCHAR(100) NOT NULL UNIQUE,
  password         VARCHAR(255) NOT NULL COMMENT 'password_hash() bcrypt',
  `role`           ENUM('admin','lecturer') NOT NULL DEFAULT 'lecturer',
  department_id    INT          NULL,
  status           ENUM('active','inactive') NOT NULL DEFAULT 'active',
  initial_password VARCHAR(50)  NULL COMMENT 'shown once when Admin resets',
  must_change_pw   TINYINT(1)   NOT NULL DEFAULT 0,
  last_login       DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id)
    REFERENCES departments(department_id) ON DELETE SET NULL,
  INDEX idx_users_role (role),
  INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  3. lecturers  (professional profile - 1:1 with users)
-- ============================================================================
CREATE TABLE lecturers (
  lecturer_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT          NOT NULL UNIQUE,
  staff_no      VARCHAR(20)  NOT NULL UNIQUE,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(100) NOT NULL,
  department_id INT          NOT NULL,
  `position`    VARCHAR(100) NOT NULL,
  phone         VARCHAR(20)  NULL,
  location      VARCHAR(100) NULL,
  profile_photo VARCHAR(255) NULL,
  research_area TEXT         NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_lecturers_user       FOREIGN KEY (user_id)       REFERENCES users(user_id)       ON DELETE CASCADE,
  CONSTRAINT fk_lecturers_department FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE RESTRICT,
  INDEX idx_lecturers_name (full_name),
  INDEX idx_lecturers_dept (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  4. expertise_categories  (master list managed by Pentadbir)
-- ============================================================================
CREATE TABLE expertise_categories (
  category_id   INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE,
  description   TEXT         NULL,
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  5. expertise  (catalogue of expertise areas, grouped by category)
-- ============================================================================
CREATE TABLE expertise (
  expertise_id   INT AUTO_INCREMENT PRIMARY KEY,
  category_id    INT          NOT NULL,
  expertise_name VARCHAR(100) NOT NULL UNIQUE,
  description    TEXT         NULL,
  status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expertise_category FOREIGN KEY (category_id)
    REFERENCES expertise_categories(category_id) ON DELETE RESTRICT,
  INDEX idx_expertise_name (expertise_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  6. lecturer_expertise  (M:N junction - a lecturer holds many expertise)
-- ============================================================================
CREATE TABLE lecturer_expertise (
  lecturer_expertise_id INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id           INT NOT NULL,
  expertise_id          INT NOT NULL,
  expertise_level       ENUM('Beginner','Intermediate','Advanced','Expert') NOT NULL DEFAULT 'Beginner',
  years_experience      INT NOT NULL DEFAULT 0,
  research_interest     TEXT NULL COMMENT 'Research Interest (optional)',
  supporting_document   VARCHAR(255) NULL,
  status                ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_le_lecturer  FOREIGN KEY (lecturer_id)  REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  CONSTRAINT fk_le_expertise FOREIGN KEY (expertise_id) REFERENCES expertise(expertise_id) ON DELETE CASCADE,
  UNIQUE KEY uq_lecturer_expertise (lecturer_id, expertise_id),
  INDEX idx_le_expertise (expertise_id),
  INDEX idx_le_level (expertise_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  7. research
-- ============================================================================
CREATE TABLE research (
  research_id         INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id         INT NOT NULL,
  title               VARCHAR(255) NOT NULL,
  research_area       VARCHAR(150) NOT NULL,
  research_category   VARCHAR(100) NULL COMMENT 'Applied / Basic / Action Research',
  `year`              YEAR NULL,
  status              ENUM('Ongoing','Completed') NOT NULL DEFAULT 'Ongoing',
  description         TEXT NULL,
  supporting_document VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_research_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  INDEX idx_research_lecturer (lecturer_id),
  INDEX idx_research_year (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  8. publications
-- ============================================================================
CREATE TABLE publications (
  publication_id      INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id         INT NOT NULL,
  title               VARCHAR(255) NOT NULL,
  publication_type    VARCHAR(50)  NOT NULL COMMENT 'Journal Article, Conference Paper, Book...',
  journal_publisher   VARCHAR(150) NOT NULL,
  `year`              YEAR NULL,
  status              ENUM('Published','In Press','Submitted') NOT NULL DEFAULT 'Published',
  doi_link            VARCHAR(255) NULL,
  supporting_document VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pub_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  INDEX idx_pub_lecturer (lecturer_id),
  INDEX idx_pub_year (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  9. certifications
-- ============================================================================
CREATE TABLE certifications (
  certification_id    INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id         INT NOT NULL,
  certification_name  VARCHAR(150) NOT NULL,
  issuing_body        VARCHAR(150) NOT NULL,
  issue_date          DATE NOT NULL,
  expiry_date         DATE NULL,
  certificate_number  VARCHAR(50)  NULL,
  supporting_document VARCHAR(255) NULL,
  status              ENUM('active','expired') NOT NULL DEFAULT 'active',
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cert_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  INDEX idx_cert_lecturer (lecturer_id),
  INDEX idx_cert_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  10. training
-- ============================================================================
CREATE TABLE training (
  training_id         INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id         INT NOT NULL,
  training_name       VARCHAR(150) NOT NULL,
  organizer           VARCHAR(150) NOT NULL,
  `date`              DATE NOT NULL,
  duration_days       INT NOT NULL DEFAULT 1,
  status              ENUM('Completed','Ongoing','Planned') NOT NULL DEFAULT 'Completed',
  description         TEXT NULL,
  supporting_document VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_training_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  INDEX idx_training_lecturer (lecturer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  11. consultancy
-- ============================================================================
CREATE TABLE consultancy (
  consultancy_id      INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id         INT NOT NULL,
  project_name        VARCHAR(200) NOT NULL,
  organization        VARCHAR(150) NOT NULL,
  `role`              VARCHAR(100) NOT NULL,
  `year`              YEAR NULL,
  status              ENUM('Ongoing','Completed') NOT NULL DEFAULT 'Completed',
  description         TEXT NULL,
  supporting_document VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cons_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  INDEX idx_cons_lecturer (lecturer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  12. achievements
-- ============================================================================
CREATE TABLE achievements (
  achievement_id      INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id         INT NOT NULL,
  achievement_name    VARCHAR(200) NOT NULL,
  category            VARCHAR(100) NULL COMMENT 'Research / Teaching / Service',
  `year`              YEAR NULL,
  description         TEXT NULL,
  supporting_document VARCHAR(255) NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ach_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  INDEX idx_ach_lecturer (lecturer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  13. documents
-- ============================================================================
CREATE TABLE documents (
  document_id   INT AUTO_INCREMENT PRIMARY KEY,
  lecturer_id   INT NOT NULL,
  uploaded_by   INT NOT NULL,
  document_type VARCHAR(50)  NOT NULL COMMENT 'CV, Portfolio, Report, Certificate',
  title         VARCHAR(150) NOT NULL,
  file_name     VARCHAR(255) NOT NULL,
  file_path     VARCHAR(255) NOT NULL,
  file_size     INT NULL,
  upload_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status        ENUM('active','archived') NOT NULL DEFAULT 'active',
  CONSTRAINT fk_doc_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(lecturer_id) ON DELETE CASCADE,
  CONSTRAINT fk_doc_uploader FOREIGN KEY (uploaded_by) REFERENCES users(user_id)      ON DELETE CASCADE,
  INDEX idx_doc_lecturer (lecturer_id),
  INDEX idx_doc_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  14. activity_log  (audit trail feeding "Recent Activities")
-- ============================================================================
CREATE TABLE activity_log (
  activity_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  activity_type VARCHAR(50) NOT NULL COMMENT 'insert, update, delete, upload, login',
  entity        VARCHAR(50) NULL,
  entity_id     INT NULL,
  description   VARCHAR(255) NOT NULL,
  ip_address    VARCHAR(45) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_log_user (user_id),
  INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
--  SAMPLE DATA
-- ============================================================================

INSERT INTO departments (department_id, department_code, department_name, description) VALUES
  (1,'CS','Computer Science','Jabatan Sains Komputer'),
  (2,'IT','Information Technology','Jabatan Teknologi Maklumat'),
  (3,'MATH','Mathematics','Jabatan Matematik'),
  (4,'ENG','Engineering','Jabatan Kejuruteraan'),
  (5,'OTH','Others','Jabatan / unit lain');

-- 12 categories (matches the "12 Expertise Categories" KPI on the Admin dashboard)
INSERT INTO expertise_categories (category_id, category_name, description) VALUES
  (1,'Cybersecurity','Keselamatan siber dan pertahanan rangkaian'),
  (2,'Artificial Intelligence','Kecerdasan buatan dan sistem pintar'),
  (3,'Networking','Rangkaian komputer dan komunikasi data'),
  (4,'Database','Pangkalan data dan pengurusan data'),
  (5,'Digital Forensics','Forensik digital dan siasatan siber'),
  (6,'Software Engineering','Kejuruteraan perisian dan pembangunan sistem'),
  (7,'Information Security','Keselamatan maklumat dan tadbir urus'),
  (8,'Data Science','Sains data dan analitik'),
  (9,'Cloud Computing','Pengkomputeran awan'),
  (10,'Internet of Things','Internet bagi segala (IoT)'),
  (11,'Machine Learning','Pembelajaran mesin'),
  (12,'Others','Bidang kepakaran lain');

INSERT INTO expertise (expertise_id, category_id, expertise_name, description) VALUES
  (1, 1,'Cybersecurity',NULL),
  (2, 1,'Cyber Threat',NULL),
  (3, 1,'Network Security',NULL),
  (4, 7,'Information Security',NULL),
  (5, 7,'Security Audit',NULL),
  (6, 7,'Cyber Law',NULL),
  (7, 7,'Security Policy',NULL),
  (8, 5,'Digital Forensics',NULL),
  (9, 5,'Incident Response',NULL),
  (10, 2,'Artificial Intelligence',NULL),
  (11, 11,'Machine Learning',NULL),
  (12, 11,'Deep Learning',NULL),
  (13, 2,'Natural Language Processing',NULL),
  (14, 3,'Networking',NULL),
  (15, 3,'Wireless Networks',NULL),
  (16, 3,'Network Administration',NULL),
  (17, 4,'Database',NULL),
  (18, 4,'Big Data',NULL),
  (19, 6,'Software Engineering',NULL),
  (20, 6,'Web Development',NULL),
  (21, 8,'Data Science',NULL),
  (22, 8,'Data Analytics',NULL),
  (23, 9,'Cloud Computing',NULL),
  (24, 10,'Internet of Things',NULL),
  (25, 12,'Project Management',NULL);

-- Users: 1 Pentadbir + 12 Pensyarah
INSERT INTO users (user_id, username, email, password, `role`, department_id, status) VALUES
  (1,'admin','admin@upnm.edu.my',      '$2y$10$6V4t9i2AKRxzgov14tFGn.nOEd6U/rCdq2MJk1dPLrp3zLKlYP0jq','admin',    1,'active'),
  (2,'aisyah','aisyah@upnm.edu.my',    '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 1,'active'),
  (3,'harun','harun@upnm.edu.my',      '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 2,'active'),
  (4,'farnad','farnad@upnm.edu.my',    '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 1,'active'),
  (5,'ahmad','ahmad@upnm.edu.my',      '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 1,'active'),
  (6,'nurul','nurul@upnm.edu.my',      '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 2,'active'),
  (7,'zulkifli','zulkifli@upnm.edu.my','$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 4,'active'),
  (8,'sitiaminah','sitiaminah@upnm.edu.my','$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 3,'active'),
  (9,'firdaus','firdaus@upnm.edu.my',  '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 2,'active'),
  (10,'norhayati','norhayati@upnm.edu.my','$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 3,'active'),
  (11,'khairul','khairul@upnm.edu.my', '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 1,'active'),
  (12,'rahimah','rahimah@upnm.edu.my', '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 4,'active'),
  (13,'syafiq','syafiq@upnm.edu.my',   '$2y$10$ICJ5cnAV4HLtpFLIeuBjEOa6/QMyVxC8bTMcTZYexySAsUUly1am.','lecturer', 5,'active');

INSERT INTO lecturers (lecturer_id, user_id, staff_no, full_name, email, department_id, `position`, phone, location, research_area) VALUES
  (1, 2,'UPNM001','Dr. Aisyah Binti Ahmad','aisyah@upnm.edu.my',       1,'Senior Lecturer','+60 12-345 6789','Kuala Lumpur, Malaysia','Cyber Threat Detection, Network Security, Digital Forensics'),
  (2, 3,'UPNM002','Prof. Dr. Harun Bin Ismail','harun@upnm.edu.my',    2,'Professor','+60 13-222 1188','Kuala Lumpur, Malaysia','Information Security, Security Audit'),
  (3, 4,'UPNM003','Dr. Farnad Binti Omar','farnad@upnm.edu.my',        1,'Senior Lecturer','+60 19-887 4410','Kajang, Selangor','Digital Forensics, Incident Response'),
  (4, 5,'UPNM004','Dr. Ahmad Bin Zulkifli','ahmad@upnm.edu.my',        1,'Lecturer','+60 11-234 7788','Seremban, Negeri Sembilan','Cyber Law, Security Policy'),
  (5, 6,'UPNM005','Dr. Nurul Huda Binti Salleh','nurul@upnm.edu.my',   2,'Senior Lecturer','+60 12-909 3321','Shah Alam, Selangor','Wireless Networks, IoT'),
  (6, 7,'UPNM006','Prof. Madya Dr. Zulkifli Bin Rahman','zulkifli@upnm.edu.my', 4,'Associate Professor','+60 17-556 2201','Kuala Lumpur, Malaysia','Systems Engineering'),
  (7, 8,'UPNM007','Dr. Siti Aminah Binti Yusof','sitiaminah@upnm.edu.my', 3,'Senior Lecturer','+60 13-778 9042','Putrajaya, Malaysia','Applied Mathematics, Data Analytics'),
  (8, 9,'UPNM008','Dr. Muhammad Firdaus Bin Omar','firdaus@upnm.edu.my', 2,'Lecturer','+60 14-221 6677','Klang, Selangor','Cloud Computing, Networking'),
  (9,10,'UPNM009','Dr. Norhayati Binti Hassan','norhayati@upnm.edu.my',  3,'Lecturer','+60 18-334 5512','Nilai, Negeri Sembilan','Statistics, Machine Learning'),
  (10,11,'UPNM010','Dr. Khairul Anuar Bin Mohd','khairul@upnm.edu.my',   1,'Senior Lecturer','+60 12-447 8890','Kuala Lumpur, Malaysia','Artificial Intelligence, Deep Learning'),
  (11,12,'UPNM011','Dr. Rahimah Binti Abdullah','rahimah@upnm.edu.my',   4,'Lecturer','+60 16-993 2210','Bangi, Selangor','Engineering Education'),
  (12,13,'UPNM012','Dr. Syafiq Bin Aziz','syafiq@upnm.edu.my',           5,'Lecturer','+60 19-662 7734','Kuala Lumpur, Malaysia','Project Management');

-- lecturer_expertise (Aisyah's five rows mirror the Talent Mapping mockup)
INSERT INTO lecturer_expertise (lecturer_id, expertise_id, expertise_level, years_experience, research_interest) VALUES
  (1, 1,'Expert',8,'Cyber threat detection in IoT environments'),
  (1, 8,'Advanced',5,'Mobile device forensics'),
  (1,14,'Intermediate',4,'Software-defined networking'),
  (1,10,'Intermediate',4,NULL),
  (1,17,'Beginner',2,NULL),
  (2, 4,'Expert',15,'Information security governance'),
  (2, 5,'Expert',12,'Security audit frameworks'),
  (2, 3,'Advanced',10,NULL),
  (3, 8,'Advanced',6,'Digital evidence handling'),
  (3, 9,'Advanced',5,'Incident response automation'),
  (4, 6,'Advanced',7,'Cyber law and policy in Malaysia'),
  (4, 7,'Advanced',6,NULL),
  (5,15,'Advanced',9,'Wireless sensor networks'),
  (5,24,'Intermediate',5,'IoT for defence applications'),
  (5,14,'Advanced',11,NULL),
  (6,19,'Expert',18,'Systems engineering process improvement'),
  (7,21,'Expert',12,'Data analytics for education'),
  (7,22,'Advanced',9,NULL),
  (8,23,'Advanced',6,'Cloud migration strategy'),
  (8,14,'Intermediate',5,NULL),
  (8,16,'Intermediate',4,'Network administration automation'),
  (9,21,'Advanced',7,'Statistical learning'),
  (9,11,'Advanced',6,'Machine learning for forecasting'),
  (10,10,'Expert',10,'Deep learning for cyber threat detection'),
  (10,12,'Advanced',7,NULL),
  (10,11,'Expert',9,'Applied machine learning'),
  (11,19,'Intermediate',5,'Engineering curriculum design'),
  (12,25,'Advanced',8,'Defence project management');

INSERT INTO research (lecturer_id, title, research_area, research_category, `year`, status, description) VALUES
  (1,'Cyber Threat Detection in IoT Environment','Cybersecurity','Applied Research',2024,'Ongoing','This research focuses on developing a threat detection model for IoT devices using machine learning.'),
  (1,'Real-Time Intrusion Detection for Campus Networks','Network Security','Applied Research',2023,'Completed','Signature and anomaly hybrid IDS tuned for university network traffic.'),
  (2,'Information Security Governance Framework for Public Universities','Information Security','Applied Research',2024,'Ongoing','A governance model aligned to ISO 27001 for Malaysian public universities.'),
  (2,'Security Audit Maturity in Defence Agencies','Security Audit','Applied Research',2022,'Completed',NULL),
  (3,'Mobile Device Forensics for Android Artefacts','Digital Forensics','Applied Research',2025,'Ongoing','Recovery and validation of deleted artefacts on modern Android devices.'),
  (4,'Legal Admissibility of Digital Evidence in Malaysia','Cyber Law','Basic Research',2024,'Ongoing',NULL),
  (5,'Low-Power Wireless Sensor Networks for Perimeter Monitoring','Wireless Networks','Applied Research',2024,'Ongoing',NULL),
  (5,'IoT Architecture for Defence Installations','Internet of Things','Applied Research',2023,'Completed',NULL),
  (6,'Systems Engineering Process Improvement in Defence Acquisition','Systems Engineering','Applied Research',2022,'Completed',NULL),
  (7,'Learning Analytics for Undergraduate Performance','Data Science','Applied Research',2025,'Ongoing',NULL),
  (9,'Forecasting Models for Defence Resource Planning','Statistics','Applied Research',2024,'Ongoing',NULL),
  (10,'Deep Learning Approaches for Malware Classification','Artificial Intelligence','Applied Research',2025,'Ongoing','CNN and transformer comparison on malware image datasets.'),
  (10,'Explainable AI for Security Operations Centres','Artificial Intelligence','Applied Research',2024,'Completed',NULL),
  (12,'Risk Management in Defence Project Delivery','Project Management','Applied Research',2023,'Completed',NULL);

INSERT INTO publications (lecturer_id, title, publication_type, journal_publisher, `year`, status, doi_link) VALUES
  (1,'A Review of Cyber Threats in Smart Cities','Journal Article','IEEE Access',2024,'Published','https://doi.org/10.1109/10.xxxx'),
  (1,'Machine Learning for IoT Intrusion Detection: A Survey','Journal Article','Computers & Security',2023,'Published',NULL),
  (1,'Detecting Botnet Traffic Using Ensemble Methods','Conference Paper','IEEE ICOCO',2024,'Published',NULL),
  (1,'Threat Modelling for Smart Campus Deployments','Conference Paper','Springer ICICT',2022,'Published',NULL),
  (1,'Securing IoT Gateways: Challenges and Directions','Journal Article','Journal of Cybersecurity',2025,'In Press',NULL),
  (2,'An ISO 27001 Implementation Roadmap for Public Universities','Journal Article','Information Security Journal',2023,'Published',NULL),
  (2,'Measuring Security Audit Maturity: A Case Study','Conference Paper','IEEE ISCAIE',2022,'Published',NULL),
  (2,'Governance Gaps in Institutional Information Security','Journal Article','Computers & Security',2024,'Published',NULL),
  (3,'Android Forensics: Artefact Recovery Techniques','Journal Article','Digital Investigation',2024,'Published',NULL),
  (3,'Automating Incident Response Playbooks','Conference Paper','Springer ICDF',2025,'Submitted',NULL),
  (4,'Digital Evidence and Malaysian Evidence Act: A Legal Review','Journal Article','Malaysian Journal of Law and Society',2024,'Published',NULL),
  (5,'Energy-Aware Routing for Perimeter Sensor Networks','Journal Article','Wireless Networks',2023,'Published',NULL),
  (5,'A Lightweight IoT Gateway for Defence Monitoring','Conference Paper','IEEE TENCON',2024,'Published',NULL),
  (9,'Statistical Forecasting of Resource Demand','Journal Article','Journal of Applied Statistics',2024,'Published',NULL),
  (10,'Malware Classification with Convolutional Networks','Journal Article','IEEE Transactions on Information Forensics',2025,'Published',NULL),
  (10,'Explainable Models for Security Analysts','Conference Paper','Springer AIsec',2024,'Published',NULL);

INSERT INTO certifications (lecturer_id, certification_name, issuing_body, issue_date, expiry_date, certificate_number) VALUES
  (1,'Certified Information Systems Security Professional (CISSP)','(ISC)2','2023-06-15','2026-06-15','CISSP-123456'),
  (1,'Certified Ethical Hacker (CEH)','EC-Council','2022-03-10','2025-03-10','CEH-778112'),
  (2,'Certified Information Security Manager (CISM)','ISACA','2021-09-01','2027-09-01','CISM-445120'),
  (2,'ISO 27001 Lead Auditor','PECB','2022-11-20','2025-11-20','PECB-99120'),
  (3,'GIAC Certified Forensic Analyst (GCFA)','GIAC','2024-02-05','2028-02-05','GCFA-33210'),
  (4,'Certified Cyber Law Practitioner','Malaysian Bar','2023-08-12','2026-08-12','CCLP-88231'),
  (5,'Cisco Certified Network Associate (CCNA)','Cisco','2023-01-30','2026-01-30','CCNA-551209'),
  (8,'AWS Certified Solutions Architect','Amazon Web Services','2024-05-18','2027-05-18','AWS-771290'),
  (10,'TensorFlow Developer Certificate','Google','2024-07-22','2027-07-22','TF-662019');

INSERT INTO training (lecturer_id, training_name, organizer, `date`, duration_days, status, description) VALUES
  (1,'Ethical Hacking and Penetration Testing','EC-Council','2024-03-12',5,'Completed','Hands-on training on penetration testing tools and techniques.'),
  (1,'IoT Security Workshop','CyberSecurity Malaysia','2023-08-14',3,'Completed',NULL),
  (2,'ISO 27001 Lead Implementer','PECB','2022-05-09',5,'Completed',NULL),
  (3,'Advanced Digital Forensics','SANS Institute','2024-04-22',6,'Completed',NULL),
  (3,'Incident Response Tabletop Exercise','CyberSecurity Malaysia','2025-02-11',2,'Completed',NULL),
  (4,'Cyber Law and Digital Evidence','Malaysian Bar','2023-11-06',3,'Completed',NULL),
  (5,'Wireless Network Design','Cisco Networking Academy','2023-06-19',4,'Completed',NULL),
  (6,'Systems Engineering Fundamentals','INCOSE','2022-03-01',5,'Completed',NULL),
  (8,'Cloud Architecture on AWS','Amazon Web Services','2024-02-19',5,'Completed',NULL),
  (9,'Applied Machine Learning with Python','Coursera','2024-09-02',4,'Completed',NULL),
  (11,'Outcome-Based Education Workshop','UPNM Academic Development Centre','2025-01-20',2,'Completed',NULL);

INSERT INTO consultancy (lecturer_id, project_name, organization, `role`, `year`, status, description) VALUES
  (1,'Network Security Assessment for Government Agency','Agensi Keselamatan Negara','Project Leader',2024,'Completed','Conducted security assessment and provided recommendations.'),
  (1,'Smart Campus Cyber Risk Review','UPNM ICT Centre','Consultant',2023,'Completed',NULL),
  (2,'Information Security Policy Development','Ministry of Defence','Lead Consultant',2023,'Completed',NULL),
  (3,'Digital Forensics Capability Building','Royal Malaysia Police','Technical Advisor',2024,'Ongoing',NULL),
  (5,'Perimeter Sensor Deployment Study','Armed Forces Installation','Consultant',2024,'Completed',NULL),
  (8,'Cloud Migration Advisory','Malaysian Administrative Modernisation Unit','Consultant',2023,'Completed',NULL),
  (10,'AI Adoption Roadmap for Security Operations','CyberSecurity Malaysia','Technical Advisor',2025,'Ongoing',NULL);

INSERT INTO achievements (lecturer_id, achievement_name, category, `year`, description) VALUES
  (1,'Best Researcher Award','Research',2024,'Awarded for outstanding research contributions in cybersecurity.'),
  (1,'Excellent Service Award','Service',2023,NULL),
  (2,'Outstanding Academic Leadership Award','Service',2022,NULL),
  (3,'Best Paper Award - ICDF 2025','Research',2025,NULL),
  (5,'Innovation Award - Defence Technology Exhibition','Research',2024,NULL),
  (7,'Teaching Excellence Award','Teaching',2023,NULL),
  (10,'Best Paper Award - AIsec 2024','Research',2024,NULL),
  (11,'Academic Advisor of the Year','Teaching',2024,NULL);

INSERT INTO documents (lecturer_id, uploaded_by, document_type, title, file_name, file_path, file_size) VALUES
  (1, 2,'CV','Latest CV 2024','CV_Aisyah.pdf','assets/uploads/documents/sample/cv_aisyah.pdf',562123),
  (1, 2,'Portfolio','Teaching Portfolio','Portfolio_Aisyah.pdf','assets/uploads/documents/sample/portfolio_aisyah.pdf',881240),
  (1, 2,'Report','Consultancy Report 2024','Consultancy_Report_2024.pdf','assets/uploads/documents/sample/consultancy_2024.pdf',430112),
  (2, 3,'CV','CV Prof Harun 2024','CV_Harun.pdf','assets/uploads/documents/sample/cv_harun.pdf',610233),
  (3, 4,'Certificate','GCFA Certificate','GCFA_Farnad.pdf','assets/uploads/documents/sample/gcfa_farnad.pdf',215004),
  (4, 5,'CV','CV Ahmad 2024','CV_Ahmad.pdf','assets/uploads/documents/sample/cv_ahmad.pdf',402331),
  (5, 6,'Report','IoT Study Report','IoT_Study_Report.pdf','assets/uploads/documents/sample/iot_report.pdf',720450),
  (8, 9,'Certificate','AWS SAA Certificate','AWS_Firdaus.pdf','assets/uploads/documents/sample/aws_firdaus.pdf',198772),
  (10,11,'CV','CV Khairul 2025','CV_Khairul.pdf','assets/uploads/documents/sample/cv_khairul.pdf',598120),
  (10,11,'Portfolio','Research Portfolio','Portfolio_Khairul.pdf','assets/uploads/documents/sample/portfolio_khairul.pdf',664901);

INSERT INTO activity_log (user_id, activity_type, entity, entity_id, description, created_at) VALUES
  (2,'insert','certifications',1,'Added new certification','2026-08-17 09:14:00'),
  (2,'update','research',1,'Updated research information','2026-08-15 14:32:00'),
  (2,'insert','publications',2,'Added new publication','2026-08-10 11:05:00'),
  (2,'update','lecturers',1,'Updated profile photo','2026-08-08 16:41:00'),
  (3,'update','lecturers',2,'Updated profile information','2026-08-14 10:22:00'),
  (3,'insert','publications',6,'Added new publication','2026-08-12 13:57:00'),
  (4,'insert','certifications',6,'Added new certification','2026-08-11 08:35:00'),
  (5,'insert','consultancy',3,'Added new consultancy record','2026-08-09 15:18:00'),
  (5,'update','lecturers',5,'Updated research area','2026-08-07 12:03:00'),
  (10,'insert','research',12,'Added new research project','2026-08-16 09:48:00'),
  (10,'insert','achievements',7,'Added new achievement','2026-08-13 17:26:00'),
  (1,'insert','users',13,'Created new lecturer account','2026-08-05 16:20:00');

-- Keep AUTO_INCREMENT ahead of the seeded ids
ALTER TABLE departments          AUTO_INCREMENT = 100;
ALTER TABLE expertise_categories AUTO_INCREMENT = 100;
ALTER TABLE expertise            AUTO_INCREMENT = 100;
ALTER TABLE users                AUTO_INCREMENT = 100;
ALTER TABLE lecturers            AUTO_INCREMENT = 100;
ALTER TABLE research             AUTO_INCREMENT = 100;
ALTER TABLE publications         AUTO_INCREMENT = 100;
ALTER TABLE certifications       AUTO_INCREMENT = 100;
ALTER TABLE training             AUTO_INCREMENT = 100;
ALTER TABLE consultancy          AUTO_INCREMENT = 100;
ALTER TABLE achievements         AUTO_INCREMENT = 100;
ALTER TABLE documents            AUTO_INCREMENT = 100;

-- ============================================================================
--  END OF FILE
-- ============================================================================
