-- Local demonstration only. Login: admin@smartproject.local / Admin@123
-- Change this password before using the portal outside a disposable demo database.
INSERT INTO users (name, email, password_hash, role)
VALUES (
    'Demo Administrator',
    'admin@smartproject.local',
    '$2y$12$xKBQdGp7fsp6gweol2LbzObaMg3wI17r66TAyaA/LMvJ/MRTTb3Qq',
    'admin'
);
