-- ============================================================
-- SkillProof Migration: Add trust_score / career_score
-- Run this ONLY if you already have data and don't want to
-- re-run schema.sql (which drops and recreates every table).
-- ============================================================

USE skillproof_db;

ALTER TABLE skill_analyses
    ADD COLUMN trust_score  TINYINT UNSIGNED DEFAULT 0 AFTER readme_count,
    ADD COLUMN career_score TINYINT UNSIGNED DEFAULT 0 AFTER trust_score;

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