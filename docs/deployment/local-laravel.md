# Local Laravel deployment — alpha.8.1

## Baseline
`apps/api/` یک Laravel 12 کامل است. حداقل PHP موردنیاز 8.2 است و MySQL/MariaDB پشتیبانی می‌شود. Windows Agent می‌تواند با Backend محلی یا یک Backend راه‌دور کار کند.

Laravel Sanctum 4.x وابستگی مستقیم پروژه است و با `composer install` نصب می‌شود. migration جدول `personal_access_tokens` نیز داخل خود پروژه قرار دارد؛ بنابراین برای این بسته نباید `php artisan install:api` اجرا شود.

## Host preparation
1. وارد `apps/api/` شوید و `.env` را تنظیم کنید.
2. `composer install` را اجرا کنید.
3. در نصب تازه، `php artisan key:generate` را اجرا کنید.
4. دیتابیس را ایجاد و مشخصات آن را در `.env` ثبت کنید.
5. `php artisan migrate` را اجرا کنید؛ این مرحله جدول `personal_access_tokens` را نیز می‌سازد.
6. مقادیر `WORKTRACKER_ADMIN_*` را تنظیم و در صورت نیاز `php artisan db:seed` را اجرا کنید.
7. در پایان cache/config/routes را پاک کنید و Health را بررسی کنید.
8. در production، `WORKTRACKER_REQUIRE_HTTPS=true` و `APP_DEBUG=false` باشد.

## Windows Agent local auto-start

هنگام اجرای Windows Agent، اگر آدرس Sync روی یک Backend محلی HTTP مانند `http://127.0.0.1:8082` یا `http://localhost:8082` تنظیم شده باشد، Agent ابتدا `GET /worktracker/health` را بررسی می‌کند.

- اگر Backend از قبل سالم باشد، Agent همان Process موجود را استفاده می‌کند و آن را مدیریت یا متوقف نمی‌کند.
- اگر Host قابل دسترس باشد ولی Health پاسخ موفق ندهد، Agent برای جلوگیری از اجرای Server دوم، Process دیگری ایجاد نمی‌کند و رخداد را با category `laravel.local` لاگ می‌کند.
- اگر Backend محلی در دسترس نباشد، Agent مسیر `apps/api` را پیدا کرده و `php artisan serve --host=<host> --port=<port>` را با همان Host/Port تنظیم‌شده اجرا می‌کند.
- Agent قبل از شروع Sync چند بار Health را retry می‌کند. شکست در اجرای Laravel باعث توقف خود Agent نمی‌شود؛ WorkTracker همچنان offline-first باقی می‌ماند.
- فقط Laravel Processای که خود Agent ساخته است هنگام خروج Agent متوقف می‌شود. Processای که قبلاً دستی، از PhpStorm یا ابزار دیگری اجرا شده باشد هرگز توسط Agent متوقف نمی‌شود.
- URLهای remote یا HTTPS باعث اجرای خودکار `php artisan serve` نمی‌شوند.

برای نصب‌هایی که ساختار Repository استاندارد ندارند، مسیر Laravel را می‌توان صریحاً مشخص کرد:

```powershell
$env:WORKTRACKER_LARAVEL_PATH = 'I:\worktracker\apps\api'
```

اگر `php.exe` در `PATH` نیست، executable را نیز می‌توان مشخص کرد:

```powershell
$env:WORKTRACKER_PHP_EXECUTABLE = 'C:\php\php.exe'
```

## Commands
```bash
php tools/check-server.php
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=worktracker
php artisan route:list --path=api/v1
```

No queue worker or scheduler is required by the current local WorkTracker runtime.

## Billing deployment note
Billing/invoice features still do not require a queue worker or scheduler. Excel export is dependency-free SpreadsheetML. PDF output uses the authenticated print page + browser Save as PDF, so no Dompdf package or server font installation is required.
