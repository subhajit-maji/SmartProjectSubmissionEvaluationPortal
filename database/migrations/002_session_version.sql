-- Add per-user session revocation support to an existing portal database.
-- Back up first. Apply once after the current schema (including 001_mvp.sql
-- when upgrading from the earlier 19-table schema) if users.session_version
-- does not already exist. Fresh installers already include this column.

USE `spep_portal`;

SELECT COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'spep_portal'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'session_version';

ALTER TABLE `users`
  ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Increment to revoke existing sessions'
    AFTER `force_password_change`;
