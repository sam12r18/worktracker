# WorkTracker

Offline-first Windows activity/project tracker with a Laravel backend, additive concurrent time accounting, central reporting and customer billing.

Current source: **0.1.0-alpha.7.3 — Activity Intelligence & Work Events**.

## Non-negotiable time rule
Legitimate parallel activities are additive. A 20-minute phone call and 20 minutes of coding from 10:00–10:20 may both belong to the same project and equal **40m Effort / 20m Elapsed Coverage**. Sync, reporting and billing must not normalize this.

## Repository layout
- `apps/windows-agent/WorkTracker.Agent/` — C#/.NET 10/WPF Agent, SQLite, capture, manual timers, classification and Sync.
- `apps/api/` — complete Laravel 12 backend application for WorkTracker.
- `docs/` — ADRs, architecture, deployment, testing and handoff.
- `tools/` — Windows build/run and server/invariant checks.

Verified map: `docs/handoff/current-project-map.md`.

## Current capabilities
- Foreground + idle capture, multiple simultaneous manual timers and System Tray.
- Local projects, deterministic project rules, Unknown Inbox and explicit learning.
- Derived Work Events aggregate noisy foreground segments; stable IDE Context patterns and independent per-Project Continuity Bridges support mutual/multi-project additive continuity without deleting raw data.
- Project Pulse side widget shows the three most recent Projects with live credited/direct/bridge time plus Effort/Coverage/Concurrent totals.
- Offline-first transactional outbox and device-bound Sanctum Sync.
- Central Effort/Coverage reporting, device health and durable conflict resolution.
- Public-host security with Session-authenticated dashboard and scoped token-only API.
- Customers, Activity Types, base rate card, customer × project multiplicative factors and effective-dated overrides.
- Activity Type cache in Windows and billable override on manual timers.
- Monthly Invoice Draft/rebuild, Final pricing snapshots, detailed lines, Excel-compatible export and print/save-PDF output.
- Historical Activity editor with immutable finalized-billing protection and Audit Log.
- Daily/weekly/monthly/custom reports with activity-type breakdown and visual concurrency timeline.
- Responsive Laravel navigation/dashboard refresh and extracted WPF TodaySummary component.

## Windows build
```powershell
.\tools\build-windows-agent.ps1
.\tools\run-windows-agent.ps1
```
Requires .NET 10 SDK on Windows. The packaging environment does not perform the final Windows build.

## Laravel integration
Read `apps/api/README.md`, then `docs/deployment/local-laravel.md` or `docs/deployment/cpanel.md`. For an existing alpha.6 installation use `docs/deployment/upgrade-alpha6-to-alpha7.md`.

The backend is a complete Laravel 12 application. Run `composer install`, configure `.env`, run migrations, seed the admin user if required, then serve the application. Sanctum 4.x is a direct dependency and is locked in `composer.lock`.

Production web root must point to Laravel `public/`.

Latest WorkTracker migration in this source:
`2026_08_12_030000_create_worktracker_audit_logs.php`

## Main web surfaces
- `/worktracker` — dashboard
- `/worktracker/projects` — Project / Rule / Task management
- `/worktracker/customers` — Customer management
- `/worktracker/access-tokens` — Device/Admin API tokens
- `/worktracker/activities` — historical activity editor
- `/worktracker/reports` — reports
- `/worktracker/audit` — audit trail
- `/worktracker/billing` — pricing
- `/worktracker/invoices` — invoices
- `/worktracker/conflicts` — Sync conflicts
- `/worktracker/diagnostics` — Sync diagnostics/logs

## Main API surfaces
- `POST /api/v1/devices`
- `POST /api/v1/sync`
- `GET /api/v1/reports/daily`
- `GET /api/v1/reports/projects/{project}`
- `GET /api/v1/operations/overview`
- `GET/POST /api/v1/sync-conflicts/*` according to token scope

## Start reading here
1. `AGENTS.md`
2. `docs/status.md`
3. `docs/handoff/current-project-map.md`
4. `docs/handoff/new-chat-brief.md`
5. `docs/adr/0004-overlapping-activities-are-additive.md`
6. `docs/adr/0009-billing-rate-card-and-multipliers.md`
7. `docs/adr/0010-invoice-snapshot-and-billing-sync.md`
8. `docs/adr/0011-effective-dated-rate-history.md`
9. `docs/adr/0014-work-events-context-normalization-and-continuity-bridge.md`
10. `docs/architecture/activity-intelligence.md`
