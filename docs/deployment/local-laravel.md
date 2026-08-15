# Local Laravel deployment — alpha.6 baseline

## Baseline
WorkTracker API is an integration module for a normal Laravel host. For a dedicated new host, use Laravel 13 + PHP 8.3+ and MySQL/MariaDB. The Windows Agent does not require the Laravel host to be on the same machine.

## Host preparation
1. Create a normal Laravel application and configure `.env` database/session values.
2. Install/configure Laravel Sanctum and ensure the host `User` model uses `Laravel\Sanctum\HasApiTokens`.
3. Ensure at least one authenticated User exists for the WorkTracker dashboard.
4. Copy `apps/api/app`, `apps/api/config/worktracker.php`, migrations, views and `routes/worktracker*.php` into the host application.
5. Copy `App\Providers\WorkTrackerServiceProvider` and register it in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\WorkTrackerServiceProvider::class,
];
```

6. Add WorkTracker env keys from `.env.worktracker.example`.
7. Run migrations.
8. Clear cached configuration/routes after deployment.
9. Verify `/worktracker` requires login and `/api/v1/*` rejects requests without a scoped bearer token.

## Commands
```bash
php tools/check-server.php
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=worktracker
php artisan route:list --path=api/v1
```

No queue worker or scheduler is required by alpha.4.2.

## alpha.6 billing deployment note
Run all WorkTracker migrations through `2026_08_12_000600_add_invoice_exclusion_counters.php`. Billing/invoice features still do not require a queue worker or scheduler. Excel export is dependency-free SpreadsheetML. PDF output uses the authenticated print page + browser Save as PDF, so no Dompdf package or server font installation is required.
