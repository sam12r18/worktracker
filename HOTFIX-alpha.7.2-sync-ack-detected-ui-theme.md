# WorkTracker alpha.7.2 — Sync ACK + Detected Activities + WPF Dark UI hotfix

## علت مشکل Sync

لاگ‌های Agent و Laravel نشان می‌دادند سرور batchهای 200تایی `activity_session` را با HTTP 200 می‌پذیرد، اما Outbox محلی بعد از پاسخ موفق کوچک نمی‌شود. در نتیجه همان batch دوباره ارسال می‌شد.

این Hotfix تأیید موفق سرور را به **شناسه دقیق رکورد Outbox ارسال‌شده** متصل می‌کند. این روش علاوه بر رفع resend، در برابر ویرایش هم‌زمان Activity هنگام HTTP request امن‌تر است؛ اگر در زمان ارسال نسخه جدیدی از همان Activity در Outbox ساخته شود، ACK نسخه قدیمی نسخه جدید را حذف نمی‌کند.

برای batchهایی که سرور همه موارد را پذیرفته و conflict صفر است، fallback امن whole-batch نیز وجود دارد. جزئیات ACK با category `sync.ack` در Agent log ثبت می‌شود.

## Full configuration pull

«بازخوانی کامل تنظیمات» اکنون pull-only است:

- Cursor محلی reset می‌شود.
- Activityهای Outbox هم‌زمان ارسال نمی‌شوند.
- Project، Project Rule و Activity Type از ابتدا Pull می‌شوند.
- نتیجه واقعی Sync در پیام نمایش داده می‌شود.

## تب تشخیص‌داده‌شده

تب جدید `تشخیص‌داده‌شده` Activityهای امروز که پروژه دارند را نمایش می‌دهد و دو عملیات دارد:

- تغییر انتساب
- انتساب + یادگیری

در حالت یادگیری Rule جدید از اطلاعات پنجره Activity ساخته می‌شود. انتخاب ردیف هنگام refreshهای دوره‌ای حفظ می‌شود.

## بازطراحی Sync

صفحه Sync به کارت‌های مجزا برای Connection، Status، Actions و Conflicts تقسیم شده و خوانایی وضعیت Queue و تنظیمات محلی بهتر شده است.

## Dark theme

- Template مستقل برای Button اضافه شد تا VisualState روشن پیش‌فرض Windows در hover نشت نکند.
- Hover و Press تاریک‌تر و کنترل‌شده‌تر شدند.
- Primary و Danger button style اضافه/بهبود داده شد.
- CheckBox و ComboBoxItem template تیره اختصاصی دارند.
- Separatorها نیز با palette دارک هماهنگ شدند.

## تست بعد از نصب

پس از Build و اجرای Agent:

1. `Sync اکنون` را اجرا کنید.
2. در لاگ Agent دنبال `sync.ack` بگردید. مقدار `deleted` باید متناسب با `accepted` باشد؛ در سناریوی فعلی انتظار `accepted: 200` و `deleted: 200` می‌رود.
3. Queue باید بعد از هر Sync کاهش یابد.
4. برای دریافت پروژه‌های وب، `بازخوانی کامل تنظیمات` را یک بار اجرا کنید. Log شروع آن باید `pull_only: true` و `has_cursor: false` داشته باشد و `remote_changes` باید تعداد configurationهای دریافت‌شده را نشان دهد.
