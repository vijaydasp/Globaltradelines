# From the native PHP CMS to Laravel 13

The user requested Laravel after the initial native PHP implementation. This release supersedes the native runtime while preserving its CMS schema, content workflows and frontend assets.

## Before switching an existing installation

1. Back up the database, the old `.env` and `storage/uploads/`.
2. Install the Laravel release separately with PHP 8.3+. Do not overwrite uploads with an empty release directory.
3. Copy `.env.example` to `.env` and enter the existing database credentials, the correct database port and the intended `APP_URL`.
4. Run `php artisan key:generate`. Laravel requires its own supported encryption key format; do not reuse the native CMS's hex key as Laravel's key.
5. Run `php artisan migrate --force`. The baseline migration verifies that all expected native CMS tables and module columns exist and an installation marker is present, then records the migration without replacing content. Partial/unrecognized schemas are rejected. Laravel adds its migration tracking table.
6. Copy the old `storage/uploads/` directory into the new `storage/app/private/uploads/`. Database paths stay in the `uploads/YYYY/MM/...` format. Built-in logo/favicon paths stay under `/assets/`.
7. Configure the web server to serve the new `public/` directory, then sign in with your existing admin email/password. Existing bcrypt password hashes are accepted. Old native sessions are not reused.
8. Verify published pages, draft previews, private/public media, enquiries and CSV workflows. Configure Laravel SMTP separately if email was enabled previously.

Do not run `migrate:fresh`, `db:wipe`, or import the initial SQL over an installed database. The baseline migration's rollback intentionally refuses to drop CMS tables. Restore your protected backup to revert an actual deployment.

## Fresh installation

Use an empty database and `php artisan cms:install`. The command runs migrations and seeds recovered template content before creating your first administrator. Seeding an existing populated CMS preserves its content. Original business datasets were unavailable; the Laravel conversion does not invent those records.

## Implementation changes

| Previous runtime | Laravel runtime |
| --- | --- |
| Custom entry-point dispatch | Named Laravel routes/controllers and middleware |
| Native PHP sessions | Laravel encrypted session cookie and session guard |
| Manual CSRF checking | Laravel request-forgery middleware and CSRF fields |
| Custom PDO connection | Laravel DB connection/query builder and Eloquent content/admin models |
| Direct PHP view inclusion | Compiled Blade views and Laravel responses |
| Filesystem response headers and exit | Laravel Storage and Symfony binary/streamed responses |
| PHP mail transport | Laravel Mail with SMTP/log configuration |
| Standalone CLI installer | Artisan commands, migrations and seeding |

The custom dashboard and public assets remain the supplied/implemented design. The CMS's typed module registry, validation rules, navigation generation, revisions and publishing logic remain application services. Drafts continue to be per record; settings and navigation save immediately.

The old native release ZIP is retained separately as a backup. It is not loaded by the Laravel application.
