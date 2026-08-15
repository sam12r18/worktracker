# WorkTracker alpha.7.2 — Admin / Help / WPF patch

This patch adds Project + Customer management (P0/P1/P2 master-data scope), project Rules/Tasks, dedicated Access Token UI, global contextual Help, pricing lifecycle improvements, Windows Rule operators, single-instance/build-lock handling, and fixes ambiguous `MessageBox` compilation in `App.xaml.cs`.

## Apply
Extract over repository root and replace matching files.

```powershell
cd I:\worktracker\apps\api
php artisan optimize:clear
php artisan route:list --path=worktracker
php artisan route:list --path=api/v1

cd I:\worktracker
.\tools\build-windows-agent.ps1
```

No new Laravel migration is required by this patch. Existing Windows SQLite databases add the new `project_rules.operator` column automatically at startup.
