-- Upgrade an existing installation of the prior 19-table schema to the MVP.
-- Back up first. Run once with spep_admin after checking the preflight queries.
-- Fresh installations should use database/install.sql instead.

USE `spep_portal`;

-- These queries should return no rows before adding the stricter checks.
SELECT id FROM submission_stages
WHERE NOT ((default_start_date IS NULL AND default_deadline IS NULL) OR
           (default_start_date IS NOT NULL AND default_deadline IS NOT NULL AND default_start_date < default_deadline));
SELECT id FROM batch_stage_deadlines
WHERE NOT ((start_date IS NULL AND deadline IS NULL) OR
           (start_date IS NOT NULL AND deadline IS NOT NULL AND start_date < deadline));
SELECT id FROM evaluation_scores WHERE marks_obtained > max_marks_snapshot;
SELECT id FROM evaluations WHERE total_obtained < 0 OR total_max < 0 OR percentage < 0 OR percentage > 100;
SELECT name, year, COUNT(*) AS duplicates FROM batches GROUP BY name, year HAVING COUNT(*) > 1;

ALTER TABLE `batches` ADD UNIQUE KEY `uk_batches_name_year` (`name`, `year`);

ALTER TABLE `submission_stages`
  DROP CHECK `chk_stages_dates`,
  ADD CONSTRAINT `chk_stages_dates`
    CHECK ((`default_start_date` IS NULL AND `default_deadline` IS NULL) OR
           (`default_start_date` IS NOT NULL AND `default_deadline` IS NOT NULL AND
            `default_start_date` < `default_deadline`));

ALTER TABLE `batch_stage_deadlines`
  DROP CHECK `chk_bsd_dates`,
  ADD CONSTRAINT `chk_bsd_dates`
    CHECK ((`start_date` IS NULL AND `deadline` IS NULL) OR
           (`start_date` IS NOT NULL AND `deadline` IS NOT NULL AND
            `start_date` < `deadline`));

ALTER TABLE `rubric_criteria` ALTER COLUMN `weight` DROP DEFAULT;

ALTER TABLE `evaluation_scores`
  DROP CHECK `chk_es_marks_positive`,
  ADD CONSTRAINT `chk_es_marks_positive`
    CHECK (`marks_obtained` >= 0 AND `marks_obtained` <= `max_marks_snapshot`);

ALTER TABLE `evaluations`
  ADD CONSTRAINT `chk_eval_totals`
    CHECK (`total_obtained` >= 0 AND `total_max` >= 0 AND
           `percentage` >= 0 AND `percentage` <= 100);

ALTER TABLE `status_history` DROP FOREIGN KEY `fk_sh_user`;
ALTER TABLE `status_history`
  ADD CONSTRAINT `fk_sh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

ALTER TABLE `audit_logs` DROP FOREIGN KEY `fk_al_user`;
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_hash`   CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ip_address`   VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_email_time` (`email_hash`, `attempted_at`),
  INDEX `idx_login_ip_time` (`ip_address`, `attempted_at`),
  INDEX `idx_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `rubric_criteria` (`id`, `stage_id`, `name`, `description`, `max_marks`, `weight`, `sort_order`) VALUES
  (9,  2, 'Requirements Coverage', 'Completeness of functional and non-functional requirements', 10.00, 30.00, 1),
  (10, 2, 'Clarity and Testability', 'Requirements are clear, consistent, and verifiable',       10.00, 25.00, 2),
  (11, 2, 'Traceability', 'Requirements map to project objectives and stakeholders',             10.00, 25.00, 3),
  (12, 2, 'Structure and Quality', 'Document organization, terminology, and presentation',        10.00, 20.00, 4),
  (13, 3, 'Architecture', 'Appropriateness and clarity of the proposed architecture',             10.00, 30.00, 1),
  (14, 3, 'Data Design', 'Quality of data models, schemas, and relationships',                   10.00, 25.00, 2),
  (15, 3, 'Interface Design', 'Quality of interface flows, diagrams, and interactions',          10.00, 25.00, 3),
  (16, 3, 'Security and Feasibility', 'Security, constraints, and implementation feasibility',   10.00, 20.00, 4),
  (17, 4, 'Feature Completion', 'Required functionality is implemented and working',             10.00, 30.00, 1),
  (18, 4, 'Code Quality', 'Structure, readability, and maintainability of the implementation',   10.00, 25.00, 2),
  (19, 4, 'Testing', 'Appropriate tests and evidence of reliable behavior',                       10.00, 25.00, 3),
  (20, 4, 'Setup and Documentation', 'Installation instructions and technical documentation',    10.00, 20.00, 4),
  (21, 6, 'Conceptual Understanding', 'Understanding of the problem and proposed solution',       10.00, 30.00, 1),
  (22, 6, 'Demonstration', 'Clarity and completeness of the live demonstration',                  10.00, 30.00, 2),
  (23, 6, 'Question Response', 'Accuracy and depth of responses during the viva',                10.00, 25.00, 3),
  (24, 6, 'Team Contribution', 'Evidence of balanced contribution and collaboration',             10.00, 15.00, 4);

DROP TRIGGER IF EXISTS `trg_eval_scores_prevent_finalized_update`;
DROP TRIGGER IF EXISTS `trg_appeals_prevent_special_revision_appeal`;
DROP TRIGGER IF EXISTS `trg_appeals_prevent_special_revision_insert`;
DROP TRIGGER IF EXISTS `trg_appeals_prevent_special_revision_update`;
DELIMITER $$
CREATE TRIGGER `trg_eval_scores_prevent_finalized_update`
BEFORE UPDATE ON `evaluation_scores`
FOR EACH ROW
BEGIN
    DECLARE old_eval_status VARCHAR(20);
    DECLARE new_eval_status VARCHAR(20);
    SELECT status INTO old_eval_status FROM `evaluations` WHERE id = OLD.evaluation_id;
    SELECT status INTO new_eval_status FROM `evaluations` WHERE id = NEW.evaluation_id;
    IF old_eval_status = 'finalized' OR new_eval_status = 'finalized' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Scores belonging to a finalized evaluation are immutable.';
    END IF;
END$$
CREATE TRIGGER `trg_appeals_prevent_special_revision_insert`
BEFORE INSERT ON `submission_appeals`
FOR EACH ROW
BEGIN
    DECLARE is_special TINYINT;
    SELECT is_special_revision INTO is_special FROM `submissions` WHERE id = NEW.submission_id;
    IF is_special = 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Special revision submissions are final and cannot be appealed.';
    END IF;
END$$
CREATE TRIGGER `trg_appeals_prevent_special_revision_update`
BEFORE UPDATE ON `submission_appeals`
FOR EACH ROW
BEGIN
    DECLARE is_special TINYINT;
    SELECT is_special_revision INTO is_special FROM `submissions` WHERE id = NEW.submission_id;
    IF is_special = 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Special revision submissions are final and cannot be appealed.';
    END IF;
END$$
DELIMITER ;

-- If the prior installer created admin@spep.local, replace its password or
-- remove that development-only account before exposing the portal.
