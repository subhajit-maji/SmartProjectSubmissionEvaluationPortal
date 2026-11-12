# Smart Project Submission & Evaluation Portal

A dependency-free PHP 8.1+ and MySQL/MariaDB portal for managing academic projects from proposal approval through staged submissions, evaluation, appeals, feedback, and final sign-off.

## Features

- Role-based dashboards for administrators, faculty, and students
- Project proposals, teams, project guides, and approval workflows
- Staged submissions with deadlines, revisions, and private file downloads
- Configurable rubrics, weighted evaluation, grades, and finalized-score protection
- Student appeals and one-time special revisions
- Feedback threads and in-app notifications
- Password changes and one-time password recovery links
- CSRF protection, secure sessions, output escaping, prepared statements, upload validation, and login rate limiting
- Status history and administrative audit logging

## Requirements

- PHP 8.1 or newer
- `pdo_mysql` and `fileinfo` PHP extensions
- Oracle MySQL 8.4 for the full feature set
- A web server configured to serve the `public/` directory

The application has no Composer or frontend build dependencies.

## Local setup

1. Create an empty MySQL database using `utf8mb4` and `utf8mb4_0900_ai_ci`.
2. Execute [`database/install.sql`](./database/install.sql) with a database migration account.
3. Copy [`config/database.example.php`](./config/database.example.php) to `config/database.php` and enter local credentials.
4. Create the restricted runtime account using [`database/grants-app.example.sql`](./database/grants-app.example.sql).
5. For local testing only, optionally execute [`database/seed-dev.sql`](./database/seed-dev.sql).
6. Configure the web server document root as `public/`.
7. Start the PHP development server from the repository root:

   ```powershell
   php -S 127.0.0.1:8000 -t public
   ```

8. Open <http://127.0.0.1:8000>.

The development seed is never intended for production. The normal installer does not create an administrator account.

## Existing database upgrades

Back up the database before applying migrations.

- Earlier 19-table schema: apply [`001_mvp.sql`](./database/migrations/001_mvp.sql), then [`002_session_version.sql`](./database/migrations/002_session_version.sql), then [`003_status_history_entities.sql`](./database/migrations/003_status_history_entities.sql).
- Existing 20-table MVP schema: apply [`002_session_version.sql`](./database/migrations/002_session_version.sql) when `users.session_version` is missing, then apply [`003_status_history_entities.sql`](./database/migrations/003_status_history_entities.sql).

The installer uses `CREATE TABLE IF NOT EXISTS`; it does not update existing table definitions.

## InfinityFree deployment

InfinityFree can be used for testing or demonstrations, but it is not the recommended host for production records. Its free plan may use MariaDB, does not support the project's database triggers, restricts email delivery, and keeps uploads under the website account.

For InfinityFree:

1. Create a database in the hosting control panel.
2. Open that database in phpMyAdmin.
3. Import [`database/install-infinityfree.sql`](./database/install-infinityfree.sql).
4. Do not import [`database/install.sql`](./database/install.sql), [`database/reset.sql`](./database/reset.sql), or [`database/grants-app.example.sql`](./database/grants-app.example.sql).
5. Copy [`config/database.infinityfree.example.php`](./config/database.infinityfree.example.php) to `config/database.php`.
6. Enter the exact database host, database name, username, and password shown by InfinityFree.
7. Upload the application to `htdocs`, including the root [`.htaccess`](./.htaccess).
8. Create the first administrator manually with a bcrypt password hash.
9. Use HTTPS and test all workflows with non-sensitive data.

See [`infinityfree.md`](./infinityfree.md) for the full hosting procedure and limitations.

## QA and viva preparation

Use [`MVP.md`](./MVP.md) for easy-to-hard functional, security, database, and deployment testing. Use [`Q-A.md`](./Q-A.md) for plain-language viva questions and answers covering the full website.

## Production guidance

- Use Oracle MySQL 8.4 or another trigger-capable managed database.
- Serve only `public/`; keep configuration and upload storage inaccessible to direct web requests.
- Keep `config/database.php` and `config/mail.php` outside version control.
- Use separate migration and runtime database accounts.
- Generate password hashes with [`scripts/generate-hash.php`](./scripts/generate-hash.php).
- Never store or commit plaintext passwords, database credentials, or real account hashes.
- Configure HTTPS, backups, log retention, and a maintained mail transport.
- Review upload storage, authorization, rate limits, and error logs before onboarding real users.

The application uses PHP's configured `mail()` transport for password recovery. A reliable SMTP integration is not included.

## Validation

Run PHP syntax checks and the dependency-free smoke tests:

```powershell
php tests\smoke.php
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

Validate both SQL installers against disposable databases matching their target engines. The reset script is destructive and must only be used for local development.

## Repository structure

```text
app/       Application workflow, queries, mailer, and views
config/    Safe configuration templates
database/  Installers, migrations, grants, reset, and development seed
public/    Web entry point and assets
scripts/   CLI utilities
storage/   Private upload storage
tests/     Dependency-free smoke checks
```

## Current limitations

- No external SMTP-library integration
- No deployment-specific web-server configuration
- Database triggers are unavailable on InfinityFree free hosting
- Hosting-specific upload, email, backup, and privacy guarantees must be verified before production use
