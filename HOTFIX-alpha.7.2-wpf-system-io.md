# WorkTracker alpha.7.2 — WPF System.IO build hotfix

## مشکل
کد Diagnostics جدید از Path، Directory، File و IOException و MainWindow از Directory استفاده می‌کرد، اما System.IO به‌صورت صریح import نشده بود. پروژه موقت WPF در مرحله markup compilation این نام‌ها را resolve نمی‌کرد.

## اصلاح
- افزودن `using System.IO;` به `Diagnostics/AgentLog.cs`
- افزودن `using System.IO;` به `MainWindow.xaml.cs`

هیچ Migration یا تغییر تنظیمات Backend لازم نیست.
