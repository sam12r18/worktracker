# Local Laravel deployment — alpha.7.2 baseline

## Baseline
`apps/api/` در بسته alpha.7.2 یک Laravel 12 کامل است. حداقل PHP موردنیاز 8.2 است و MySQL/MariaDB پشتیبانی می‌شود. Windows Agent لازم نیست روی همان سیستم Backend اجرا شود.

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
