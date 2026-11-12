-- DEVELOPMENT ONLY. Creates the documented local bootstrap account.
-- Never run this script on a production database.
-- Login: admin@spep.local
-- Password: Admin@123 (must be changed at first login)

INSERT IGNORE INTO `users`
  (`id`, `name`, `email`, `password`, `role`, `department_id`, `status`, `force_password_change`)
VALUES
  (1,
   'Development Administrator',
   'admin@spep.local',
   '$2y$12$xKBQdGp7fsp6gweol2LbzObaMg3wI17r66TAyaA/LMvJ/MRTTb3Qq',
   'admin',
   1,
   'active',
   1);
