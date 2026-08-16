# WorkTracker alpha.7.3 — Activity Intelligence P0 / Mutual Bridges / Project Pulse

این پچ هسته P0 مربوط به Activity Intelligence را تکمیل می‌کند.

## تغییرات اصلی

- حداقل Anchor اولیه Continuity از 120 ثانیه به 60 ثانیه کاهش یافت.
- سقف هر وقفه Bridge همچنان 120 ثانیه است.
- re-arm هر پروژه بعد از Bridge همچنان 120 ثانیه کار مستقیم همان پروژه است.
- Bridge دیگر global/تک-Anchor نیست؛ هر پروژه continuity مستقل دارد و Bridge متقابل یا هم‌زمان بین 2/3+ پروژه مجاز است.
- Event Aggregator دیگر span یک پروژه را به دلیل استفاده در Bridge پروژه دیگر globally absorbed نمی‌کند.
- state/decisionهای `Direct / Suspended / BridgeCandidate / Bridged / Closed` برای تشخیص و Log اضافه شدند.
- تست deterministic Activity Intelligence بعد از Build Release اجرا می‌شود.
- TrackingEngine یک live provisional segment در حافظه ارائه می‌کند بدون اینکه داده خام زودتر از موعد در SQLite ذخیره شود.
- ویجت `Project Pulse` اضافه شد: سه پروژه اخیر، زمان اعتباری امروز، Direct، Bridge، برنامه فعلی/آخر و Effort/Coverage/Concurrent.
- ویجت به‌صورت پیش‌فرض کنار سمت راست نمایش داده می‌شود و از پنجره اصلی یا System Tray قابل بازگشایی است.
- مستندات ADR، Time Accounting، Roadmap، Smoke Test و Handoff با مدل mutual/multi-project bridge هماهنگ شدند.

## نکته حسابداری

Continuity Bridge در alpha.7.3 هنوز projection محلی Windows است. Raw Activity Sessionها منبع Sync باقی می‌مانند و Server/Billing parity برای Bridge در مرحله بعد انجام می‌شود.

## نصب

ZIP را روی ریشه پروژه (`I:\worktracker`) Extract/Replace کنید و سپس:

```powershell
.\tools\build-windows-agent.ps1
```

Build در پایان Self-test را نیز اجرا می‌کند و باید پیام PASS مربوط به Activity Intelligence را نشان دهد.
