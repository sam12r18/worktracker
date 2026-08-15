# WorkTracker alpha.7.2 — Dark Theme Patch

این پچ تم دارک یکپارچه را برای داشبورد Laravel و Windows Agent اعمال می‌کند.

## Web
- تم دارک برای Login و تمام صفحات مبتنی بر `layouts.worktracker`
- رنگ‌بندی navy/slate با accent آبی
- فرم‌ها، جدول‌ها، کارت‌ها، KPI، Badge، Timeline، hover و focus
- `color-scheme: dark` برای کنترل‌های native مرورگر
- نمای چاپ فاکتور عمداً روشن باقی مانده است تا چاپ/PDF خوانا باشد.

## Windows Agent
- تم مرکزی دارک در `Themes/WorkTrackerTheme.xaml`
- Window، Button، TextBox، PasswordBox، ComboBox، CheckBox، Tab، ListView/ListBox و DataGrid
- رنگ‌های hover/selected/focus و status badge

## نصب
محتویات ZIP را روی ریشه پروژه (`I:\worktracker`) Extract و Replace کنید.

برای Windows Agent سپس اجرا کنید:

```powershell
.\tools\build-windows-agent.ps1
```

برای Web چون استایل اصلی داخل Blade است، build فرانت الزامی نیست. در صورت cache بودن viewها:

```powershell
cd .\apps\api
php artisan optimize:clear
```
