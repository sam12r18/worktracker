# WorkTracker alpha.7.3 — Laravel Work Event Projection + Compact Pulse + Blade Fix

## Included

- Fixes Project Rule Builder Blade compilation error caused by complex `@json(...)` expression in `projects/show.blade.php`.
- Adds materialized Laravel Work Event projection and explicit Continuity Bridge audit tables.
- Rebuilds affected projection dates after accepted Sync Activity changes and historical Web corrections.
- Adds `/worktracker/work-events` audit UI and Admin API read/rebuild endpoints.
- Adds Direct / Bridge / Credited metrics to central reports while leaving finalized invoice generation on raw Activity Sessions.
- Makes projection-day database queries UTC-safe while keeping the reporting day in `Asia/Tehran`.
- Extends Health checks with projection schema/policy status.
- Adds a compact Project Pulse widget mode.

## Upgrade

```powershell
cd I:\worktracker\apps\api
php artisan migrate
php artisan optimize:clear
```

Then build the Agent:

```powershell
cd I:\worktracker
.\tools\build-windows-agent.ps1
```

Open:

- `http://127.0.0.1:8082/worktracker/health`
- `http://127.0.0.1:8082/worktracker/work-events`

For historical/current-day data already stored before this migration, use **بازسازی مجدد** once on the Work Events page for the selected date.
