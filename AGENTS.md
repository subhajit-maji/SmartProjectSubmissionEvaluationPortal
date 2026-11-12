# Smart Project Submission & Evaluation Portal

## Repository scope

This repository contains a dependency-free PHP 8.1+ portal, a MySQL 8.4 schema, and one PHP CLI utility. The app includes role dashboards, project review, teams, guides, private staged submissions, evaluation, stage/rubric/deadline management, appeals, feedback, notifications, password recovery, account/organization management, and project sign-off. Email delivery uses PHP's configured mail transport. It does not include external SMTP-library integration or deployment-specific web-server configuration. Do not infer unimplemented workflows from the schema alone.

## Canonical references

- [`config/database.example.php`](config/database.example.php): PDO connection defaults, UTC settings, and intended least-privilege database accounts.
- [`config/database.infinityfree.example.php`](config/database.infinityfree.example.php): PDO template for InfinityFree's MariaDB-compatible free-plan database.
- [`config/mail.example.php`](config/mail.example.php): password-recovery sender and public base URL.
- [`database/install.sql`](database/install.sql): non-destructive Oracle MySQL 8.4 schema installer, triggers, and baseline departments, batches, stages, deadlines, and rubrics; it does not seed an administrator.
- [`database/install-infinityfree.sql`](database/install-infinityfree.sql): free-plan MariaDB-compatible schema copy; it uses `utf8mb4_unicode_ci` and omits triggers that InfinityFree does not permit.
- [`database/reset.sql`](database/reset.sql): destructive local-development database reset with sample records and a development-only administrator.
- [`scripts/generate-hash.php`](scripts/generate-hash.php): bcrypt password-hash utility and password requirements.
- [`app/actions.php`](app/actions.php): authenticated workflow actions and validation.
- [`app/data.php`](app/data.php): role-scoped dashboard queries and authorized file delivery.
- [`app/mailer.php`](app/mailer.php): password-recovery email adapter.
- [`public/index.php`](public/index.php): web entry point.
- [`database/grants-app.example.sql`](database/grants-app.example.sql): least-privilege runtime account template.
- [`database/migrations/001_mvp.sql`](database/migrations/001_mvp.sql): one-time upgrade from the earlier schema.
- [`database/migrations/002_session_version.sql`](database/migrations/002_session_version.sql): one-time addition of per-user session revocation to existing installations.
- [`README.md`](README.md): setup and current MVP limits.
- [`vps-rdp-deploy.md`](vps-rdp-deploy.md): Windows Server VPS deployment through RDP, IIS FastCGI, PHP, and MySQL 8.4.
- [`tests/smoke.php`](tests/smoke.php): dependency-free PHP smoke checks.

Link to these files instead of duplicating their detailed schema or setup documentation.

## Working conventions

- Target Oracle MySQL 8.4 with InnoDB, `utf8mb4`, `utf8mb4_0900_ai_ci`, and UTC timestamps unless the task explicitly changes compatibility requirements.
- PHP database access should use PDO with exceptions, associative fetches, native prepared statements, and parameter binding. Never interpolate user input into SQL.
- Keep runtime database credentials in the untracked `config/database.php`, copied from the example and filled with unique secrets. Never commit credentials or generated password hashes for real accounts.
- Use `scripts/generate-hash.php` for bcrypt password hashes; never store plaintext passwords.
- Preserve foreign-key names, indexes, constraints, trigger behavior, and existing naming style when modifying SQL. Check both installer and reset scripts when changing the schema so development environments remain consistent.
- Use `database/install.sql` for non-destructive installation. Treat `database/reset.sql` as development-only: it drops the entire `spep_portal` database and permanently erases data.
- Record application-layer status changes in `status_history` and administrative mutations in `audit_logs` when implementing those layers. Respect finalized-evaluation immutability and the special-revision appeal restriction.
- Keep schema changes narrowly scoped and explain any migration or compatibility impact. Do not assume MariaDB or older MySQL versions support this schema unchanged.

## Application security and workflow requirements

- Enforce authorization in application code; schema comments and foreign keys do not enforce role, project membership, team-leader, guide, or batch rules.
- Preserve CSRF protection, session hardening, output escaping, rate limiting, secure upload validation/storage, and security headers in all new routes.
- Validate workflow transitions, rubric totals, score bounds, appeal permissions, and primary-guide rules in application services and transactions.
- The non-destructive installer must not create an administrator account. The destructive reset and `database/seed-dev.sql` may retain a clearly marked development-only administrator for local testing.

## Validation

There is no build system. Run `php tests/smoke.php` and PHP syntax checks after PHP changes. For SQL changes, validate against MySQL 8.4 using a disposable development database when available. Run relevant CLI scripts interactively; never pass passwords as command-line arguments.
