# Smart Project Submission & Evaluation Portal

A PHP and MySQL project for managing student project proposals, current report submissions, and faculty feedback.

## What it does

- Admin creates student and faculty accounts.
- Students can submit project proposals.
- Admin approves or rejects the proposal and assigns a faculty guide.
- Students upload a PDF report after approval and may replace it with a revised report when faculty requests a revision.
- Faculty approves the report or requests a revision with written feedback.
- Students and faculty can download the report only when they are connected to that project.

The database has four tables: `users`, `projects`, `submissions`, and `feedback`. The app uses PHP sessions, password hashing, prepared SQL statements, CSRF tokens, output escaping, and private PDF storage.

## Project structure

- `public/index.php` is the web entry point. The web server should use `public/` as its document root.
- `app/bootstrap.php` loads the database connection and shared security and upload helpers.
- `app/actions.php` handles sign-in, account creation, proposals, report submissions, reviews, and downloads.
- `app/views.php` renders the sign-in page and role-specific dashboards.
- `public/404.php` is the branded not-found page; `public/router.php` serves it for missing paths in PHP's built-in server.
- `public/.htaccess` maps Apache 404 responses to the same page, and `public/assets/favicon.svg` provides the browser-tab icon.
- `config/database.example.php` is the local database configuration template. Keep `config/database.php` private.
- `database/` contains the schema and optional local demonstration account.
- `storage/uploads/` keeps submitted PDFs outside the public web directory.

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `fileinfo`, and `mbstring`
- MySQL or MariaDB
- Apache, XAMPP, or PHP's built-in server

## Local setup

1. Create a database named `smart_project_portal` using `utf8mb4_unicode_ci`.
2. Import `database/install.sql`, then `database/seed-demo.sql`.
3. Copy `config/database.example.php` to `config/database.php` and enter your local database details.
4. From this folder, run:

   ```powershell
   php -S 127.0.0.1:8000 -t public public/router.php
   ```

5. Open `http://127.0.0.1:8000`.

The local seed account is `admin@smartproject.local` with password `Admin@123`. It is only for a disposable local database. Change it before any shared demonstration. The administrator can create faculty and student accounts from the dashboard.

## Project flow

1. Sign in as the administrator and create a faculty and student account.
2. Sign in as the student and submit a project proposal.
3. Sign in as the administrator, approve the proposal, and assign the faculty guide.
4. Sign in as the student and upload a PDF report.
5. Sign in as the faculty member, download the report, and approve it or request a revision with feedback.
6. Sign in again as the student to read the feedback and check the report status. If a revision is requested, upload a revised PDF and repeat the faculty review.

## Scope

Students may submit more than one project proposal. Each project represents one proposal and stores one current report; a revision replaces that report file while preserving the feedback history. This does not include teams, multiple submission stages, weighted rubrics, appeals, bulk import, or analytics.

## Project report

- [Project report](./docs/Project-Report.docx)

Edit the report in Word. Its text and tables are editable; screenshots and diagrams remain images. Replace the student, guide, institution, course, and academic-session placeholders before submission, then check the contents page numbers after editing.
