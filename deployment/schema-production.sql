-- SkillProof database tables for first-time hosted deployment.
-- Create/select your database in phpMyAdmin before importing this file.
-- Does not CREATE DATABASE, DROP TABLE, or load demonstration users.
-- For fresh databases only; use migrations for later schema changes.

CREATE TABLE IF NOT EXISTS users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    role            ENUM('Developer', 'Recruiter', 'Admin') NOT NULL DEFAULT 'Developer',
    github_username VARCHAR(100)  NULL,
    bio             TEXT          NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS skill_analyses (
    analysis_id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id                INT NOT NULL,
    github_username        VARCHAR(100) NOT NULL,
    total_repositories     INT DEFAULT 0,
    original_repositories  INT DEFAULT 0,
    forked_repositories    INT DEFAULT 0,
    total_stars            INT DEFAULT 0,
    total_forks            INT DEFAULT 0,
    languages_detected     INT DEFAULT 0,
    readme_count           INT DEFAULT 0,
    trust_score            TINYINT UNSIGNED DEFAULT 0,
    career_score            TINYINT UNSIGNED DEFAULT 0,
    analyzed_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS skill_scores (
    score_id     INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id  INT NOT NULL,
    skill_name   VARCHAR(80)  NOT NULL,
    score        TINYINT UNSIGNED NOT NULL,
    level        VARCHAR(40)  NOT NULL,
    evidence     VARCHAR(255) NOT NULL,
    status       ENUM('Verified', 'Needs Evidence', 'Low Evidence', 'Not Detected', 'Not Connected') NOT NULL,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS dimension_scores (
    dimension_id   INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id    INT NOT NULL,
    dimension_name VARCHAR(80)  NOT NULL,
    score          TINYINT UNSIGNED NOT NULL,
    evidence       VARCHAR(255) NOT NULL,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS recruiter_reports (
    report_id     INT AUTO_INCREMENT PRIMARY KEY,
    recruiter_id  INT NOT NULL,
    developer_id  INT NOT NULL,
    analysis_id   INT NULL,
    generated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recruiter_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (developer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE SET NULL
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS learning_gaps (
    gap_id       INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id  INT NOT NULL,
    title        VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NOT NULL,
    priority     ENUM('High Priority', 'Medium Priority', 'Low Priority') NOT NULL,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE CASCADE
) ENGINE = InnoDB;

