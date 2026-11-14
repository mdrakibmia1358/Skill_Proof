-- Run ONCE in the existing SkillProof production database via phpMyAdmin.
-- Do NOT reimport schema.sql or delete existing users.
CREATE TABLE IF NOT EXISTS password_reset_codes (
    user_id INT NOT NULL PRIMARY KEY,
    code_digest CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    requests_in_window TINYINT UNSIGNED NOT NULL DEFAULT 1,
    window_started_at DATETIME NOT NULL,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
