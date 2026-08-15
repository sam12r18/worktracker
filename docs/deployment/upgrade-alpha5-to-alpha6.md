# Upgrade — WorkTracker alpha.5 → alpha.6

Verified source paths for this release:
- Windows Agent: `apps/windows-agent/WorkTracker.Agent/`
- Laravel integration module: `apps/api/`
- Current project map: `docs/handoff/current-project-map.md`

## Before upgrading
1. Back up the Laravel database and the deployed WorkTracker module files.
2. Do not delete the Windows local database. Alpha.6 upgrades its SQLite schema additively.
3. Confirm PHP/extensions with `php tools/check-server.php` in an environment that has the target PHP CLI.
4. Ensure the public domain/subdomain document root points to the host Laravel application's `public/` directory.

## Laravel files
Copy/merge the contents of `apps/api/` into the existing authenticated Laravel host application using the same relative paths. The host app must already provide the framework/vendor tree, a User model, login/session auth and Laravel Sanctum.

`App\Providers\WorkTrackerServiceProvider` remains the WorkTracker route loader and must be registered in `bootstrap/providers.php`.

## Database migrations
Run the WorkTracker/Laravel migrations after deploying the new files:

```bash
php artisan migrate --force
```

Alpha.6 adds the billing/invoice and pricing-history migrations through:

`database/migrations/2026_08_12_000600_add_invoice_exclusion_counters.php`

The pricing history records preserve effective activity rates, customer multiplier/currency, and project customer assignment/multiplier/billable default. This is required so an old month's draft invoice does not silently change when today's pricing configuration changes.

### cPanel without Terminal/SSH
Do not add a public web route that executes migrations. If the host has no Terminal/SSH, use a temporary cPanel Cron entry to run the exact CLI command with the account PHP binary, for example:

```bash
cd /home/ACCOUNT/path-to-laravel && /usr/local/bin/php artisan migrate --force
```

Run it once, inspect the Laravel log/output, then remove the Cron entry. The exact PHP binary varies by cPanel host; use the same PHP major/minor configured for the site.

## Windows Agent
Build the current source with .NET 10 SDK:

```powershell
.\tools\build-windows-agent.ps1
```

The local SQLite database is retained. On startup `LocalDatabase.InitializeAsync()` adds missing billing columns idempotently and creates the Activity Type cache table.

After connection, trigger Sync once. Activity Types and server-authoritative Project billing metadata are pulled to the Agent. Device tokens cannot author customer/rate/multiplier configuration.

## Post-upgrade smoke checks
1. Login to `/worktracker` — unauthenticated access must redirect/refuse.
2. Open `/worktracker/billing` and create or inspect an Activity Type.
3. Assign a customer and multiplier to a project with an effective date.
4. In Windows, Sync and confirm Activity Types appear in the Manual Timer selector.
5. Record/correct an Activity Type and Sync it.
6. Build a draft under `/worktracker/invoices`.
7. Confirm untyped/non-billable exclusions are visible.
8. Verify rate detail: base × customer multiplier × project multiplier.
9. Finalize only after checking lines; a finalized invoice must no longer be recalculated by later pricing changes.
10. Test Excel download and Print/Save as PDF.

## No mandatory worker
Alpha.6 still has no mandatory always-on queue worker or scheduler. Device Sync is initiated by the Windows Agent. Queue/Cron may be introduced only for future optional jobs.
