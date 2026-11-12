<?php
/**
 * Database Configuration Template
 * =================================
 * Engine: Oracle MySQL 8.4 LTS
 * Driver: PDO MySQL
 *
 * Copy this file to `config/database.php` and set your credentials.
 *
 * Database Accounts (Strict Least Privilege):
 * 1. Runtime Application Account (`spep_app`):
 *    - Used exclusively by the PHP web application for regular operations.
 *    - Table privileges for the current MVP are listed in
 *      `database/grants-app.example.sql`; apply them to the same host used below.
 *    - The runtime account has no DDL privileges. Evaluation triggers protect
 *      finalized evaluation records; scores are append-only for the app account.
 *    - PROHIBITED from all DDL commands (CREATE, DROP, ALTER, RENAME, TRUNCATE).
 *    - PROHIBITED from DELETE on all tables except login_attempts.
 *
 * 2. Migration/Installer Account (`spep_admin`):
 *    - Used strictly from CLI or MySQL Workbench for executing `database/install.sql`.
 *    - Holds DDL + DML privileges on `spep_portal.*`.
 *
 * SECURITY NOTICE:
 * All passwords below are insecure PLACEHOLDERS.
 * You MUST generate unique, cryptographically strong passwords for each account.
 * `config/database.php` is excluded from version control (.gitignore).
 */

return [
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'database'  => 'spep_portal',
    'username'  => 'spep_app',
    'password'  => 'PLACEHOLDER_APP_PASSWORD_CHANGE_ME', // [PLACEHOLDER - SET UNIQUE SECRET]
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_0900_ai_ci',
    'timezone'  => '+00:00', // UTC session timezone
    'options'   => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // The PDO DSN sets charset=utf8mb4; this command sets the UTC session zone.
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00', collation_connection = 'utf8mb4_0900_ai_ci'",
    ],
];
