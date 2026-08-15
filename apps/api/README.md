# WorkTracker Server — Complete Laravel Host

این پوشه یک Laravel 12 کامل برای WorkTracker است و به‌صورت مستقل قابل اجراست؛ دیگر overlay نیست.

## Local
1. `.env.example` را به `.env` کپی و DB را تنظیم کنید.
2. `composer install` (Sanctum 4.x از `composer.lock` نصب می‌شود)
3. `php artisan key:generate`
4. `php artisan migrate`
5. ایمیل/رمز `WORKTRACKER_ADMIN_*` را در `.env` تنظیم و `php artisan db:seed` اجرا کنید.
6. `php artisan serve`
7. Login: `http://127.0.0.1:8000/login`
8. Health: `http://127.0.0.1:8000/worktracker/health`

API WorkTracker زیر `/api/v1/*` و با Sanctum token محافظت می‌شود.
در production مقدار `WORKTRACKER_REQUIRE_HTTPS=true` و `APP_DEBUG=false` باشد.
DocumentRoot هاست باید به `apps/api/public` (یا مسیر متناظر همین Laravel root روی سرور) اشاره کند.
