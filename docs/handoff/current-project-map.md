# Current Project Map — WorkTracker 0.1.0-alpha.7.3

Verified against the alpha.7.3 source tree on 2026-08-16.

## Repository root
- `AGENTS.md` — mandatory invariants and development discipline.
- `README.md` — entry/build/deployment summary.
- `tools/` — Windows build/run, server preflight and invariant checks.
- `docs/` — ADRs, architecture, product, deployment, testing and handoff.
- `apps/windows-agent/WorkTracker.Agent/` — WPF/.NET 10 Windows Agent.
- `apps/api/` — Laravel WorkTracker application module/current local web backend.

## Windows Agent
Important areas:
- `App.xaml.cs` — startup, single-instance mutex and explicit WPF `MessageBox` alias.
- `Tracking/` — foreground + idle capture plus application Context normalization.
- `Classification/ProjectResolver.cs` — deterministic Rule matching including contains/equals/starts_with/ends_with/regex.
- `Classification/ActivityTypeInferenceService.cs` + `ActivityTypeResolver.cs` — conservative Activity Type resolution from explicit IDE signals, server-managed Rules and Project defaults with provenance/confidence.
- `Services/WorkEventAggregationService.cs` — derived Work Events, same-Project aggregation, per-project continuity state decisions and mutual/multi-project bridge projection.
- `Storage/` — SQLite schema/repositories and additive upgrade columns; includes pulled `activity_type_rules`, Project default Activity Type and Activity Type decision provenance.
- `Sync/` — outbox, Sanctum API client, Project/Rule/Billing Pull and conflict handling.
- `MainWindow.xaml(.cs)` — Work Event timeline/correction, Sync diagnostics/settings and Device UUID copy action.
- `Themes/WorkTrackerTheme.xaml` — shared dark WPF theme.

## Laravel web/admin
Important controllers:
- `WorkTracker/ProjectManagementController.php`
- `WorkTracker/CustomerManagementController.php`
- `WorkTracker/ProjectRuleManagementController.php`
- `WorkTracker/TaskManagementController.php`
- `WorkTracker/BillingController.php`
- `WorkTracker/ActivityIntelligenceController.php`
- `Web/WorkTrackerAccessTokenController.php`

Important shared UI:
- `resources/views/layouts/worktracker.blade.php` — dark layout + one global Help modal host.
- `resources/views/components/worktracker/help.blade.php` — reusable field/section `!` help trigger.
- `resources/views/components/worktracker/context-help.blade.php` — route-aware floating page help.
- `config/worktracker-help.php` — central page-help registry.

Authenticated web routes include:
- `/worktracker`
- `/worktracker/projects`
- `/worktracker/customers`
- `/worktracker/access-tokens`
- `/worktracker/billing`
- `/worktracker/activity-intelligence`
- `/worktracker/activities`
- `/worktracker/reports`
- `/worktracker/audit`
- `/worktracker/invoices`
- `/worktracker/conflicts`
- `/worktracker/diagnostics`

## API
Device bootstrap/sync:
- `POST /api/v1/devices`
- `POST /api/v1/sync`

Admin API additionally exposes Projects, Customers, project Tasks, Devices, Activities, Activity Type Rules, Reports, Operations and Sync Conflicts, split by `admin:read` / `admin:write` abilities.

## Token flow
1. Agent creates/persists Device UUID locally.
2. User copies UUID from Agent Sync tab.
3. Web `/worktracker/access-tokens` creates a Device Token with `device:register`, `device:sync`, `device:<uuid>`.
4. Plain token is shown once and saved in Agent; Windows protects it via DPAPI.
5. Agent registers then syncs using Bearer authentication.
6. Revoking the token immediately prevents further authenticated sync.

## Documentation audit
- `docs/review/alpha7.3-source-documentation-audit.md` — source ↔ documentation alignment and deliberate open gaps.

## Scope boundary
Team/organization/RBAC administration is not part of alpha.7.3. Do not bolt it onto Customer/Project ownership without a dedicated ownership/security design.

- `ProjectPulseWidget.xaml(.cs)` — compact always-on-top view of the three latest Projects with live credited/direct/bridge and global Effort/Coverage/Concurrent counters.
