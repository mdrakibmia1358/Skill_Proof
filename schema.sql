-- ============================================================
-- SkillProof Database Schema
-- Week 5 Lab Task - Database Design, SQL (MySQL) & Midterm Evaluation
-- Normalized to Third Normal Form (3NF)
-- ============================================================

CREATE DATABASE IF NOT EXISTS skillproof_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE skillproof_db;

-- Drop tables first (child -> parent order) so this script can be
-- safely re-run any number of times during development/demo.
DROP TABLE IF EXISTS learning_gaps;
DROP TABLE IF EXISTS dimension_scores;
DROP TABLE IF EXISTS skill_scores;
DROP TABLE IF EXISTS skill_analyses;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100)  NOT NULL,
    email           VARCHAR(150)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255)  NOT NULL,
    role            ENUM('Developer', 'Recruiter', 'Admin') NOT NULL DEFAULT 'Developer',
    github_username VARCHAR(100)  NULL,
    bio             TEXT          NULL,
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE skill_analyses (
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
    analyzed_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE skill_scores (
    score_id     INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id  INT NOT NULL,
    skill_name   VARCHAR(80)  NOT NULL,
    score        TINYINT UNSIGNED NOT NULL,
    level        VARCHAR(40)  NOT NULL,
    evidence     VARCHAR(255) NOT NULL,
    status       ENUM('Verified', 'Needs Evidence', 'Low Evidence', 'Not Detected', 'Not Connected') NOT NULL,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE dimension_scores (
    dimension_id   INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id    INT NOT NULL,
    dimension_name VARCHAR(80)  NOT NULL,
    score          TINYINT UNSIGNED NOT NULL,
    evidence       VARCHAR(255) NOT NULL,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE learning_gaps (
    gap_id       INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id  INT NOT NULL,
    title        VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NOT NULL,
    priority     ENUM('High Priority', 'Medium Priority', 'Low Priority') NOT NULL,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- ============================================================
-- Seed Data
-- ============================================================

-- Password for both seed accounts is: secureStudent123
INSERT INTO users (user_id, full_name, email, password_hash, role, github_username) VALUES
(1, 'Md. Rakib Mia',   'student@university.edu', '$2y$10$0MWeaGLlEaSD0uYMUC0UduQiXUEv3BePZwKKfA.JHgcvTfMKl0KAq', 'Developer', 'torvalds'),
(2, 'Nondini Ghosh',   'nondini@university.edu', '$2y$10$0MWeaGLlEaSD0uYMUC0UduQiXUEv3BePZwKKfA.JHgcvTfMKl0KAq', 'Developer', NULL);

INSERT INTO skill_analyses
    (analysis_id, user_id, github_username, total_repositories, original_repositories, forked_repositories, total_stars, total_forks, languages_detected, readme_count)
VALUES
    (1, 1, 'torvalds', 8, 6, 2, 3200, 1400, 4, 5);

INSERT INTO skill_scores (analysis_id, skill_name, score, level, evidence, status) VALUES
(1, 'JavaScript', 72, 'Intermediate', 'Detected in 4 repositories', 'Verified'),
(1, 'SQL',        40, 'Beginner',     'Detected in 1 repository',   'Low Evidence'),
(1, 'DevOps',      0, 'Not Detected', 'No CI/CD configuration found', 'Not Detected');

INSERT INTO dimension_scores (analysis_id, dimension_name, score, evidence) VALUES
(1, 'Documentation',      65, 'README present in 5 of 8 repositories'),
(1, 'Testing Readiness',  30, 'Test folders found in 2 repositories'),
(1, 'Security Awareness', 55, '.gitignore present in most repositories');

INSERT INTO learning_gaps (analysis_id, title, description, priority) VALUES
(1, 'Add Testing Evidence',  'Add test folders, test files, or basic testing documentation to improve testing score.', 'Medium Priority'),
(1, 'Add DevOps Evidence',   'Add GitHub Actions, Dockerfile, or deployment configuration to improve DevOps score.', 'Low Priority');