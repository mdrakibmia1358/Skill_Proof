-- ============================================================
-- SkillProof Migration: Admin Panel support
-- Adds 'status' (Active/Blocked) to users so Admins can block
-- or unblock accounts. Safe to run alongside earlier migrations.
--
-- NOTE: This does NOT create an Admin account. Use
-- auth_system/setup_admin.php (one-time, self-disabling) to
-- create your first Admin login after running this migration.
-- ============================================================

USE skillproof_db;

ALTER TABLE users
    ADD COLUMN status ENUM('Active', 'Blocked') NOT NULL DEFAULT 'Active' AFTER role;