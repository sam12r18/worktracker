# ADR 0013 — Range Reporting and Visual Concurrency Timeline
Status: Accepted — 2026-08-12

Central reporting supports day/week/month/custom ranges. Every range reports Effort, Elapsed Coverage and Concurrent Effort separately. The visual timeline renders sessions as independent bars and intentionally displays overlaps rather than merging them. Grouping by project, device, source and activity type must preserve additive Effort semantics.
