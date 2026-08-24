-- ============================================================
-- SkillProof Migration: Add public_slug column (FR-9)
-- Enables a unique, publicly shareable profile URL per developer
-- that recruiters can view WITHOUT logging in.
-- Safe to run even if you already ran the earlier migrations.
-- ============================================================

USE skillproof_db;

ALTER TABLE users
    ADD COLUMN public_slug VARCHAR(64) NULL UNIQUE AFTER bio;

-- Existing accounts get their public_slug generated automatically the
-- next time they visit their Profile page or Dashboard (see profile.php /
-- dashboard.php) - no manual backfill needed here.