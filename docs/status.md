# WorkTracker Status

Current release: **0.1.0-alpha.8.0 — Context Intelligence & Integrations**

## Completed source milestones
- alpha.1: offline foreground capture, idle handling, SQLite timeline, simultaneous manual timers, additive overlap accounting.
- alpha.2: projects, deterministic classification, Unknown Inbox, explicit Assign + Learn.
- alpha.3: transactional outbox, device-bound Sanctum Sync, DPAPI token protection, Project/Rule Pull.
- alpha.4.x: central reporting/operations, durable conflicts, public-host security, cPanel/local hardening, shared UI primitives.
- alpha.5: Customers, Activity Types, base rate card, multiplicative customer/project factors and effective-dated pricing overrides.
- alpha.6: Activity Type selection/correction in Windows, billing configuration Pull, effective-dated pricing history and invoice draft/final lifecycle.
- alpha.7: historical Activity editor + Audit Log, daily/weekly/monthly/custom reports, visual overlap timeline, responsive dashboard redesign.
- alpha.7.2: first-class Project/Customer admin UI, Project Rules, project Tasks, pricing override lifecycle/history visibility, Activity Type activation/order, dedicated Access Token UI, route-aware contextual Help, Agent reliability and Sync diagnostics.
- alpha.7.3: Work Event projection, IDE context normalization, event-level correction/learning, per-project short Continuity Bridges with mutual overlap, Project Pulse widget (full + compact + quick call), Rule pattern preview, Activity Type Intelligence, and Laravel Work Event/Bridge materialization for audit/report parity.
- alpha.8.0: first-party PhpStorm Context Bridge, foreground-PID/multi-project context selection, Project path/name enrichment, Git branch, Run/Debug/Test state, IDE metadata sync/audit and unified alpha.8 regression tooling.

## Important semantics
- Effort is additive. Legitimate overlap is never capped to wall-clock coverage.
- Billing uses additive billable Effort.
- Final invoices freeze pricing inputs via invoice items + BillingRateSnapshot.
- Draft invoices may be rebuilt; finalized invoices are immutable through application services.
- Device Tokens are bound to a Device UUID and must remain least-privilege.
- Pricing overrides are expired rather than destructively deleted so historical commercial intent remains visible.

## Current validation state
- PHP syntax and pricing/time-accounting invariant tests pass in the packaging environment.
- Blade templates are intentionally kept readable and are not aggressively minified.
- A real Windows/.NET 10 build is still the authoritative WPF compiler gate. The alpha.7.3 source explicitly aliases WPF MessageBox to avoid the WPF/WinForms ambiguity introduced by the Tray integration.

## Next
- Build/install the PhpStorm Context Bridge and validate `idle/run/debug/test` on real projects.
- Add privacy allow/deny controls and user-visible IDE integration health/history.
- Extend semantic IDE modes beyond Debug/Test (review, terminal, database, build) only where reliable IDE signals exist.
- Design the Chrome/Edge URL/domain bridge after the PhpStorm protocol is stable.
- Keep finalized Billing on raw Activity Sessions until immutable Bridge financial snapshot semantics are implemented.
- Team/organization/RBAC management remains intentionally deferred until its ownership model is specified.

## alpha.7.2 sync diagnostics hotfix
- Added structured daily Agent logs with 14-day retention and a dedicated WPF log viewer.
- Added dedicated Laravel `worktracker_sync` daily logs and an authenticated `/worktracker/diagnostics` page.
- Added end-to-end correlation IDs for device registration and sync.
- Added Outbox diagnostics for total/due/delayed/failed rows plus last error and next retry time.
- Added a safe retry action that removes only backoff delay and immediately retries queued rows.
- Sanctum tokens and Authorization headers are intentionally excluded from diagnostic logs.


## alpha.7.3 P0 Activity Intelligence core
- Initial continuity anchor reduced to 60 seconds; bridge max remains 120 seconds and per-project re-arm remains 120 seconds.
- Continuity chains are independent per Project; mutual and 3+ Project bridge overlap is explicitly valid and additive.
- Aggregation exposes Direct/Suspended/BridgeCandidate/Bridged/Closed decisions and logs bridge/close decisions for diagnosis.
- Same-Project app/title changes stay one projected Work Event while raw sessions remain auditable.
- Windows build now runs deterministic Activity Intelligence scenarios after Release compilation.
- Project Pulse widget shows four recent/active Pulse items with live credited/direct/bridge counters and overall Effort/Coverage/Concurrent.

## alpha.7.3 Laravel Work Event projection
- Added materialized `work_events`, `work_event_segments` and `continuity_bridges` tables.
- Sync rebuilds affected local dates after accepted Activity changes; historical Web edits rebuild old/new dates.
- Work Event audit UI and admin API expose Direct, Bridge, Credited, raw Segment links and interrupted Projects.
- Central reports can include derived Bridge Effort while invoice generation remains intentionally unchanged.
- Projection-day queries convert `Asia/Tehran` day boundaries to UTC before MySQL filtering.
- Health now requires the Work Event projection schema and reports the Activity Intelligence policy/version.

- Activity Type Intelligence P1: project defaults, configurable type rules, explicit IDE Debug/Test/Review signals, confidence/source/reason provenance, and Agent sync are implemented in alpha.7.3.
