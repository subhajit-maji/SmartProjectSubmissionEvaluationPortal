-- InfinityFree free-plan schema copy for Smart Project Submission & Evaluation Portal.
-- MariaDB-compatible collation. Database triggers are omitted because the free
-- plan does not permit trigger creation; workflow checks remain in PHP.
-- Select the provider-created database in phpMyAdmin before importing this file.
-- Seeds departments, batches, six stages, rubrics, and batch deadlines; no admin.
-- For Oracle MySQL 8.4, use database/install.sql instead.

SET NAMES utf8mb4;
SET time_zone = '+00:00';


-- ============================================================
-- 1. DEPARTMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `departments` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `code`       VARCHAR(20)  NOT NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_departments_name` (`name`),
  UNIQUE KEY `uk_departments_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. BATCHES
-- ============================================================
CREATE TABLE IF NOT EXISTS `batches` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `year`       YEAR          NOT NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_batches_name_year` (`name`, `year`),
  INDEX `idx_batches_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. USERS (Admin, Faculty, Student)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`                  VARCHAR(100) NOT NULL,
  `email`                 VARCHAR(150) NOT NULL,
  `password`              VARCHAR(255) NOT NULL,
  `role`                  ENUM('admin','faculty','student') NOT NULL,
  `department_id`         INT UNSIGNED NOT NULL,
  `batch_id`              INT UNSIGNED DEFAULT NULL COMMENT 'NULL for faculty and admin; required for students',
  `registration_no`       VARCHAR(50)  DEFAULT NULL COMMENT 'Student roll no / faculty employee ID',
  `phone`                 VARCHAR(20)  DEFAULT NULL,
  `status`                ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `force_password_change` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = must change password on next login',
  `session_version`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Increment to revoke existing sessions',
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_users_reg_no` (`registration_no`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_dept` (`department_id`),
  INDEX `idx_users_batch` (`batch_id`),
  INDEX `idx_users_status` (`status`),
  CONSTRAINT `fk_users_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_users_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. PROJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `projects` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`              VARCHAR(200) NOT NULL,
  `abstract`           TEXT         NOT NULL,
  `department_id`      INT UNSIGNED NOT NULL,
  `batch_id`           INT UNSIGNED NOT NULL,
  `status`             ENUM('draft','pending_approval','approved','rejected','in_progress','completed')
                         NOT NULL DEFAULT 'draft',
  `created_by`         INT UNSIGNED NOT NULL COMMENT 'student user_id who created proposal (initial leader)',
  `approved_at`        DATETIME     DEFAULT NULL,
  `completed_at`       DATETIME     DEFAULT NULL,
  `completed_by`       INT UNSIGNED DEFAULT NULL COMMENT 'Faculty primary guide or Admin who performed sign-off',
  `completion_remarks` TEXT         DEFAULT NULL,
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_projects_status` (`status`),
  INDEX `idx_projects_dept` (`department_id`),
  INDEX `idx_projects_batch` (`batch_id`),
  INDEX `idx_projects_creator` (`created_by`),
  INDEX `idx_projects_completed_by` (`completed_by`),
  CONSTRAINT `fk_projects_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_projects_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_projects_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_projects_completed_by`
    FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. PROJECT MEMBERS (junction — students in a team)
-- ============================================================
CREATE TABLE IF NOT EXISTS `project_members` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id`  INT UNSIGNED NOT NULL,
  `student_id`  INT UNSIGNED NOT NULL,
  `role`        ENUM('leader','member') NOT NULL DEFAULT 'member',
  `joined_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_project_student` (`project_id`, `student_id`),
  INDEX `idx_pm_student` (`student_id`),
  CONSTRAINT `fk_pm_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pm_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. PROJECT GUIDES (junction — faculty assigned to project)
-- ============================================================
CREATE TABLE IF NOT EXISTS `project_guides` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id`  INT UNSIGNED NOT NULL,
  `faculty_id`  INT UNSIGNED NOT NULL,
  `role`        ENUM('primary_guide','co_guide') NOT NULL DEFAULT 'primary_guide'
                  COMMENT 'exactly one active primary_guide per project acts as lead evaluator',
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_project_faculty` (`project_id`, `faculty_id`),
  INDEX `idx_pg_faculty` (`faculty_id`),
  INDEX `idx_pg_role_status` (`project_id`, `role`, `status`),
  CONSTRAINT `fk_pg_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pg_faculty`
    FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. SUBMISSION STAGES (with complete start date, deadline, is_required)
-- ============================================================
CREATE TABLE IF NOT EXISTS `submission_stages` (
  `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`               VARCHAR(100) NOT NULL,
  `description`        TEXT         DEFAULT NULL,
  `default_start_date` DATETIME     DEFAULT NULL COMMENT 'UTC; stage open start window fallback',
  `default_deadline`   DATETIME     DEFAULT NULL COMMENT 'UTC; stage submission deadline fallback',
  `is_required`        TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = mandatory for project completion; 0 = optional',
  `sort_order`         INT UNSIGNED NOT NULL DEFAULT 0,
  `status`             ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_stages_status` (`status`),
  INDEX `idx_stages_order` (`sort_order`),
  CONSTRAINT `chk_stages_dates`
    CHECK ((`default_start_date` IS NULL AND `default_deadline` IS NULL) OR
           (`default_start_date` IS NOT NULL AND `default_deadline` IS NOT NULL AND
            `default_start_date` < `default_deadline`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. BATCH STAGE DEADLINES (per-batch stage overrides)
-- ============================================================
CREATE TABLE IF NOT EXISTS `batch_stage_deadlines` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `batch_id`    INT UNSIGNED NOT NULL,
  `stage_id`    INT UNSIGNED NOT NULL,
  `start_date`  DATETIME DEFAULT NULL COMMENT 'UTC; overrides submission_stages.default_start_date',
  `deadline`    DATETIME DEFAULT NULL COMMENT 'UTC; overrides submission_stages.default_deadline',
  `is_required` TINYINT UNSIGNED DEFAULT NULL COMMENT 'NULL = inherit from stage; 0/1 = override',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_bsd_batch_stage` (`batch_id`, `stage_id`),
  INDEX `idx_bsd_batch` (`batch_id`),
  INDEX `idx_bsd_stage` (`stage_id`),
  CONSTRAINT `fk_bsd_batch`
    FOREIGN KEY (`batch_id`) REFERENCES `batches`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bsd_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `submission_stages`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_bsd_dates`
    CHECK ((`start_date` IS NULL AND `deadline` IS NULL) OR
           (`start_date` IS NOT NULL AND `deadline` IS NOT NULL AND
            `start_date` < `deadline`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. RUBRIC CRITERIA
-- ============================================================
CREATE TABLE IF NOT EXISTS `rubric_criteria` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `stage_id`    INT UNSIGNED NOT NULL,
  `name`        VARCHAR(150) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `max_marks`   DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `weight`      DECIMAL(5,2) NOT NULL COMMENT 'percentage weight; sum per stage must be 100.00',
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rc_stage` (`stage_id`),
  INDEX `idx_rc_status` (`status`),
  CONSTRAINT `fk_rc_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `submission_stages`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_rc_marks`
    CHECK (`max_marks` > 0),
  CONSTRAINT `chk_rc_weight`
    CHECK (`weight` > 0 AND `weight` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. SUBMISSIONS (with versioning, special revision flag, is_late)
-- ============================================================
CREATE TABLE IF NOT EXISTS `submissions` (
  `id`                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id`          INT UNSIGNED NOT NULL,
  `stage_id`            INT UNSIGNED NOT NULL,
  `version`             INT UNSIGNED NOT NULL DEFAULT 1,
  `is_special_revision` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = granted via approved appeal (can be v4)',
  `submitted_by`        INT UNSIGNED NOT NULL COMMENT 'student user_id (must be team leader)',
  `status`              ENUM('draft','submitted','under_review','revision_required','resubmitted','approved','rejected')
                          NOT NULL DEFAULT 'draft',
  `submitted_at`        DATETIME     DEFAULT NULL,
  `is_late`             TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `remarks`             TEXT         DEFAULT NULL COMMENT 'student submission notes',
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_submissions_version` (`project_id`, `stage_id`, `version`),
  INDEX `idx_sub_project` (`project_id`),
  INDEX `idx_sub_stage` (`stage_id`),
  INDEX `idx_sub_status` (`status`),
  INDEX `idx_sub_submitter` (`submitted_by`),
  CONSTRAINT `fk_sub_project`
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sub_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `submission_stages`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_sub_user`
    FOREIGN KEY (`submitted_by`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. SUBMISSION FILES
-- ============================================================
CREATE TABLE IF NOT EXISTS `submission_files` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `submission_id`  INT UNSIGNED NOT NULL,
  `original_name`  VARCHAR(255) NOT NULL,
  `stored_name`    VARCHAR(255) NOT NULL,
  `file_path`      VARCHAR(500) NOT NULL,
  `mime_type`      VARCHAR(100) NOT NULL,
  `file_size`      INT UNSIGNED NOT NULL COMMENT 'bytes',
  `uploaded_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sf_submission` (`submission_id`),
  CONSTRAINT `fk_sf_submission`
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. EVALUATIONS (with draft/finalized state and trigger lock)
-- ============================================================
CREATE TABLE IF NOT EXISTS `evaluations` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `submission_id`     INT UNSIGNED NOT NULL,
  `faculty_id`        INT UNSIGNED NOT NULL COMMENT 'evaluator ID (Primary Guide or authorized Admin)',
  `status`            ENUM('draft','finalized') NOT NULL DEFAULT 'draft',
  `is_admin_override` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total_obtained`    DECIMAL(7,2) NOT NULL DEFAULT 0.00 COMMENT 'sum of raw criterion marks',
  `total_max`         DECIMAL(7,2) NOT NULL DEFAULT 0.00 COMMENT 'sum of criterion maximum marks',
  `percentage`        DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'weighted percentage 0.00-100.00',
  `grade`             VARCHAR(5)   DEFAULT NULL COMMENT 'A+, A, B+, B, C, F',
  `comments`          TEXT         DEFAULT NULL COMMENT 'overall evaluation narrative',
  `evaluated_at`      DATETIME     DEFAULT NULL COMMENT 'timestamp when evaluation was finalized',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_eval_submission` (`submission_id`) COMMENT 'strictly one lead evaluation per submission version',
  INDEX `idx_eval_faculty` (`faculty_id`),
  INDEX `idx_eval_status` (`status`),
  CONSTRAINT `fk_eval_submission`
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_eval_faculty`
    FOREIGN KEY (`faculty_id`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_eval_totals`
    CHECK (`total_obtained` >= 0 AND `total_max` >= 0 AND
           `percentage` >= 0 AND `percentage` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. EVALUATION SCORES (per-criterion snapshots, draft editable, finalized immutable)
-- ============================================================
CREATE TABLE IF NOT EXISTS `evaluation_scores` (
  `id`                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `evaluation_id`          INT UNSIGNED NOT NULL,
  `criteria_id`            INT UNSIGNED NOT NULL,
  `marks_obtained`         DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `criteria_name_snapshot` VARCHAR(150) NOT NULL COMMENT 'frozen criterion name at evaluation time',
  `max_marks_snapshot`     DECIMAL(5,2) NOT NULL COMMENT 'frozen copy of criterion max_marks at evaluation time',
  `weight_snapshot`        DECIMAL(5,2) NOT NULL COMMENT 'frozen copy of criterion weight at evaluation time',
  `comments`               TEXT         DEFAULT NULL COMMENT 'per-criterion feedback',
  UNIQUE KEY `uk_es_eval_criteria` (`evaluation_id`, `criteria_id`),
  INDEX `idx_es_criteria` (`criteria_id`),
  CONSTRAINT `fk_es_evaluation`
    FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_es_criteria`
    FOREIGN KEY (`criteria_id`) REFERENCES `rubric_criteria`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_es_marks_positive`
    CHECK (`marks_obtained` >= 0 AND `marks_obtained` <= `max_marks_snapshot`),
  CONSTRAINT `chk_es_max_marks_positive`
    CHECK (`max_marks_snapshot` > 0),
  CONSTRAINT `chk_es_weight_positive`
    CHECK (`weight_snapshot` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. SUBMISSION APPEALS (Academic Appeal & Special Revision)
-- ============================================================
CREATE TABLE IF NOT EXISTS `submission_appeals` (
  `id`                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `submission_id`           INT UNSIGNED NOT NULL,
  `requested_by`            INT UNSIGNED NOT NULL COMMENT 'student leader who filed the appeal',
  `appeal_reason`           TEXT         NOT NULL,
  `status`                  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`             INT UNSIGNED DEFAULT NULL COMMENT 'admin who reviewed the appeal',
  `decision_reason`         TEXT         DEFAULT NULL,
  `resulting_submission_id` INT UNSIGNED DEFAULT NULL COMMENT 'version created upon approved special revision',
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`             DATETIME     DEFAULT NULL,
  UNIQUE KEY `uk_appeal_submission` (`submission_id`) COMMENT 'strictly one appeal per rejected submission',
  UNIQUE KEY `uk_appeal_result` (`resulting_submission_id`) COMMENT 'strictly one special revision per appeal',
  INDEX `idx_appeals_status` (`status`),
  CONSTRAINT `fk_appeals_submission`
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_appeals_requester`
    FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_appeals_reviewer`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appeals_resulting_sub`
    FOREIGN KEY (`resulting_submission_id`) REFERENCES `submissions`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. FEEDBACK (threaded comments on submissions)
-- ============================================================
CREATE TABLE IF NOT EXISTS `feedback` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `submission_id`  INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `message`        TEXT         NOT NULL,
  `parent_id`      INT UNSIGNED DEFAULT NULL COMMENT 'NULL = top-level; set = reply',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_fb_submission` (`submission_id`),
  INDEX `idx_fb_parent` (`parent_id`),
  INDEX `idx_fb_user` (`user_id`),
  CONSTRAINT `fk_fb_submission`
    FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fb_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fb_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `feedback`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT         NOT NULL,
  `link`       VARCHAR(500) DEFAULT NULL COMMENT 'URL to navigate when clicked',
  `type`       ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user_read` (`user_id`, `is_read`),
  INDEX `idx_notif_created` (`created_at`),
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. STATUS HISTORY (polymorphic audit trail with system actor support)
-- ============================================================
CREATE TABLE IF NOT EXISTS `status_history` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `entity_type` ENUM('project','submission','user','department','batch','submission_stage','project_guide','evaluation','submission_appeal') NOT NULL,
  `entity_id`   INT UNSIGNED NOT NULL,
  `old_status`  VARCHAR(30)  DEFAULT NULL COMMENT 'NULL on initial creation',
  `new_status`  VARCHAR(30)  NOT NULL,
  `changed_by`  INT UNSIGNED DEFAULT NULL COMMENT 'NULL = system-generated automation',
  `is_system`   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = automated event, 0 = human initiated',
  `remarks`     TEXT         DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sh_entity` (`entity_type`, `entity_id`),
  INDEX `idx_sh_changed_by` (`changed_by`),
  INDEX `idx_sh_created` (`created_at`),
  CONSTRAINT `fk_sh_user`
    FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`),
  CONSTRAINT `chk_sh_system_actor`
    CHECK ((`changed_by` IS NOT NULL AND `is_system` = 0) OR
           (`changed_by` IS NULL AND `is_system` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. AUDIT LOGS (immutable administrative mutation log)
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED DEFAULT NULL COMMENT 'NULL = system-generated automation',
  `is_system`   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 = automated action, 0 = human initiated',
  `action`      VARCHAR(100) NOT NULL COMMENT 'e.g. user.create, project.override_member',
  `entity_type` VARCHAR(50)  NOT NULL COMMENT 'e.g. user, project, department',
  `entity_id`   INT UNSIGNED DEFAULT NULL,
  `old_values`  TEXT         DEFAULT NULL COMMENT 'JSON snapshot of previous state',
  `new_values`  TEXT         DEFAULT NULL COMMENT 'JSON snapshot of new state',
  `ip_address`  VARCHAR(45)  NOT NULL COMMENT 'supports IPv6, 127.0.0.1 for CLI',
  `user_agent`  VARCHAR(500) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_al_user` (`user_id`),
  INDEX `idx_al_action` (`action`),
  INDEX `idx_al_entity` (`entity_type`, `entity_id`),
  INDEX `idx_al_created` (`created_at`),
  CONSTRAINT `fk_al_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
  CONSTRAINT `chk_al_system_actor`
    CHECK ((`user_id` IS NOT NULL AND `is_system` = 0) OR
           (`user_id` IS NULL AND `is_system` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. PASSWORD RESETS (with UNIQUE token_hash constraint)
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64)  NOT NULL COMMENT 'SHA-256 hash of raw token sent via email',
  `expires_at` DATETIME     NOT NULL,
  `used`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_pr_token` (`token_hash`),
  INDEX `idx_pr_user` (`user_id`),
  INDEX `idx_pr_expires` (`expires_at`),
  INDEX `idx_pr_used` (`used`),
  CONSTRAINT `fk_pr_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. LOGIN ATTEMPTS (database-backed login rate limiting)
-- ============================================================
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email_hash`   CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ip_address`   VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_login_email_time` (`email_hash`, `attempted_at`),
  INDEX `idx_login_ip_time` (`ip_address`, `attempted_at`),
  INDEX `idx_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA (INSERT IGNORE — safe to re-run)
-- ============================================================
-- ⚠️ IMPORTANT NOTICE FOR PRODUCTION DEPLOYMENTS:
-- A development administrator is created only by database/seed-dev.sql.
--
-- FOR PRODUCTION INSTALLATION:
-- 1. Create a production administrator with a unique password hash, for example:
--    php scripts/generate-hash.php
-- 2. Insert the production administrator account using that generated hash:
--    INSERT INTO users (name, email, password, role, department_id, status, force_password_change)
--    VALUES ('System Administrator', 'admin@yourinstitution.edu', '<GENERATED_HASH>', 'admin', 1, 'active', 1);
-- 3. The force_password_change = 1 flag ensures the admin must update their password
--    upon their very first login.
-- ============================================================

INSERT IGNORE INTO `departments` (`id`, `name`, `code`) VALUES
  (1, 'Computer Science', 'CS'),
  (2, 'Information Technology', 'IT'),
  (3, 'Electronics & Communication', 'ECE'),
  (4, 'Mechanical Engineering', 'ME');

INSERT IGNORE INTO `batches` (`id`, `name`, `year`) VALUES
  (1, '2024-25', 2024),
  (2, '2025-26', 2025),
  (3, '2026-27', 2026);

INSERT IGNORE INTO `submission_stages` (`id`, `name`, `description`, `default_start_date`, `default_deadline`, `is_required`, `sort_order`) VALUES
  (1, 'Synopsis',        'Project proposal and abstract',                    '2026-11-01 00:00:00', '2026-12-15 23:59:59', 1, 1),
  (2, 'SRS Document',    'Software Requirements Specification',              '2026-12-16 00:00:00', '2027-01-15 23:59:59', 1, 2),
  (3, 'Design Document', 'System design, ER diagrams, architecture',        '2027-01-16 00:00:00', '2027-02-15 23:59:59', 1, 3),
  (4, 'Implementation',  'Working code with documentation',                 '2027-02-16 00:00:00', '2027-03-15 23:59:59', 1, 4),
  (5, 'Final Report',    'Complete project report with all deliverables',    '2027-03-16 00:00:00', '2027-04-01 23:59:59', 1, 5),
  (6, 'Viva Voce',       'Oral examination and project demonstration',      '2027-04-02 00:00:00', '2027-04-15 23:59:59', 1, 6);

INSERT IGNORE INTO `rubric_criteria` (`id`, `stage_id`, `name`, `description`, `max_marks`, `weight`, `sort_order`) VALUES
  (1, 1, 'Problem Statement',  'Clarity and relevance of the problem identified',     10.00, 30.00, 1),
  (2, 1, 'Objectives',         'Well-defined, measurable, and achievable objectives', 10.00, 25.00, 2),
  (3, 1, 'Literature Survey',  'Depth and breadth of background research',           10.00, 25.00, 3),
  (4, 1, 'Feasibility',        'Technical, economic, and time feasibility analysis',  10.00, 20.00, 4);

INSERT IGNORE INTO `rubric_criteria` (`id`, `stage_id`, `name`, `description`, `max_marks`, `weight`, `sort_order`) VALUES
  (5, 5, 'Documentation',    'Report quality, formatting, and completeness',       10.00, 25.00, 1),
  (6, 5, 'Code Quality',     'Clean code, naming conventions, structure, comments', 10.00, 30.00, 2),
  (7, 5, 'Functionality',    'All described features implemented and working',      10.00, 25.00, 3),
  (8, 5, 'Presentation',     'Clarity of explanation and live demonstration',       10.00, 20.00, 4);

INSERT IGNORE INTO `rubric_criteria` (`id`, `stage_id`, `name`, `description`, `max_marks`, `weight`, `sort_order`) VALUES
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

INSERT IGNORE INTO `batch_stage_deadlines` (`batch_id`, `stage_id`, `start_date`, `deadline`, `is_required`) VALUES
  (3, 1, '2026-11-01 00:00:00', '2026-12-15 23:59:59', 1),
  (3, 2, '2026-12-16 00:00:00', '2027-01-15 23:59:59', 1),
  (3, 3, '2027-01-16 00:00:00', '2027-02-15 23:59:59', 1),
  (3, 4, '2027-02-16 00:00:00', '2027-03-15 23:59:59', 1),
  (3, 5, '2027-03-16 00:00:00', '2027-04-01 23:59:59', 1),
  (3, 6, '2027-04-02 00:00:00', '2027-04-15 23:59:59', 1);

