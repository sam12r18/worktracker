# WorkTracker 0.1.0-alpha.8.0 — Complete Package

## ساختار
- `apps/api/` — Laravel 12 کامل و مستقل (نه overlay)
- `apps/windows-agent/WorkTracker.Agent/` — برنامه WPF ویندوز با Work Event Aggregation، Activity Type Intelligence و ویجت Project Pulse
- `apps/phpstorm-plugin/` — افزونه PhpStorm برای Project/File/Run/Debug/Test/Git Context
- `tools/build-windows-agent.ps1` — Build ویندوز
- `tools/build-phpstorm-plugin.ps1` — Build افزونه PhpStorm
- `tools/test-alpha8-regression.ps1` — Regression Gate یکپارچه alpha.8
- `docs/` — مستندات پروژه

## راه‌اندازی Laravel در Windows
```powershell
cd apps/api
Copy-Item .env.example .env
# DB و WORKTRACKER_ADMIN_* را در .env تنظیم کنید
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Health مستقیم: `http://127.0.0.1:8000/worktracker/health`
Login: `http://127.0.0.1:8000/login`
Dashboard: `http://127.0.0.1:8000/worktracker`
Activity Type Intelligence: `http://127.0.0.1:8000/worktracker/activity-intelligence`

## Build ویندوز
```powershell
.\tools\build-windows-agent.ps1
```

## cPanel
کل پوشه `apps/api/` اپلیکیشن Laravel است. DocumentRoot دامنه/ساب‌دامین باید به `apps/api/public` اشاره کند. در production: `APP_DEBUG=false` و `WORKTRACKER_REQUIRE_HTTPS=true`.
