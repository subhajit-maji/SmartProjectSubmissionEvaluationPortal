# Deploying the portal on InfinityFree

This is a **test/development deployment guide**, checked against InfinityFree's published hosting information on 12 November 2026. InfinityFree does provide a MySQL database service, and this PHP app connects to it with PDO. The free-tier database may identify itself as MariaDB rather than Oracle MySQL, so check the exact engine/version and supported features before importing. Confirm the versions and limits shown in your own control panel before deploying; the provider can change them.

## Compatibility decision

The portal targets Oracle MySQL 8.4. InfinityFree calls the database feature **MySQL Databases** and supports PHP's PDO/MySQLi drivers. Provider support posts report MariaDB 10.6 on free hosting; the actual server version is available from `SELECT VERSION()`. This matters because MariaDB 10.6 does not provide MySQL's `utf8mb4_0900_ai_ci` collation. InfinityFree also does not allow trigger creation on free hosting, while the project installer defines five triggers for evaluation immutability and appeal integrity. As a result, the unmodified installer **will not import successfully** on the free plan. PHP workflow code checks these rules too, but skipping the database triggers removes a layer of protection. Treat the portal as **not production-ready on InfinityFree free hosting**. [InfinityFree PHP 8.4 announcement](https://forum.infinityfree.com/t/free-hosting-is-now-upgraded-to-php-8-4/120906), [provider MySQL setup guide](https://forum.infinityfree.com/t/how-to-setup-a-new-mysql-database/49342), [provider forum: database version](https://forum.infinityfree.com/t/how-to-learn-mysql-version-that-i-use/111369), [provider forum: triggers are unsupported](https://forum.infinityfree.com/t/creating-triggers/91719), [MariaDB migration notes on MySQL 8 collations](https://mariadb.com/resources/blog/what-a-careful-mysql-to-mariadb-migration-still-misses/)

For a production portal with real student records, choose a host that provides the project's Oracle MySQL 8.4 target, reliable transactional email, and a private upload directory outside the document root. Use InfinityFree only after the institution approves the host and you have tested the compatibility changes below with non-sensitive data.

## InfinityFree limits that affect this app

- The website document root is `htdocs`; you cannot set it to this repository's `public/` directory. The rewrite setup below keeps the app's existing `public/index.php` entry point and prevents direct URL access to the rest of the project. [InfinityFree's document-root guidance](https://forum.infinityfree.com/t/changing-where-to-look-for-index-php/101934)
- PHP 8.4 is currently advertised for free hosting. The provider controls the version; verify it on your account. [PHP 8.4 announcement](https://forum.infinityfree.com/t/free-hosting-is-now-upgraded-to-php-8-4/120906)
- The provider's published MariaDB version is 10.6. Confirm with `SELECT VERSION()` in your own database before importing. [Provider forum: database version](https://forum.infinityfree.com/t/how-to-learn-mysql-version-that-i-use/111369)
- MySQL triggers are not supported on the free plan. The normal installer has five triggers; the prepared InfinityFree installer omits them, leaving the app without database-level enforcement of those trigger rules. [InfinityFree trigger support statement](https://forum.infinityfree.com/t/creating-triggers/91719)
- Website uploads have a 20 MB PHP request limit, but non-PHP/HTML/JS files stored on the account have a 10 MB per-file limit. The app's submission limit is set to **9 MiB** so accepted files remain below the provider's stored-file cap. [InfinityFree file size and upload limits](https://forum.infinityfree.com/t/file-size-and-upload-limits/49308)
- PHP `mail()` is heavily restricted on free hosting. The current password recovery adapter uses `mail()`, so password reset emails will not be reliable. Adding an external SMTP integration requires an application change; `config/mail.php` alone does not enable SMTP. [InfinityFree email guidance](https://forum.infinityfree.com/t/emails-from-my-website-arent-working/49242/1)
- Files and PHP code must stay inside the site's `htdocs` tree due to the provider's `open_basedir` restriction. The rewrite configuration below keeps private app paths from being served as URLs. [InfinityFree file-location guidance](https://forum.infinityfree.com/t/uploading-files-into-the-home-directory/51284)
- InfinityFree supplies database credentials from its control panel. Do not run [database/grants-app.example.sql](./database/grants-app.example.sql): it creates a database user and grants privileges, which are managed by the host.

## 1. Create the hosting account and database

1. Create an InfinityFree hosting account and add a domain or free subdomain in its Client Area. Open the website over its direct hostname; free subdomains have SSL by default and the `www` version may not be covered.
2. Open the hosting Control Panel and create a MySQL database from **MySQL Databases**. Save the database name, username, password, and **database hostname** exactly as shown. The database hostname is not `localhost`; it is usually a host such as `sql123.infinityfree.com`. [InfinityFree MySQL setup instructions](https://forum.infinityfree.com/t/how-to-setup-a-new-mysql-database/49342)
3. Open the provider's phpMyAdmin for that database. Run:

   ```sql
   SELECT VERSION();
   SHOW COLLATION LIKE 'utf8mb4_0900_ai_ci';
   SHOW COLLATION LIKE 'utf8mb4_unicode_ci';
   ```

   If the `0900` collation is absent, use the prepared [InfinityFree installer](./database/install-infinityfree.sql), which uses `utf8mb4_unicode_ci`. Do not assume that a database labelled “MySQL” is Oracle MySQL; use the returned version and collation list.

4. Find the website's FTP credentials in the Client Area under the hosting account's **FTP** details, or use the account's File Manager. InfinityFree provides FTP for uploads; use the website's own document root. For the first site this is usually `htdocs`; additional domains can have a domain-specific `htdocs` folder. [InfinityFree hosting terminology](https://forum.infinityfree.com/t/infinityfree-glossary-terminology-and-their-definitions/92708)

5. Set up HTTPS before entering real credentials. Direct free subdomains are covered automatically and should be opened without `www`. For a custom domain, use the Client Area's **Free SSL Certificates** instructions for the domain and verify `https://your-domain/` before redirecting all traffic to HTTPS. [InfinityFree SSL guidance](https://forum.infinityfree.com/t/changes-to-ssl-for-free-subdomains/110169), [custom-domain SSL steps](https://forum.infinityfree.com/t/how-to-get-free-ssl-https-on-infinityfree/49323)

## 2. Import the InfinityFree database schema

1. In phpMyAdmin, select the newly created empty database and open **Import**.
2. Import [database/install-infinityfree.sql](./database/install-infinityfree.sql). This prepared install file uses `utf8mb4_unicode_ci`, has no `USE spep_portal` statement, and omits triggers that InfinityFree free hosting does not permit. It creates the tables and seeds the baseline departments, batches, six stages, rubric criteria, and batch deadlines. It does not create an administrator.
3. Check that phpMyAdmin reports success and that all 20 tables exist. The normal [database/install.sql](./database/install.sql) remains unchanged for Oracle MySQL 8.4. Do not run [database/reset.sql](./database/reset.sql): it drops a database and is only for local development. Do not apply [database/migrations/001_mvp.sql](./database/migrations/001_mvp.sql) to a fresh database; it upgrades an earlier version of this project's schema.
4. The deployment schema omits the five triggers because the free plan rejects trigger commands. Workflow checks are implemented in PHP, but this removes database-level defense-in-depth. Use dummy data and verify evaluation finalization and appeal restrictions before use. If database-level trigger enforcement is required, this free plan cannot provide it.

## 3. Create the first administrator

The installer intentionally has no default admin account. Create one production-style account instead of using the development credentials.

1. The InfinityFree schema seeds a Computer Science department. Find its ID:

   ```sql
   SELECT id FROM departments WHERE code = 'CS';
   ```

2. On your local machine, run `php scripts/generate-hash.php` interactively and save its bcrypt hash. Do not put a real password in a command-line argument or this guide.
3. In phpMyAdmin, insert the administrator using the ID returned for `CS` and the generated hash:

   ```sql
   INSERT INTO users
     (name, email, password, role, department_id, status, force_password_change)
   VALUES
     ('Portal Administrator', 'admin@example.com', '<PASTE_BCRYPT_HASH>', 'admin', <CS_DEPARTMENT_ID>, 'active', 1);
   ```

   Replace both placeholders before running the statement. The account is required to change its password on first sign-in.

## 4. Upload the application

1. In the hosting File Manager or an FTP client, open the website's document root (usually `htdocs` for the first site). Upload only the runtime application files: `.htaccess`, `app/`, `config/`, `public/`, and `storage/`. Do not upload `.git/`, `.vscode/`, SQL files, development tools, local credentials, or database backups.
2. Copy [config/database.infinityfree.example.php](./config/database.infinityfree.example.php) to `config/database.php`. Enter the database hostname, name, username, and password exactly as shown in the hosting panel. Keep the `utf8mb4_unicode_ci` collation because that is what the InfinityFree install file uses.
3. Upload the repository's root [`.htaccess`](./.htaccess) as `htdocs/.htaccess`. It routes public URLs through `public/` and keeps the private application/config/storage paths from being served directly. Make sure the filename is exactly `.htaccess`, not `.htaccess.txt`. InfinityFree supports `.htaccess` and `mod_rewrite`. [InfinityFree rewrite guidance](https://forum.infinityfree.com/t/htaccess/115880/2)
4. Keep `storage/uploads/` under the project directory. The app creates it on first upload if needed. It must remain under the rewrite-protected path; check that direct requests to `/storage/uploads/` and `/config/database.php` do not return file contents.
5. The app accepts submission files up to 9 MiB, below InfinityFree's published 10 MB stored-file limit. Do not try to raise the host's PHP upload or stored-file limits in `.htaccess`.

## 5. Configure the database connection

Fill `config/database.php` with the exact values from the InfinityFree control panel:

- `host`: the displayed database hostname, not `localhost` or the phpMyAdmin server address.
- `port`: use the provider's displayed port, normally `3306`.
- `database`, `username`, and `password`: copy the exact provider values, including account prefixes.
- `collation`: keep `utf8mb4_unicode_ci`; it matches the prepared InfinityFree installer.

Keep `timezone` at `+00:00`. The provider's own migration instructions also note that the database hostname is available in the control panel and must be used instead of `localhost`. [InfinityFree database migration guide](https://forum.infinityfree.com/t/how-to-migrate-your-website-to-infinityfree/49315/1)

## 6. Verify the host before using the portal

Temporarily create `public/host-check.php` containing:

```php
<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'PDO MySQL: ' . (extension_loaded('pdo_mysql') ? 'available' : 'missing') . PHP_EOL;
echo 'Fileinfo: ' . (extension_loaded('fileinfo') ? 'available' : 'missing') . PHP_EOL;

try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        (int) $config['port'],
        $config['database'],
        $config['charset']
    );
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'Database: connected' . PHP_EOL;
    echo 'Database version: ' . $pdo->query('SELECT VERSION()')->fetchColumn() . PHP_EOL;
} catch (Throwable $error) {
    http_response_code(500);
    echo 'Database: connection failed (' . get_class($error) . ')' . PHP_EOL;
}
```

Open `/host-check.php`, confirm the PHP version, both extensions, and database connection. This temporary endpoint intentionally does not print the exception message or credentials. Delete `public/host-check.php` immediately after checking it.

Then:

1. Open the site at its HTTPS hostname without `www` on free subdomains; check the login page and CSS. After confirming HTTPS works, use InfinityFree's instructions to redirect HTTP visitors to HTTPS. The app's reset-link base URL should also use HTTPS if SMTP is later added.
2. Verify `/config/database.php` and `/storage/uploads/` do not reveal files.
3. Sign in as the new administrator and change the temporary password.
4. Review the seeded stages, rubrics, and batch deadlines. Create dummy student and faculty accounts, assign a guide, then test proposal approval, a submission under 9 MiB, authorized download, evaluation, and sign-off with dummy data.
5. Do not onboard real students until the database compatibility, storage privacy, HTTPS, backups, and institutional approval have all been checked.

## Password recovery on the free plan

The portal currently sends reset links through PHP `mail()`. InfinityFree says PHP `mail()` is heavily restricted on free hosting, so reset emails should be treated as unavailable there. The SMTP adapter/library described by InfinityFree is not included in this repository. To enable email recovery, implement and test an SMTP mailer using an external provider, store credentials outside public access, and update this app's mail adapter; setting `config/mail.php` by itself is not sufficient. [InfinityFree email guidance](https://forum.infinityfree.com/t/emails-from-my-website-arent-working/49242/1)

For a test site without SMTP, an administrator can issue a temporary password manually: generate a bcrypt hash locally with `php scripts/generate-hash.php`, then use phpMyAdmin to update only the intended account and force a password change:

```sql
UPDATE users
SET password = '<PASTE_BCRYPT_HASH>', force_password_change = 1,
    session_version = session_version + 1
WHERE email = 'student@example.com';
```

Replace both placeholders and verify that exactly one account was updated. Never save or send plaintext passwords through the database or support messages.

## If deployment fails

- **Unknown collation:** check `SHOW COLLATION`; convert the deployment copy and app config to a collation available on the host.
- **Database connection refused:** copy the database hostname exactly from the hosting panel; do not use `localhost` or the phpMyAdmin hostname.
- **404 or 500 after upload:** confirm the web files are under `htdocs`, the file is exactly `.htaccess`, and the rewrite rules match the snippet above. Check PHP logs through the provider's panel where available.
- **Uploaded submission disappears:** keep it under 10 MB and confirm the app is writing under `htdocs`.
- **No reset email:** expected with the current `mail()` adapter on free hosting; SMTP support requires an application change.
- **SQL import errors:** do not try the destructive reset script. Confirm that you imported [database/install-infinityfree.sql](./database/install-infinityfree.sql) into a new empty database and check the provider's `SELECT VERSION()` output.
- **Trigger permission errors:** expected on InfinityFree free hosting. The app can use PHP-side validation, but this loses database-level trigger enforcement; choose a trigger-capable host if that assurance is required.

## References

- [InfinityFree: getting started and MySQL database setup](https://forum.infinityfree.com/t/how-to-migrate-your-website-to-infinityfree/49315/1)
- [InfinityFree: PHP 8.4 upgrade](https://forum.infinityfree.com/t/free-hosting-is-now-upgraded-to-php-8-4/120906)
- [InfinityFree: MariaDB version](https://forum.infinityfree.com/t/how-to-learn-mysql-version-that-i-use/111369)
- [InfinityFree: triggers are unsupported on free hosting](https://forum.infinityfree.com/t/creating-triggers/91719)
- [InfinityFree: document root and public subfolder](https://forum.infinityfree.com/t/changing-where-to-look-for-index-php/101934)
- [InfinityFree: upload and file size limits](https://forum.infinityfree.com/t/file-size-and-upload-limits/49308)
- [InfinityFree: PHP mail and SMTP](https://forum.infinityfree.com/t/emails-from-my-website-arent-working/49242/1)
- [MariaDB: MySQL-to-MariaDB collation compatibility](https://mariadb.com/resources/blog/what-a-careful-mysql-to-mariadb-migration-still-misses/)
