# Laravel installation and deployment

## Local Windows setup

Use the commands in [README](../README.md). PHP 8.3+ is mandatory; the inspected XAMPP PHP 8.2 is too old. The workspace includes an independent PHP 8.4 runtime that `tools/php.cmd` selects. The release ZIP does not include PHP itself.

XAMPP can continue providing MariaDB even while the website runs with a different PHP runtime through Artisan. Apache is not needed for the Artisan website server. phpMyAdmin still uses Apache if you choose it to create the database.

The `.env.example` uses MySQL port 3308 to avoid the observed conflicts on 3306 and 3307. Configure XAMPP's `my.ini`, control-panel port and phpMyAdmin consistently, or change `DB_PORT` to your actual server port. The test service on port 3318 is private QA infrastructure, not your installation database.

Use a new empty database and real local credentials. `cms:install` runs the SQL through Laravel migrations and seeds, so no manual SQL import is needed. Run `key:generate` before installation. Keep the generated key private and stable after deployment.

If `vendor/` is missing, run `composer install` with PHP 8.3+. In the current workspace you can explicitly use:

```powershell
.\tools\php.cmd C:\ProgramData\ComposerSetup\bin\composer.phar install
```

The ready-to-run ZIP already contains production dependencies. `composer install` additionally installs development/test packages when needed.

## Apache and cPanel

Laravel's public document root must be the application's **public/** directory. The root `.htaccess` deliberately denies direct access to application files. The previous native CMS's root/subdirectory rewrite configuration is no longer used.

Example Apache virtual host, after configuring Apache to use PHP 8.3+:

```apache
<VirtualHost *:80>
    ServerName globaltradelines.local
    DocumentRoot "E:/WEBSITES/globaltradelines/public"
    <Directory "E:/WEBSITES/globaltradelines/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Map the hostname locally and set `APP_URL=http://globaltradelines.local`. Do not point Apache at this project's root directory and do not attempt to load a non-thread-safe CLI PHP build as an Apache module. Artisan is the straightforward local option with the supplied independent runtime.

On cPanel, select PHP 8.3+, upload the application outside `public_html`, and point the domain root at `public/`. If the host fixes `public_html`, put the application's public files there and keep the other folders outside it; adjust the paths in public `index.php` to the application folder. Never upload private application folders into a publicly served directory without isolation.

Configure HTTPS `APP_URL`, database credentials and a new application key. Install dependencies with `composer install --no-dev --optimize-autoloader` if not using the ready-to-run archive. Run `php artisan cms:install` for a fresh database, or follow the migration guide for existing data. Then run:

```text
php artisan optimize
```

Both `storage/` and `bootstrap/cache/` must be writable by the PHP account. Do not ship cached local configuration, test credentials or the development `.env` to production. After changing production `.env`, rebuild the configuration cache. No background queue worker is required for the synchronous workflows implemented here.

## Uploads

Configure the active PHP runtime with `file_uploads=On`, `upload_max_filesize=25M`, `post_max_size=100M`, and `max_file_uploads=20`. `UPLOAD_MAX_MB` is the application limit. PHP also needs a writable `upload_tmp_dir`; this was explicitly set for the workspace PHP runtime after real upload testing identified an inaccessible system temp directory.

Files are stored privately in `storage/app/private/uploads` and delivered through `/media/{id}` after permission/publication checks. Do not run `storage:link` for these private files. The public logo and favicon assets live in `public/assets`.

## Email and integrations

Set `MAIL_ENABLED=true`, `MAIL_MAILER=smtp`, your SMTP host/port/credentials, and `MAIL_FROM_ADDRESS`. Set the notification recipient in Admin → Contact Information. A successful SMTP send is recorded as `accepted`; it is not a guarantee of mailbox delivery. `MAIL_MAILER=log` records `logged`, and disabled mail records `disabled`.

For Turnstile, put the public site key in Admin → Integrations and `TURNSTILE_SECRET` in `.env`. The application verifies the token, configured hostname and quote action. Test on the final domain with your real credentials.

Password reset by email is available when mail is enabled. For local recovery:

```text
php artisan cms:reset-password
```

This updates the hash and invalidates previous sessions.

## Troubleshooting

- PHP version/dependency error: run `tools/php.cmd -v` in this workspace, or install PHP 8.3+ elsewhere. XAMPP's PHP and the command-line PHP may differ.
- Database refusal: check that the server is running and `.env` uses its actual port, database and credentials.
- Port 8013 busy: choose another free port in both `APP_URL` and `artisan serve --port=...`.
- 403 at the project root under Apache: configure the document root as `public/`.
- 500 response: inspect `storage/logs/laravel.log`; keep `APP_DEBUG=false` on public deployments.
- 419 form response: refresh the form and ensure cookies, URL hostname and session directory permissions are consistent.
- Upload failure: verify the active runtime's temporary directory and PHP/application size limits.

Back up the database, private uploads and `.env` together. Review recovered business claims, missing legal pages and indexing settings before launch.
