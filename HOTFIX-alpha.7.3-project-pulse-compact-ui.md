# WorkTracker alpha.7.3 — Project Pulse Compact UI Hotfix

این پچ ظاهر حالت Compact ویجت Project Pulse را اصلاح می‌کند.

## تغییرات

- پس‌زمینه Window شفاف شد تا گوشه‌های `Border` واقعاً گرد نمایش داده شوند.
- `AllowsTransparency=true` برای Window فعال شد و کارت اصلی با `CornerRadius=14` نمایش داده می‌شود.
- ابعاد Compact از `238×146` به `232×142` کاهش یافت.
- Padding حالت Compact کاهش یافت.
- دکمه تغییر حالت در Compact از متن «کامل» به آیکن کوچک `↗` تغییر کرد.
- دکمه تغییر حالت و دکمه بستن در Compact به `24×24` کاهش یافتند.
- فاصله ردیف‌ها و Summary پایین کمی جمع‌تر شد.
- حالت Full همچنان متن «فشرده» را برای دکمه تغییر حالت نشان می‌دهد.

## فایل‌های تغییرکرده

- `apps/windows-agent/WorkTracker.Agent/ProjectPulseWidget.xaml`
- `apps/windows-agent/WorkTracker.Agent/ProjectPulseWidget.xaml.cs`

## نصب

فایل ZIP را روی ریشه پروژه `I:\worktracker` استخراج و Replace کنید، سپس:

```powershell
cd I:\worktracker
.\tools\build-windows-agent.ps1
```

## تست سریع

1. Agent را اجرا کنید و Project Pulse را باز کنید.
2. روی «فشرده» بزنید.
3. هر چهار گوشه باید واقعاً گرد باشند و پس‌زمینه مربع Window دیده نشود.
4. دکمه‌های `↗` و `×` باید کوچک و هم‌ارتفاع باشند.
5. `↗` باید ویجت را به حالت کامل برگرداند.
6. Drag کردن ویجت و Live Counter باید بدون تغییر کار کنند.
