-- ============================================================================
--  CAMPUSPHERE  ·  Aurora National High School
--  Database schema  ·  MySQL 8.x / MariaDB 10.4+  ·  InnoDB  ·  utf8mb4
--
--  Import with Navicat / phpMyAdmin / `mysql < schema.sql`, or let
--  `php database/install.php` create everything for you.
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
--  Reference / configuration
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_years` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label`       VARCHAR(20)  NOT NULL,                 -- e.g. 2026–2027
  `start_date`  DATE         NOT NULL,
  `end_date`    DATE         NOT NULL,
  `term_label`  VARCHAR(40)  NOT NULL DEFAULT 'First Semester',
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_school_years_label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT        NULL,
  `updated_at`    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subjects` (
  `id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`  VARCHAR(12)  NOT NULL,
  `name`  VARCHAR(80)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_name` (`name`),
  UNIQUE KEY `uq_subjects_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Identity & access
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`              VARCHAR(60)  NOT NULL,
  `email`                 VARCHAR(150) NULL,
  `password_hash`         VARCHAR(255) NOT NULL,
  `role`                  ENUM('Administrator','Teacher','Student','Guidance') NOT NULL,
  `full_name`             VARCHAR(120) NOT NULL,
  `initials`              VARCHAR(4)   NOT NULL,
  `is_active`             TINYINT(1)   NOT NULL DEFAULT 1,
  `must_change_password`  TINYINT(1)   NOT NULL DEFAULT 0,
  `last_login_at`         DATETIME     NULL,
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`    VARCHAR(100) NOT NULL,
  `ip_address`  VARCHAR(45)  NOT NULL,
  `success`     TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_user_time` (`username`, `created_at`),
  KEY `idx_login_ip_time` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `username`    VARCHAR(60)  NULL,
  `action`      VARCHAR(60)  NOT NULL,
  `entity`      VARCHAR(60)  NOT NULL,
  `entity_id`   VARCHAR(40)  NULL,
  `details`     TEXT         NULL,
  `ip_address`  VARCHAR(45)  NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_time` (`created_at`),
  KEY `idx_audit_entity` (`entity`, `entity_id`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Academic structure
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teachers` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `employee_no`  VARCHAR(20)  NOT NULL,
  `full_name`    VARCHAR(120) NOT NULL,
  `department`   VARCHAR(80)  NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teachers_user` (`user_id`),
  UNIQUE KEY `uq_teachers_employee` (`employee_no`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sections` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_year_id`  INT UNSIGNED NOT NULL,
  `grade`           TINYINT UNSIGNED NOT NULL,
  `name`            VARCHAR(60)  NOT NULL,
  `adviser_id`      INT UNSIGNED NULL,
  `capacity`        SMALLINT UNSIGNED NOT NULL DEFAULT 40,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sections_sy_grade_name` (`school_year_id`, `grade`, `name`),
  KEY `idx_sections_adviser` (`adviser_id`),
  CONSTRAINT `fk_sections_sy` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  CONSTRAINT `fk_sections_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_sections_grade` CHECK (`grade` BETWEEN 7 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_assignments` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id`    INT UNSIGNED NOT NULL,
  `section_id`    INT UNSIGNED NOT NULL,
  `subject_id`    INT UNSIGNED NOT NULL,
  `period_start`  TIME NULL,                              -- first period time, used for gate/class checks
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_assign_section_subject` (`section_id`, `subject_id`),
  KEY `idx_assign_teacher` (`teacher_id`),
  CONSTRAINT `fk_assign_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `students` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_no`         VARCHAR(20)  NOT NULL,              -- e.g. ANHS-2026-001
  `user_id`            INT UNSIGNED NULL,
  `full_name`          VARCHAR(120) NOT NULL,
  `email`              VARCHAR(150) NULL,
  `phone`              VARCHAR(30)  NULL,
  `birth_date`         DATE         NULL,
  `address`            VARCHAR(200) NULL,
  `guardian_name`      VARCHAR(120) NULL,
  `guardian_phone`     VARCHAR(30)  NULL,
  `section_id`         INT UNSIGNED NULL,
  `enrollment_status`  ENUM('Enrolled','Not enrolled','Transferred','Graduated') NOT NULL DEFAULT 'Enrolled',
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_no` (`student_no`),
  UNIQUE KEY `uq_students_user` (`user_id`),
  KEY `idx_students_section` (`section_id`),
  KEY `idx_students_name` (`full_name`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Enrollment
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`            VARCHAR(20)  NULL,                      -- ENR-2026-001, set after insert
  `student_id`      INT UNSIGNED NULL,                      -- NULL until a new applicant is approved
  `applicant_name`  VARCHAR(120) NOT NULL,
  `grade`           TINYINT UNSIGNED NOT NULL,
  `section_id`      INT UNSIGNED NOT NULL,
  `school_year_id`  INT UNSIGNED NOT NULL,
  `type`            ENUM('New enrollment','Re-enrollment') NOT NULL,
  `docs_status`     ENUM('Awaiting verification','Verified') NOT NULL DEFAULT 'Awaiting verification',
  `status`          ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `email`           VARCHAR(150) NULL,
  `birth_date`      DATE NULL,
  `guardian_name`   VARCHAR(120) NULL,
  `guardian_phone`  VARCHAR(30)  NULL,
  `remarks`         VARCHAR(255) NULL,
  `created_by`      INT UNSIGNED NULL,
  `decided_by`      INT UNSIGNED NULL,
  `decided_at`      DATETIME NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enrollments_code` (`code`),
  KEY `idx_enr_student` (`student_id`),
  KEY `idx_enr_sy_status` (`school_year_id`, `status`),
  CONSTRAINT `fk_enr_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_enr_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `fk_enr_sy` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`),
  CONSTRAINT `chk_enr_grade` CHECK (`grade` BETWEEN 7 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historical headcount per school year and grade (feeds the re-enrollment forecast).
CREATE TABLE IF NOT EXISTS `enrollment_history` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_start`  SMALLINT UNSIGNED NOT NULL,                -- 2022 for SY 2022–2023
  `grade`       TINYINT UNSIGNED NOT NULL,
  `headcount`   SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hist_year_grade` (`year_start`, `grade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Academic records
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `grades` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`      INT UNSIGNED NOT NULL,
  `subject_id`      INT UNSIGNED NOT NULL,
  `school_year_id`  INT UNSIGNED NOT NULL,
  `q1`              DECIMAL(5,2) NULL,
  `q2`              DECIMAL(5,2) NULL,
  `q3`              DECIMAL(5,2) NULL,
  `q4`              DECIMAL(5,2) NULL,
  `final_grade`     DECIMAL(5,2) NULL,
  `remarks`         VARCHAR(40)  NULL,
  `encoded_by`      INT UNSIGNED NULL,
  `updated_at`      DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grades_student_subject_sy` (`student_id`, `subject_id`, `school_year_id`),
  KEY `idx_grades_sy` (`school_year_id`),
  CONSTRAINT `fk_grades_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grades_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `fk_grades_sy` FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Attendance (gate + classroom)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `gate_devices` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(80)  NOT NULL,
  `location`      VARCHAR(120) NULL,
  `token_hash`    CHAR(64)     NOT NULL,                   -- SHA-256 of the device API token
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `last_seen_at`  DATETIME NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_gate_devices_token` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gate_logs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`   INT UNSIGNED NOT NULL,
  `log_date`     DATE NOT NULL,
  `log_time`     TIME NOT NULL,
  `event`        ENUM('Time-in','Time-out') NOT NULL,
  `source`       ENUM('Scanner','Manual') NOT NULL DEFAULT 'Manual',
  `device_id`    INT UNSIGNED NULL,
  `recorded_by`  INT UNSIGNED NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gate_date_student` (`log_date`, `student_id`),
  KEY `idx_gate_student_date` (`student_id`, `log_date`),
  CONSTRAINT `fk_gate_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gate_device` FOREIGN KEY (`device_id`) REFERENCES `gate_devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `class_attendance` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`   INT UNSIGNED NOT NULL,
  `subject_id`   INT UNSIGNED NOT NULL,
  `section_id`   INT UNSIGNED NOT NULL,
  `attend_date`  DATE NOT NULL,
  `status`       ENUM('Present','Absent','Late','Excused') NOT NULL,
  `recorded_by`  INT UNSIGNED NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_class_att` (`student_id`, `subject_id`, `attend_date`),
  KEY `idx_class_att_date` (`attend_date`),
  KEY `idx_class_att_section_date` (`section_id`, `attend_date`),
  CONSTRAINT `fk_catt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_catt_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `fk_catt_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_corrections` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id`  BIGINT UNSIGNED NOT NULL,
  `old_status`     VARCHAR(12) NOT NULL,
  `new_status`     VARCHAR(12) NOT NULL,
  `reason`         VARCHAR(255) NOT NULL,
  `changed_by`     INT UNSIGNED NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_corr_att` (`attendance_id`),
  CONSTRAINT `fk_corr_att` FOREIGN KEY (`attendance_id`) REFERENCES `class_attendance` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Activities & submissions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activities` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(160) NOT NULL,
  `subject_id`    INT UNSIGNED NOT NULL,
  `teacher_id`    INT UNSIGNED NOT NULL,
  `section_id`    INT UNSIGNED NOT NULL,
  `deadline`      DATE NOT NULL,
  `instructions`  TEXT NOT NULL,
  `max_score`     SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_section_deadline` (`section_id`, `deadline`),
  KEY `idx_act_teacher` (`teacher_id`),
  CONSTRAINT `fk_act_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `fk_act_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`),
  CONSTRAINT `fk_act_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `submissions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id`   INT UNSIGNED NOT NULL,
  `student_id`    INT UNSIGNED NOT NULL,
  `status`        ENUM('Submitted','Graded') NOT NULL DEFAULT 'Submitted',
  `file_name`     VARCHAR(200) NOT NULL,                   -- original file name shown to users
  `file_path`     VARCHAR(255) NULL,                       -- stored name under runtime/uploads/submissions
  `submitted_at`  DATETIME NOT NULL,
  `score`         DECIMAL(6,2) NULL,
  `feedback`      TEXT NULL,
  `graded_by`     INT UNSIGNED NULL,
  `graded_at`     DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_activity_student` (`activity_id`, `student_id`),
  KEY `idx_sub_student` (`student_id`),
  CONSTRAINT `fk_sub_activity` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Student services
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_requests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(20)  NULL,                          -- REQ-2026-041, set after insert
  `student_id`  INT UNSIGNED NOT NULL,
  `type`        VARCHAR(80)  NOT NULL,
  `purpose`     VARCHAR(255) NOT NULL,
  `status`      ENUM('Pending','Processing','Ready','Completed') NOT NULL DEFAULT 'Pending',
  `created_by`  INT UNSIGNED NULL,
  `handled_by`  INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_requests_code` (`code`),
  KEY `idx_req_student_status` (`student_id`, `status`),
  KEY `idx_req_status` (`status`),
  CONSTRAINT `fk_req_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_status_log` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id`  INT UNSIGNED NOT NULL,
  `status`      VARCHAR(12)  NOT NULL,
  `changed_by`  INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rsl_request` (`request_id`),
  CONSTRAINT `fk_rsl_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Notifications
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notices` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(160) NOT NULL,
  `body`            TEXT NOT NULL,
  `category`        ENUM('Attendance','Academic','Announcement','Services','Guardian','Enrollment') NOT NULL,
  `aud_roles`       VARCHAR(120) NULL,                      -- comma separated roles; NULL = no role restriction
  `aud_student_id`  INT UNSIGNED NULL,                      -- limit to one student
  `aud_section_id`  INT UNSIGNED NULL,                      -- limit to one section
  `created_by`      INT UNSIGNED NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notices_created` (`created_at`),
  KEY `idx_notices_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notice_reads` (
  `notice_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `read_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notice_id`, `user_id`),
  CONSTRAINT `fk_nr_notice` FOREIGN KEY (`notice_id`) REFERENCES `notices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
--  Guidance
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `guidance_notes` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED NOT NULL,
  `author_id`   INT UNSIGNED NULL,
  `author_name` VARCHAR(120) NOT NULL,
  `kind`        ENUM('Note','Commendation','Parent consultation','Support case') NOT NULL DEFAULT 'Note',
  `note`        TEXT NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gnotes_student` (`student_id`, `created_at`),
  CONSTRAINT `fk_gnotes_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `interventions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED NOT NULL,
  `title`       VARCHAR(160) NOT NULL,
  `detail`      VARCHAR(255) NULL,
  `due_date`    DATE NOT NULL,
  `is_done`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`  INT UNSIGNED NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_interv_student` (`student_id`, `due_date`),
  CONSTRAINT `fk_interv_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
