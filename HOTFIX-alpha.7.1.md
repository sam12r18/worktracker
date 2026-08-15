# WorkTracker alpha.7.1 hotfix

Changed files address the first real Windows/.NET 10 build and MySQL/MariaDB fresh migration issues found during local deployment.

## Windows
- WPF `UserControl` ambiguity fixed.
- `System.Net.Http` imports fixed.
- `Microsoft.Data.Sqlite` updated to 10.0.10.
- SQLite native bundle pinned to `SQLitePCLRaw.bundle_e_sqlite3` 2.1.12.
- Redundant direct `System.Security.Cryptography.ProtectedData` reference removed.
- Build/run PowerShell scripts now fail correctly when `dotnet` returns a non-zero exit code.

Run after replacing the files:

```powershell
dotnet nuget locals all --clear
.\tools\build-windows-agent.ps1
```

## Laravel / MySQL
`activity_sessions.started_at`, `ended_at`, `created_at_device` and `updated_at_device` use `dateTimeTz` instead of MySQL `TIMESTAMP`. This avoids `Invalid default value for ended_at` on MySQL/MariaDB configurations with legacy TIMESTAMP default behavior.

If migration `2026_08_11_000300_create_activity_sessions_table` failed and the database is otherwise a new/empty WorkTracker database, simply run `php artisan migrate` again after replacing the migration. If partial schema objects were left behind, inspect them first; only use `php artisan migrate:fresh` when it is safe to erase that database.
