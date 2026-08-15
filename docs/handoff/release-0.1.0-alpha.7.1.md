# WorkTracker 0.1.0-alpha.7.1 — Windows Build + MySQL Migration Hotfix

Fixes:
- Resolve WPF/WinForms `UserControl` ambiguity in `TodaySummaryControl`.
- Add explicit `System.Net.Http` imports for Sync/App startup.
- Change Activity event timestamps from Laravel `timestampTz` to `dateTimeTz` to avoid MySQL/MariaDB invalid implicit TIMESTAMP defaults.
- Upgrade `Microsoft.Data.Sqlite` to 10.0.10.
- Pin `SQLitePCLRaw.bundle_e_sqlite3` to 2.1.12 so the old vulnerable 2.1.11 native SQLite bundle is not selected.
- Remove redundant direct `System.Security.Cryptography.ProtectedData` reference; .NET 10 Windows target supplies it.

After replacing files, run:

```powershell
dotnet nuget locals all --clear
.\tools\build-windows-agent.ps1
```

For a failed fresh Laravel migration, drop/recreate the empty WorkTracker database (or run `php artisan migrate:fresh` only if the database contains no data you need), then run migrations again.

## Build script reliability
`tools/build-windows-agent.ps1` now checks `$LASTEXITCODE` after every `dotnet` command and stops on restore/build failure instead of printing a misleading success message.
