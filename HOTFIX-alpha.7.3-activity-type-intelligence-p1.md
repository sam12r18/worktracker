# WorkTracker alpha.7.3 — Activity Type Intelligence P1

این پچ لایه تشخیص «نوع فعالیت» را از تشخیص پروژه جدا و قابل تنظیم می‌کند.

## رفتار تصمیم‌گیری

1. سیگنال صریح IDE برای Debugging / Testing / Review
2. Activity Type Ruleهای مدیریتی (سراسری یا محدود به پروژه)
3. نوع فعالیت پیش‌فرض پروژه
4. Unknown؛ سیستم در نبود شواهد کافی حدس نمی‌زند

در Priority یکسان، Rule محدود به پروژه از Rule سراسری خاص‌تر است. Near-tie در Scope و Priority یکسان Unknown می‌ماند.

هر تشخیص خودکار `activity_type_confidence`، `activity_type_source` و `activity_type_reason` دارد. اصلاح دستی با `user_override` و Confidence=1 ثبت می‌شود.

## نصب

```powershell
cd I:\worktracker\apps\api
php artisan migrate
php artisan optimize:clear
php artisan route:list --path=activity-intelligence

cd I:\worktracker
.\tools\build-windows-agent.ps1
```

Build ویندوز Self-test قطعی Activity Intelligence را نیز اجرا می‌کند.

## تست اصلی

در پنل Billing مطمئن شوید Activity Typeهای Development، Debugging و Testing موردنیاز شما وجود دارند. سپس در Project، Development را به‌عنوان Default Activity Type انتخاب و Sync کنید. عنوان عادی PhpStorm باید از `project_default` Development بگیرد؛ `Debugger` یا `PHPUnit Test Runner` باید در صورت وجود taxonomy مناسب با منبع `ide_signal` آن را override کند. فایلی مثل `DebugService.php` نباید به‌تنهایی Debugging شود.

صفحه مدیریتی:

`/worktracker/activity-intelligence`
