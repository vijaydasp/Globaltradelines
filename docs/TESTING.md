# Laravel migration verification

Verified locally on 16 October 2026 with Laravel 13.37.0, PHP 8.4.26 and MariaDB 10.4.32. Composer resolves dependencies against PHP 8.3.0; the generated platform check requires PHP 8.3+. Actual runtime testing used PHP 8.4, not a separate PHP 8.3 installation.

## Results

- HTTP integration: **190 passed, 0 failed** against the final project location.
- Laravel PHPUnit: **7 tests, 29 assertions passed**.
- Installation and native migration: **13 checks passed**, using disposable databases. Fresh installation, repeated-install refusal, preserved product IDs/password hashes, safe seeding, and partial-schema rejection were covered.
- Browser: **30 recorded checks/observations**, no uncaught JavaScript errors or failed external resource requests. Includes login, dark mode, inline upload, product/specification publishing, public media, menu reordering, keyboard navigation, deletion and mobile menus.
- Responsive checks: no horizontal overflow on dashboard, product editor, homepage, contact or product detail at 1440, 768 and 390 pixels.
- PHP syntax: 53 application/configuration/migration/test/entry-point files passed. Blade compilation, route caching/clearing, Composer validation and installed-platform requirements passed.
- Composer reported no known security vulnerability advisories for the resolved dependencies at the time of installation.

The production package is separately built with `composer install --no-dev`, booted with Artisan, and checked by compiling Blade templates. Local environment files, test databases, sessions, uploads, logs and development dependencies are excluded. File and archive SHA-256 manifests accompany the release.

## Running the checks

Use an isolated MySQL/MariaDB server or test account. Create two empty databases: `gtl_laravel_test` for HTTP/browser checks and `gtl_laravel_phpunit_test` for PHPUnit. Configure `.env` with the correct test server credentials, `DB_DATABASE=gtl_laravel_test`, `APP_ENV=testing`, and `APP_URL=http://127.0.0.1:8013`. Generate an application key, then run `php artisan cms:install` and create a test-only administrator.

The workspace helper `tools/php.cmd` can replace `php` in all commands below. Install development dependencies with Composer before running PHPUnit; the release ZIP includes only production dependencies.

```text
php artisan test --compact
php tests/installation.php
php artisan serve --host=127.0.0.1 --port=8013
```

`phpunit.xml` selects `gtl_laravel_phpunit_test`, inherits server credentials from `.env`, and rolls back each test's database changes. The installer test creates random `gtl_install_<random>_test` databases on the configured server and removes only those it created; its account needs database creation/deletion permissions.

In another PowerShell terminal, supply the test administrator credentials without placing them in source files:

```powershell
$env:TEST_ADMIN_EMAIL='your-test-admin@example.test'
$env:TEST_ADMIN_PASSWORD=Read-Host 'Test administrator password' -MaskInput
.\tools\php.cmd tests\integration.php
node tests\browser.cjs
```

`Read-Host -MaskInput` requires PowerShell 7.1+. On Windows PowerShell 5.1, use a process environment supplied by your test runner instead. Do not use production credentials.

Browser tests require Node, Playwright and its Chromium browser. Install these only for development. `PLAYWRIGHT_PACKAGE` can point to an existing Playwright module; `TEST_BASE_URL` overrides the browser URL. The tests require a database name ending in `_test`; HTTP/browser fixture records are cleaned up at completion. Do not run the integration and browser suites concurrently against the same test database.

## Limits

Live SMTP delivery, real Turnstile credentials, production Apache/cPanel hosting and business datasets unavailable in the supplied source were not verified. The Mail framework test checks transport selection with a mock; real mailbox delivery still needs deployment testing. External hero imagery depends on its remote provider. This verification is not a load test or a full independent security audit.

Test reports and development screenshots stay under private `storage/` in the workspace and are not shipped as application data.
