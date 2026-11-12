# Deploy the portal to a Windows VPS using RDP

This guide deploys the portal to a Windows Server VPS with IIS, PHP FastCGI, and Oracle MySQL 8.4. It assumes you have RDP administrator access, a domain name, and a VPS public IP address. It keeps the application code and database credentials outside IIS's public document root.

## 1. Prepare the VPS and restrict remote access

1. In both your VPS provider's firewall and Windows Defender Firewall, allow inbound TCP 80 and 443 for the website. Restrict RDP (normally TCP 3389, and optionally UDP 3389) to your administrator IP or VPN. Do not open MySQL port 3306 to the internet. Microsoft recommends not exposing administrative RDP to all public addresses; use a VPN or gateway when available. [Microsoft RDP security guidance](https://learn.microsoft.com/en-us/troubleshoot/azure/virtual-machines/troubleshoot-rdp-nsg-problem)
2. Connect with Windows Remote Desktop (`mstsc`) using the VPS public IP and the credentials from your provider. Before changing firewall rules, confirm you can recover access through the provider console if your IP changes or an RDP rule is misconfigured.
3. Change any provider-supplied administrator password to a unique strong password, install Windows Updates, and keep Network Level Authentication enabled for RDP. Add a separate deployment administrator if multiple people need access; do not share the built-in Administrator login. [Microsoft NLA guidance](https://learn.microsoft.com/en-us/troubleshoot/windows-server/remote/troubleshoot-remote-desktop-disconnected-errors)
4. In your domain's DNS, point the site hostname's A record to the VPS public IPv4 address. Add an AAAA record only if the VPS has working public IPv6 and its firewall is configured for it. Allow DNS time to propagate before requesting an HTTPS certificate.

## 2. Install IIS

1. In Server Manager, choose **Manage → Add Roles and Features** and install **Web Server (IIS)**.
2. In Role Services, include **CGI** under **Application Development**, plus **Default Document**, **Static Content**, **HTTP Errors**, **HTTP Logging**, and **IIS Management Console**. CGI is required for IIS FastCGI. [Microsoft Server Manager instructions](https://learn.microsoft.com/en-us/windows-server/administration/server-manager/add-remove-roles-features) · [IIS FastCGI requirements](https://learn.microsoft.com/en-us/iis/configuration/system.webServer/fastCgi/)
3. Do not use the repository's root `.htaccess` on IIS; IIS does not use Apache `.htaccess` rules. This app already uses `public/index.php` as its entry point and does not need Apache rewrite rules.

## 3. Install PHP for IIS FastCGI

1. Install the x64 Microsoft Visual C++ Redistributable for Visual Studio 2015–2022.
2. Download the latest supported x64 **Non-Thread Safe (NTS)** PHP ZIP from the official PHP for Windows site. NTS is the PHP build recommended for IIS FastCGI. Extract it to `C:\PHP`. [PHP Windows downloads and build guidance](https://www.php.net/downloads.php?os=windows) · [PHP IIS installation](https://www.php.net/manual/en/install.windows.iis.php)
3. Copy `C:\PHP\php.ini-production` to `C:\PHP\php.ini`. Set at least these values; adjust paths if you chose another install location:

   ```ini
   extension_dir="C:\PHP\ext"
   extension=pdo_mysql
   extension=fileinfo
   date.timezone=UTC
   expose_php=Off
   display_errors=Off
   log_errors=On
   error_log="C:\Sites\SpepPortal\logs\php_errors.log"
   upload_tmp_dir="C:\PHP\tmp\uploads"
   session.save_path="C:\PHP\tmp\sessions"
   upload_max_filesize=9M
   post_max_size=12M
   max_execution_time=60
   memory_limit=128M
   ```

   Create `C:\PHP\tmp\uploads`, `C:\PHP\tmp\sessions`, and `C:\Sites\SpepPortal\logs` before starting the site. The IIS application-pool identity needs Modify permission on the two PHP temp folders and the log folder.
4. In IIS Manager, open the server's **FastCGI Settings** and add `C:\PHP\php-cgi.exe`. In that FastCGI application's environment variables, set `PHPRC` to `C:\PHP` so PHP loads the intended `php.ini`. Then open **Handler Mappings** and add a module mapping for `*.php` using `FastCgiModule` and `C:\PHP\php-cgi.exe`. Use `php-cgi.exe`, not `php.exe`. [Microsoft FastCGI configuration](https://learn.microsoft.com/en-us/iis/configuration/system.webServer/fastCgi/) · [PHP's IIS handler steps](https://www.php.net/manual/en/install.windows.iis.php)
5. Restart IIS after changing PHP settings. In a temporary test page, verify `PHP_VERSION`, `extension_loaded('pdo_mysql')`, and `extension_loaded('fileinfo')`. Delete the test page as soon as you confirm the results; do not leave a `phpinfo()` page on the public site.

## 4. Install and secure MySQL 8.4

1. Download the MySQL Community Server **8.4 Windows MSI** from Oracle's MySQL 8.4 download page. MySQL 8.1 and later use the product MSI/ZIP with MySQL Configurator; the old MySQL Installer is for the 8.0 series. Use MySQL Configurator to install MySQL as a Windows service and set a strong root password. [MySQL 8.4 Windows installation](https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html) · [MySQL 8.4 packages](https://dev.mysql.com/doc/refman/8.4/en/windows-choosing-package.html)
2. This app and database are on the same VPS. In MySQL's `my.ini`, under `[mysqld]`, set `bind-address=127.0.0.1`, then restart the MySQL service. This limits database TCP listening to the local machine. Also leave TCP 3306 blocked in both the VPS provider firewall and Windows Firewall. [MySQL bind-address behavior](https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_bind_address)
3. Open **MySQL 8.4 Command Line Client** or a local MySQL Workbench connection and sign in as the MySQL administrator. Create the database:

   ```sql
   CREATE DATABASE spep_portal
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_0900_ai_ci;
   ```

4. Import [database/install.sql](./database/install.sql), which creates the schema and baseline departments, batches, stages, rubrics, and deadlines. It includes trigger definitions, so run it with the MySQL command-line client or Workbench's SQL script runner, not as one PDO query. For the command-line client, connect with `-p` so it prompts for the password, then run:

   ```text
   SOURCE C:/Sites/SpepPortal/database/install.sql;
   ```

   The installer does not create an administrator. Do not run [database/reset.sql](./database/reset.sql); it drops the database.
5. Create the restricted runtime account using [database/grants-app.example.sql](./database/grants-app.example.sql). First make a private copy of that file and replace its password placeholder with a unique secret. Run that copy as the MySQL administrator. Do not put a password directly in a command argument or commit the edited grants file.
6. MySQL binds only to loopback, the runtime user is limited to `127.0.0.1`, and port 3306 remains closed to external clients. The app's PDO host must therefore be `127.0.0.1`.

## 5. Place the app outside the public web root

Use this layout:

```text
C:\Sites\SpepPortal\
  app\
  config\
    database.php
  database\
  public\
    index.php
    assets\
  scripts\
  storage\
    uploads\
  logs\
```

1. Copy the repository to `C:\Sites\SpepPortal` using RDP drive redirection or your VPS provider's secure file-transfer method. Keep the full `app`, `config`, `database`, `scripts`, and `storage` directories here. In IIS, set the website's **Physical path** to `C:\Sites\SpepPortal\public`, not the project root.
2. Create `config\database.php` from [config/database.example.php](./config/database.example.php). Set `host` to `127.0.0.1`, `port` to `3306`, `database` to `spep_portal`, `username` to `spep_app`, `password` to the secret used in the grants copy, and leave the MySQL 8.4 collation as `utf8mb4_0900_ai_ci`.
3. Create an IIS application pool named `SpepPortal` with **.NET CLR Version: No Managed Code** and **Identity: ApplicationPoolIdentity**. Create an IIS website using that pool, physical path `C:\Sites\SpepPortal\public`, and an HTTP binding for your domain on port 80. Add `index.php` to that site's Default Documents if it is not already present. Disable Directory Browsing. Set **Anonymous Authentication** to use the application-pool identity; the portal handles user sign-in itself.
4. Grant `IIS AppPool\SpepPortal` **Read & Execute** on `C:\PHP` and `C:\Sites\SpepPortal`. Grant **Modify** only on `storage\uploads`, `logs`, `C:\PHP\tmp\uploads`, and `C:\PHP\tmp\sessions`. Keep `config\database.php` outside `public`; never grant the app pool Modify on project code or configuration.
5. Confirm the PHP handler runs a temporary version/extension check and that the site's static CSS loads. Remove the temporary check page afterward. The application stores submission files under `storage\uploads`, outside the IIS public root, and serves them through its authorization-checked download route.

## 6. Create the first administrator

The installer seeds department ID 1 with code `CS`. From an elevated Command Prompt or PowerShell, run the password utility interactively:

```powershell
C:\PHP\php.exe C:\Sites\SpepPortal\scripts\generate-hash.php
```

Copy the generated bcrypt hash. In the MySQL command-line client, insert a production admin account:

```sql
INSERT INTO users
  (name, email, password, role, department_id, status, force_password_change)
VALUES
  ('Portal Administrator', 'admin@example.com', '<GENERATED_BCRYPT_HASH>', 'admin', 1, 'active', 1);
```

Replace the example email and hash. The account must change its password on first sign-in. Do not import [database/seed-dev.sql](./database/seed-dev.sql) on a public server.

## 7. Enable HTTPS

1. Confirm the DNS A record resolves to this VPS and the site responds over HTTP on port 80.
2. Install the current x64 **win-acme** release, run it as Administrator, select the IIS site/domain, and request a Let's Encrypt certificate. The IIS installation plugin creates or updates the HTTPS binding. win-acme installs a scheduled renewal task; verify that task exists and succeeds. [win-acme IIS setup](https://www.win-acme.com/manual/getting-started) · [renewal behavior](https://www.win-acme.com/manual/automatic-renewal)
3. Allow inbound TCP 443 in the provider firewall and Windows Firewall. Verify `https://your-domain/` works before adding the HTTP-to-HTTPS redirect.
4. Install [Microsoft IIS URL Rewrite 2.1](https://learn.iis.net/downloads/microsoft/url-rewrite), then create a site-level rule: match all paths, apply when `{HTTPS}` is `off`, and redirect to `https://{HTTP_HOST}/{R:0}` with the query string preserved. Keep ports 80 and 443 open for HTTP validation and normal web traffic. [URL Rewrite documentation](https://learn.microsoft.com/en-us/iis/extensions/url-rewrite/iis-url-rewrite)

## 8. Final checks and operations

1. Sign in over HTTPS and change the initial admin password.
2. Create test student and faculty accounts. Test proposal review, stage submission under 9 MiB, authorized file download, evaluation, appeal handling, notifications, and project sign-off with dummy data.
3. Confirm `https://your-domain/config/database.php` and `https://your-domain/storage/uploads/` do not expose files. Those paths are outside the site's physical root and should return 404/403.
4. Check IIS logs, Windows Event Viewer, and `C:\Sites\SpepPortal\logs\php_errors.log` when troubleshooting. Keep `display_errors=Off` on the public site.
5. Schedule Windows security updates and backups. Back up both `spep_portal` and `storage\uploads` to storage outside this VPS. Use `mysql_config_editor` or another protected credential mechanism for scheduled database backups; do not put MySQL passwords in Task Scheduler command lines. [MySQL credential storage guidance](https://dev.mysql.com/doc/refman/8.4/en/mysql-config-editor.html)
6. The current mail adapter uses PHP `mail()`. It does not include an authenticated SMTP client, so password recovery email needs a separate SMTP integration and credentials before the portal can send reset links. Until SMTP is configured, an administrator can issue a temporary password by generating a bcrypt hash with `scripts/generate-hash.php` and updating only the intended user in MySQL:

   ```sql
   UPDATE users
   SET password = '<GENERATED_BCRYPT_HASH>', force_password_change = 1,
       session_version = session_version + 1
   WHERE email = 'student@example.com';
   ```

   Replace the placeholders and confirm exactly one row was updated. Never store or send the plaintext password in SQL or support messages.

## Common problems

- **IIS downloads PHP instead of running it:** verify the CGI role service, FastCGI application path, and `*.php` handler mapping to `C:\PHP\php-cgi.exe`.
- **PDO driver missing:** enable `pdo_mysql` in the `php.ini` used by FastCGI and restart IIS.
- **Database connection fails:** confirm MySQL service is running, `bind-address=127.0.0.1`, database/user/password values, and that `config/database.php` uses `127.0.0.1`.
- **Uploads fail:** verify `fileinfo`, `upload_max_filesize=9M`, `post_max_size=12M`, PHP temp-folder ACLs, and Modify access for `IIS AppPool\SpepPortal` on `storage\uploads`.
- **500.19 after HTTPS redirect:** confirm IIS URL Rewrite is installed; remove the rewrite rule temporarily to recover the site, then reinstall/configure the module.
- **Reset email is not received:** the app does not include SMTP integration; PHP `mail()` requires a working outbound mail transport.

## Official references

- [Microsoft: Add IIS roles and features](https://learn.microsoft.com/en-us/windows-server/administration/server-manager/add-remove-roles-features)
- [Microsoft: IIS FastCGI](https://learn.microsoft.com/en-us/iis/configuration/system.webServer/fastCgi/)
- [PHP: IIS installation](https://www.php.net/manual/en/install.windows.iis.php)
- [PHP: official Windows downloads](https://www.php.net/downloads.php?os=windows)
- [Oracle: MySQL 8.4 on Windows](https://dev.mysql.com/doc/refman/8.4/en/windows-installation.html)
- [Microsoft: Remote Desktop Network Level Authentication](https://learn.microsoft.com/en-us/troubleshoot/windows-server/remote/troubleshoot-remote-desktop-disconnected-errors)
- [MySQL: secure client credentials](https://dev.mysql.com/doc/refman/8.4/en/mysql-config-editor.html)
- [win-acme documentation and downloads](https://www.win-acme.com/)
