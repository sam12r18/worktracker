# WorkTracker alpha.7.3 — Pagination & Activity Edit Modal

## Changes

- Added server-side pagination to `/worktracker/work-events`.
- Added server-side pagination to `/worktracker/activities`.
- Added 25/50/100/200 page-size selector while preserving current filters in pagination links.
- Work Event metric cards still summarize the complete filtered day/result set, not only the visible page.
- Replaced inline `<details>` activity editor with one reusable dark modal.
- The activity edit modal preserves project, activity type, billing mode, time range, note and correction reason.
- Validation failures reopen the same activity modal with the submitted values.
- Historical edit still records Audit Log and rebuilds affected Work Event projection dates.

## Database

No migration is required.

## Apply

Extract the patch over the repository root and run:

```powershell
cd I:\worktracker\apps\api
php artisan optimize:clear
```
