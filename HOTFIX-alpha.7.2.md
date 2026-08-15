# WorkTracker 0.1.0-alpha.7.2 — Sanctum / Backend Packaging Hotfix

## Fixed
- Added `laravel/sanctum:^4.3` as a direct production dependency.
- Locked Sanctum 4.3.3 in `composer.lock`.
- Kept the bundled `personal_access_tokens` migration, which matches the Sanctum 4.x schema.
- Corrected server preflight from the stale Laravel 13 / PHP 8.3 baseline to Laravel 12 / PHP 8.2+.
- Corrected deployment docs that still described `apps/api` as an overlay or used the nonexistent `server/` path.

## Apply on the current Windows installation
```powershell
cd I:\worktracker\apps\api
composer install
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=api/v1
```

Then open:
`http://127.0.0.1:8082/worktracker/health`

Expected critical checks:
- `database.status = ok`
- `schema.status = ok` with `missing = []`
- `sanctum.status = ok`
- overall `status = ok`

Do **not** run `php artisan install:api` for this package. The WorkTracker API routes and the personal-access-token migration are already bundled.
