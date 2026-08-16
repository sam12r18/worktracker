# WorkTracker Roadmap — 0.1 to 1.0

## alpha.1 — Capture + Local Timeline — completed
Foreground/idle capture, SQLite, concurrent manual timers, System Tray and additive overlap accounting.

## alpha.2 — Projects + Classification — completed
Offline projects, weighted rules, Unknown Inbox, correction and explicit learning.

## alpha.3 — Secure Bidirectional Sync — completed source milestone
Transactional outbox, DPAPI-protected Sanctum token, device binding, Activity push, Project/Rule Pull, retry/backoff, opaque cursor and conflict quarantine.

## alpha.4 / 4.1 / 4.2 — Central Operations + Public Host Hardening — completed source milestone
Central reports, device/operator health, durable conflict resolution, authenticated RTL dashboard, scoped token-only API, HTTPS, cPanel/local deployment checks and shared UI primitives.

## alpha.5 — Billing Foundation — completed
Customers, Activity Types, base hourly rate card, customer multiplier × project multiplier, explicit rate overrides, effective dates and billing preview.

## alpha.6 — Billing Sync + Invoices — completed source milestone
- Activity Type cache pulled to Windows.
- Manual timer Activity Type and billable override.
- Project billing configuration Pull.
- Billing fields included in Activity Sync/conflict payloads.
- Monthly invoice Draft/rebuild.
- Per-Activity invoice lines using additive Effort.
- Final invoice immutable pricing snapshots.
- Excel-compatible `.xls` export without extra Composer dependency.
- Printable Persian invoice suitable for browser Save as PDF.

## alpha.7 — Historical correction + reporting polish
Activity edit/audit trail, Activity Type correction, daily/weekly/monthly UX, filters, richer concurrent lanes and export refinement.

## alpha.7.2 — Admin master data + Task web foundation — completed source milestone
Project/Customer master-data UI, Project Rule management, contextual help, Task CRUD web foundation and pricing-operations polish.

## alpha.7.3 — Activity Intelligence & Work Events — current
- Raw Activity Session vs derived Work Event separation.
- Stable IDE Context normalization to prevent one visible event per file/tab.
- Same-Project aggregation across applications without duplicate project credit.
- Event-level correction and Assign + Learn using stable workspace patterns.
- Per-project Continuity Bridge: 60s initial anchor, max 120s interruption, 120s per-project re-arm, with mutual/multi-project bridges allowed.
- Rule Builder pattern suggestion + recent-activity match preview.
- Conservative explicit Debug/Test inference; ambiguous IDE foreground stays untyped.
- P0 Agent core completed: explicit continuity decision state, mutual/multi-project bridge projection, deterministic build self-test, live Project Pulse widget and decision logging.
- Laravel Work Event Projection + Bridge Audit started: derived tables, explicit rebuild, Sync-triggered rebuild, Work Event API/UI and report parity are implemented.
- Finalized invoice/Billing parity for derived continuity credits remains intentionally pending until financial audit semantics are frozen.

## 0.2 — Browser/IDE enrichment
Chrome/Edge extension contract, privacy allow/deny rules, active tab/domain metadata, PhpStorm/VS Code/Git adapters, explicit IDE run/debug/test state and background-job sources.

## 0.3+
Team roles, richer Task planning/assignees, invoice payment lifecycle, optional AI daily narrative, installer/update channel and deeper analytics.

## 1.0 target
Stable Windows Agent + Laravel backend with reliable offline-first sync, trustworthy additive time accounting, multi-device reporting, billing/invoicing, privacy controls, production deployment documentation and update path.
