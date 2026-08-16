# WorkTracker Status

Current release: **0.1.0-alpha.7.3 — Activity Intelligence & Work Events**

## Completed source milestones
- alpha.1: offline foreground capture, idle handling, SQLite timeline, simultaneous manual timers, additive overlap accounting.
- alpha.2: projects, deterministic classification, Unknown Inbox, explicit Assign + Learn.
- alpha.3: transactional outbox, device-bound Sanctum Sync, DPAPI token protection, Project/Rule Pull.
- alpha.4.x: central reporting/operations, durable conflicts, public-host security, cPanel/local hardening, shared UI primitives.
- alpha.5: Customers, Activity Types, base rate card, multiplicative customer/project factors and effective-dated pricing overrides.
- alpha.6: Activity Type selection/correction in Windows, billing configuration Pull, effective-dated pricing history and invoice draft/final lifecycle.
- alpha.7: historical Activity editor + Audit Log, daily/weekly/monthly/custom reports, visual overlap timeline, responsive dashboard redesign.
- alpha.7.2: first-class Project/Customer admin UI, Project Rules, project Tasks, pricing override lifecycle/history visibility, Activity Type activation/order, dedicated Access Token UI, route-aware contextual Help, Agent reliability and Sync diagnostics.
- alpha.7.3: Work Event projection, IDE context normalization, event-level correction/learning, bounded short Continuity Bridge, Rule pattern preview and conservative explicit Debug/Test inference.

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
- Real Windows build + alpha.7.3 Activity Intelligence smoke test.
- Server-side Work Event/Continuity Bridge projection for reports and billing audit parity.
- Browser/IDE enrichment after Project Rule behavior is validated against real foreground windows.
- Work-report export/presets and richer Task workflows.
- Team/organization/RBAC management remains intentionally deferred until its ownership model is specified.

## alpha.7.2 sync diagnostics hotfix
- Added structured daily Agent logs with 14-day retention and a dedicated WPF log viewer.
- Added dedicated Laravel `worktracker_sync` daily logs and an authenticated `/worktracker/diagnostics` page.
- Added end-to-end correlation IDs for device registration and sync.
- Added Outbox diagnostics for total/due/delayed/failed rows plus last error and next retry time.
- Added a safe retry action that removes only backoff delay and immediately retries queued rows.
- Sanctum tokens and Authorization headers are intentionally excluded from diagnostic logs.
