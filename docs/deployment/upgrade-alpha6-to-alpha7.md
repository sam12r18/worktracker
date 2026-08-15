# Upgrade alpha.6 → alpha.7

## Before deploying
1. Back up the Laravel database and the current WorkTracker integration files.
2. Keep the production web root pointed at Laravel `public/`.
3. Confirm normal Laravel login and `/worktracker` access still work before changing files.

## Copy source
Merge the new `apps/api/` files into the host Laravel application and replace the Windows Agent source if you build the desktop client locally.

## Migration order
Run:

```bash
php artisan migrate --force
```

alpha.7 includes `2026_08_12_000150_prepare_activity_billable_column_for_billing.php`. It is intentionally named before the alpha.5 Billing migration:
- on a fresh install it removes the legacy alpha.1 `is_billable` column immediately before alpha.5 re-creates the nullable Billing form;
- on an existing alpha.5/alpha.6 database where migration `000200` is already recorded, it is a no-op.

The new alpha.7 audit table is created by `2026_08_12_030000_create_worktracker_audit_logs.php`.

On cPanel without terminal access, use a one-time cron to execute `php artisan migrate --force`, verify the result, then remove that cron. Never expose a public migration URL.

## Smoke test
- `/worktracker` opens only after authentication.
- `/worktracker/activities` shows historical Activity rows.
- Edit one non-finalized Activity and verify an entry appears in `/worktracker/audit`.
- A finalized/invoiced Activity refuses direct historical editing.
- `/worktracker/reports?preset=week` loads and overlapping Activity bars remain separate.
- Windows Agent starts with the existing SQLite database; no database reset is required.

## WPF
Build on a real Windows machine with .NET 10 SDK:

```powershell
.\tools\build-windows-agent.ps1
```

alpha.7 introduces `Controls/TodaySummaryControl`. Do not ship a desktop binary until the WPF build passes.
