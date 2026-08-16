# WorkTracker alpha.7.2 — Sync pull + complete WPF dark theme hotfix

## Sync
- Explicit `System.Text.Json` mappings for Laravel snake_case response fields.
- One-time protocol-v2 cursor reset so configuration missed by older agents is pulled again.
- Manual **بازخوانی کامل از سرور** action in the Sync tab.
- Sync status now reports accepted/pushed and pulled counts.
- Manual sync waits for an in-flight sync instead of silently returning.
- Local Project / Activity Type counts are visible in the Sync tab for diagnosis.

## WPF dark theme
- Explicit dark Window/root background.
- Full dark `ComboBox` template and popup.
- Full dark `TabControl`/`TabItem` content surface.
- Dark scrollbars and tooltips.
- Native Windows 11 caption/border/text colors are aligned with the WorkTracker palette through DWM when supported.

## Commit suggestion
رفع همگام‌سازی تنظیمات بین Laravel و Windows Agent و تکمیل تم تیره WPF.
نگاشت پاسخ‌های snake_case، بازخوانی کامل Project/Ruleها و نمایش وضعیت Sync اضافه شد؛ همچنین ComboBox، Tabها، ScrollBar و نوار عنوان ویندوز با تم تیره یکپارچه شدند.
