# alpha.7 History / Reports / UI Smoke Test

## Laravel
1. Login through the host Laravel application and open `/worktracker`.
2. Confirm the responsive navigation shows Dashboard, Activities, Reports, Billing, Invoices, Conflicts and Audit.
3. Open `/worktracker/activities`, select a non-finalized Activity and change its project/type/time with a correction reason.
4. Confirm duration is recalculated and `/worktracker/audit` contains before/after JSON and the reason.
5. Attempt to edit an Activity already in a finalized invoice; expect HTTP 409 / protected behavior.
6. Open `/worktracker/reports?preset=week`; verify Effort, Coverage and Concurrent are independent.
7. Use overlapping Activity records and verify the timeline renders multiple overlapping bars instead of merging them.
8. Test dashboard and tables at approximately 360px, 768px and desktop width; no page-level horizontal overflow should occur (tables may scroll inside their wrapper).

## Windows
1. Build with .NET 10 SDK.
2. Verify `TodaySummaryControl` renders current Activity, Effort, Coverage, Concurrent and Unknown/Sync values.
3. Resize down to 900×620 and verify main controls remain usable without whole-window vertical scrolling.
4. Start two simultaneous manual timers and verify summary Effort remains additive.
5. Verify Timeline, Unknown Inbox, Projects/Rules and Sync tabs still work after the summary extraction.
