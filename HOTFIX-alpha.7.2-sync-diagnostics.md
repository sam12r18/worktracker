# WorkTracker alpha.7.2 — Sync Diagnostics Hotfix

این پچ برای عیب‌یابی Sync بین Windows Agent و Laravel اضافه شده است.

## Agent
- لاگ JSONL روزانه در `%LOCALAPPDATA%\WorkTracker\logs\agent-YYYY-MM-DD.log`
- نگهداری ۱۴ روزه
- تب «لاگ‌ها» با بروزرسانی، کپی، باز کردن پوشه و پاک‌سازی
- Correlation ID مشترک برای Device Register و Sync
- نمایش جزئیات صف: Total / Due / Delayed / Failed / Max Attempts / Next Retry / Last Error
- دکمه «تلاش مجدد صف» که فقط backoff را صفر می‌کند و داده‌ای حذف نمی‌کند
- ثبت خطای Apply کردن Project/Rule/Activity Type در SQLite با Entity ID
- Token و Authorization در لاگ نوشته نمی‌شوند

## Laravel
- کانال daily مستقل `worktracker_sync`
- فایل `storage/logs/worktracker-sync-YYYY-MM-DD.log`
- ثبت شروع/پایان/Validation Error/Exception برای Sync
- ثبت Device registration
- صفحه `/worktracker/diagnostics` برای مشاهده وضعیت Device و آخرین لاگ‌های Sync
- Correlation ID مشترک با Agent

## نصب
1. پچ را روی ریشه پروژه Replace کنید.
2. در Backend اجرا شود:
   `php artisan optimize:clear`
3. Windows Agent مجدداً build شود:
   `.\tools\build-windows-agent.ps1`
4. در Agent ابتدا «Sync اکنون» و سپس تب «لاگ‌ها» بررسی شود.
5. اگر صف Delayed بود، یک بار «تلاش مجدد صف» اجرا شود.

Migration جدیدی ندارد.
