# Current Project Map — WorkTracker 0.1.0-alpha.7

Verified against the packaged source tree on 2026-08-12.

## Repository root
- `AGENTS.md` — mandatory development invariants.
- `README.md` — entry point and build/deployment summary.
- `MANIFEST.txt` — generated file inventory.
- `tools/` — build, run, server preflight and invariant checks.
- `docs/` — ADRs, architecture, deployment, testing, roadmap, handoff.
- `apps/windows-agent/WorkTracker.Agent/` — Windows WPF/.NET 10 agent.
- `apps/api/` — Laravel integration module (not a standalone Laravel framework distribution).

## Windows Agent
Path: `apps/windows-agent/WorkTracker.Agent/`

Important areas:
- `Tracking/` foreground + idle capture.
- `Classification/` deterministic project resolution.
- `Storage/` SQLite schema/repositories and additive upgrade migrations.
- `Sync/` outbox, Sanctum API client, configuration Pull and conflict resolution.
- `Services/ManualTimerService.cs` concurrent manual activities.
- `Storage/ActivityTypeRepository.cs` offline cache of server Activity Types.
- `Themes/WorkTrackerTheme.xaml` shared desktop styles.
- `Controls/TodaySummaryControl.xaml(.cs)` extracted summary component.
- `MainWindow.xaml(.cs)` current shell; Manual Timer, Timeline and Sync are the next extraction targets after a real .NET build.

## Laravel integration module
Path: `apps/api/`

It must be merged/copied into a normal Laravel application that already has User authentication and Laravel Sanctum. `WorkTrackerServiceProvider` owns WorkTracker route loading.

Important areas:
- `app/Http/Controllers/Api/V1/SyncController.php` device sync.
- `app/Http/Controllers/WorkTracker/BillingController.php` customers/rate card/multipliers.
- `app/Http/Controllers/WorkTracker/InvoiceController.php` invoice lifecycle and exports.
- `app/Http/Controllers/WorkTracker/ActivityHistoryController.php` historical corrections + Audit.
- `app/Http/Controllers/WorkTracker/WorkReportController.php` daily/weekly/monthly/custom reports.
- `app/Services/PricingService.php` effective rate resolution.
- `app/Services/InvoiceService.php` draft/final snapshot lifecycle.
- `resources/views/worktracker/billing/` pricing UI.
- `resources/views/worktracker/invoices/` invoice UI and print/PDF surface.
- `database/migrations/2026_08_12_000100...000600` Billing, effective-dated pricing/customer-assignment history and Invoice schema.
- `database/migrations/2026_08_12_000150_prepare_activity_billable_column_for_billing.php` fresh-install compatibility shim.
- `database/migrations/2026_08_12_030000_create_worktracker_audit_logs.php` historical-edit Audit schema.

## Route surfaces
Authenticated web:
- `/worktracker`
- `/worktracker/activities`
- `/worktracker/reports`
- `/worktracker/audit`
- `/worktracker/billing`
- `/worktracker/invoices`
- `/worktracker/conflicts`

Token-only API:
- `/api/v1/devices`
- `/api/v1/sync`
- `/api/v1/reports/*`
- `/api/v1/operations/*`
- `/api/v1/sync-conflicts/*`

## Deployment truth
The source package is an integration repository, not a one-command Laravel application. On local/cPanel the host Laravel app must supply framework/vendor files, authentication, Sanctum, database configuration and a web root pointed to Laravel `public/`.

Do not relocate WorkTracker code into arbitrary paths without updating this map, deployment docs, `AGENTS.md`, and the ServiceProvider.

## Upgrade guide
Existing alpha.6 deployments must follow `docs/deployment/upgrade-alpha6-to-alpha7.md`.
