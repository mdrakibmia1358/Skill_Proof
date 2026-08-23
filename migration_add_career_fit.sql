-- ============================================================
-- SkillProof Migration: Add career_fit_scores table (FR-6)
-- Safe to run even if you already ran migration_add_scores.sql -
-- this only adds the new table, nothing else.
-- ============================================================

USE skillproof_db;

CREATE TABLE IF NOT EXISTS career_fit_scores (
    fit_id       INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id  INT NOT NULL,
    role_name    VARCHAR(80) NOT NULL,
    match_score  TINYINT UNSIGNED NOT NULL,
    FOREIGN KEY (analysis_id) REFERENCES skill_analyses(analysis_id) ON DELETE CASCADE
) ENGINE = InnoDB;