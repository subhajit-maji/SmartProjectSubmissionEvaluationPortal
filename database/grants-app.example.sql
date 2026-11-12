-- Application account privilege template for the MVP.
-- Replace the password, run once as a database administrator, and keep the
-- account host aligned with config/database.php. This does not grant DDL.
CREATE USER IF NOT EXISTS 'spep_app'@'127.0.0.1' IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_STRONG_SECRET';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'spep_app'@'127.0.0.1';

GRANT SELECT, INSERT ON `spep_portal`.`departments` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`name`, `code`, `status`) ON `spep_portal`.`departments` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`batches` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`name`, `year`, `status`) ON `spep_portal`.`batches` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`users` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`password`, `force_password_change`, `status`, `session_version`) ON `spep_portal`.`users` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`projects` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`title`, `abstract`, `status`, `approved_at`, `completed_at`, `completed_by`, `completion_remarks`) ON `spep_portal`.`projects` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON `spep_portal`.`project_members` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`project_guides` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`role`, `status`, `assigned_at`) ON `spep_portal`.`project_guides` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`submission_stages` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`name`, `description`, `default_start_date`, `default_deadline`, `is_required`, `sort_order`, `status`) ON `spep_portal`.`submission_stages` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `spep_portal`.`batch_stage_deadlines` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `spep_portal`.`rubric_criteria` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`submissions` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`status`, `submitted_at`, `submitted_by`, `remarks`) ON `spep_portal`.`submissions` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`submission_files` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`evaluations` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`status`, `evaluated_at`) ON `spep_portal`.`evaluations` TO 'spep_app'@'127.0.0.1';
GRANT INSERT ON `spep_portal`.`evaluation_scores` TO 'spep_app'@'127.0.0.1';
GRANT INSERT ON `spep_portal`.`status_history` TO 'spep_app'@'127.0.0.1';
GRANT INSERT ON `spep_portal`.`audit_logs` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON `spep_portal`.`login_attempts` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `spep_portal`.`password_resets` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`feedback` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT ON `spep_portal`.`notifications` TO 'spep_app'@'127.0.0.1';
GRANT UPDATE (`is_read`) ON `spep_portal`.`notifications` TO 'spep_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `spep_portal`.`submission_appeals` TO 'spep_app'@'127.0.0.1';
