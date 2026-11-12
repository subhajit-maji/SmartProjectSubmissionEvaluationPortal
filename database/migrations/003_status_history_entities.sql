-- Expand status history entity types to match application-layer audit events.
-- Back up first and apply once to an existing portal database.

USE `spep_portal`;

ALTER TABLE `status_history`
  MODIFY COLUMN `entity_type`
    ENUM('project','submission','user','department','batch','submission_stage','project_guide','evaluation','submission_appeal')
    NOT NULL;
