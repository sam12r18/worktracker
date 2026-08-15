# cPanel deployment — alpha.7.2 baseline

## Non-negotiable layout
The web domain/subdomain document root must point to Laravel's `public/` directory. Do not expose the Laravel project root, `.env`, `vendor`, `storage`, or `bootstrap/cache` directly through the public web root.

Recommended layout when cPanel allows a custom document root:

```text
/home/ACCOUNT/worktracker/apps/api/        # Laravel root, not public
/home/ACCOUNT/worktracker/apps/api/public # subdomain document root
```

If the hosting plan does not allow the document root to target `public/`, do not move Laravel's `index.php` into the application root. Use a subdomain/addon-domain configuration that can target `public/`, or ask the host to configure it.

## Requirements
- PHP 8.2+ for the Laravel 12 backend baseline.
- Required Laravel PHP extensions plus `pdo_mysql`.
- MySQL/MariaDB using InnoDB for WorkTracker foreign keys.
- HTTPS certificate enabled.
- Writable `storage/` and `bootstrap/cache/`.
- Laravel Session Auth plus Sanctum 4.x (installed by Composer) and the `personal_access_tokens` migration.

Upload `tools/check-server.php` temporarily outside the public directory and run it from cPanel Terminal when available. Delete temporary diagnostic copies after use.

## cPanel without Terminal
Build the Laravel application locally, run `composer install --no-dev --optimize-autoloader` locally for the target PHP/platform compatibility, then upload the complete application including `vendor/`. Create/import the database using cPanel/phpMyAdmin. Do not upload a development `.env`; create the production `.env` on the host.

Migrations are strongly preferred. If Terminal is unavailable, run migrations before generating a production database dump, then import that dump into cPanel. Do not expose an HTTP route that executes `artisan migrate`.

## Production `.env` essentials
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tracker.example.com
SESSION_SECURE_COOKIE=true

WORKTRACKER_ADMIN_EMAILS=you@example.com
WORKTRACKER_ALLOW_ANY_AUTHENTICATED_USER=false
WORKTRACKER_REQUIRE_HTTPS=true
WORKTRACKER_DEVICE_TOKEN_DAYS=90
WORKTRACKER_ADMIN_TOKEN_DAYS=30
```

Also configure the normal `DB_*`, cache and session settings required by the WorkTracker Laravel application.

## Reverse proxy / Cloudflare
When HTTPS terminates before Laravel, configure Laravel trusted proxies correctly. Do not disable `WORKTRACKER_REQUIRE_HTTPS` merely to hide an incorrect proxy configuration.

## Cron / queue
alpha.4.2 does not require a queue worker or Laravel scheduler. The Windows Agent initiates sync itself. Future releases that add scheduled report generation must document their cPanel Cron requirements explicitly before they are considered deployable.

## Post-deployment checks
- `/worktracker` is not available to anonymous visitors.
- `/api/v1/reports/daily` returns 401 without a real Sanctum bearer token.
- A device token cannot call admin-report or admin-write routes.
- A token bound to Device A cannot sync Device B.
- HTTP is redirected/blocked and HTTPS succeeds.
- `storage/logs/laravel.log` remains writable and `APP_DEBUG=false` prevents stack traces from leaking publicly.

## Billing deployment note
Run all WorkTracker migrations through `2026_08_12_000600_add_invoice_exclusion_counters.php`. Billing/invoice features still do not require a queue worker or scheduler. Excel export is dependency-free SpreadsheetML. PDF output uses the authenticated print page + browser Save as PDF, so no Dompdf package or server font installation is required.
