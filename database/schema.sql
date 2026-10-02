SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','teacher') NOT NULL DEFAULT 'teacher',
    active TINYINT(1) NOT NULL DEFAULT 1,
    auth_version INT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subjects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO subjects (name) VALUES
('Arte'),('Ciências'),('Educação Física'),('Ensino Religioso'),('Geografia'),('História'),('Língua Inglesa'),('Língua Portuguesa'),('Matemática');

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL,
    enrollment VARCHAR(40) NOT NULL UNIQUE,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_students_name (name),
    INDEX idx_students_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    grade_level VARCHAR(50) DEFAULT NULL,
    school_year SMALLINT UNSIGNED NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_class_year (name, school_year),
    INDEX idx_classes_year (school_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_students (
    class_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, student_id),
    CONSTRAINT fk_class_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_students_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_teachers (
    class_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (class_id, teacher_id, subject_id),
    CONSTRAINT fk_class_teachers_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_teachers_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_teachers_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    INDEX idx_class_teachers_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    subject_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    questions_count TINYINT UNSIGNED NOT NULL,
    total_points DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    version VARCHAR(10) NOT NULL DEFAULT 'A',
    status ENUM('draft','published') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_exams_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_exams_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT,
    CONSTRAINT fk_exams_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,
    INDEX idx_exams_teacher (teacher_id),
    INDEX idx_exams_class (class_id),
    INDEX idx_exams_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    question_number TINYINT UNSIGNED NOT NULL,
    correct_option ENUM('A','B','C','D','E') NOT NULL,
    points DECIMAL(7,4) NOT NULL,
    CONSTRAINT fk_exam_questions_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    UNIQUE KEY uq_exam_question (exam_id, question_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exam_student_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    token CHAR(48) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_tokens_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY uq_exam_student_token (exam_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT UNSIGNED NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    correct_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    incorrect_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    blank_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    score DECIMAL(7,2) NOT NULL DEFAULT 0,
    scanner_confidence DECIMAL(5,2) DEFAULT NULL,
    corrected_by INT UNSIGNED NOT NULL,
    scanned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_results_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_user FOREIGN KEY (corrected_by) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_exam_student_result (exam_id, student_id),
    INDEX idx_results_exam (exam_id),
    INDEX idx_results_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS result_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    result_id BIGINT UNSIGNED NOT NULL,
    question_number TINYINT UNSIGNED NOT NULL,
    selected_option ENUM('A','B','C','D','E') DEFAULT NULL,
    correct_option ENUM('A','B','C','D','E') NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    confidence DECIMAL(5,2) DEFAULT NULL,
    CONSTRAINT fk_result_answers_result FOREIGN KEY (result_id) REFERENCES results(id) ON DELETE CASCADE,
    UNIQUE KEY uq_result_question (result_id, question_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_rate_limits (
    key_hash CHAR(64) PRIMARY KEY,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    last_attempt_at DATETIME NOT NULL,
    locked_until DATETIME DEFAULT NULL,
    INDEX idx_login_rate_last (last_attempt_at),
    INDEX idx_login_rate_lock (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) DEFAULT NULL,
    entity_id VARCHAR(80) DEFAULT NULL,
    description VARCHAR(500) DEFAULT NULL,
    metadata LONGTEXT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

