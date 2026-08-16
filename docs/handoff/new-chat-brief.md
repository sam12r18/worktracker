# New Chat Brief — WorkTracker

Baseline: **WorkTracker 0.1.0-alpha.7.3**.

Before coding read, in order:
1. `AGENTS.md`
2. `docs/status.md`
3. `docs/handoff/current-project-map.md`
4. `docs/product/admin-project-customer-management.md`
5. `docs/architecture/contextual-help.md`
6. ADR 0004, 0005, 0008, 0009, 0010, 0011 and 0014.

Key invariants:
- Legitimate overlapping Activities are additive, including same user/device/project/time range.
- Raw Activity Sessions are source records; Work Events are derived projections. A short Continuity Bridge may add bounded Project credit without deleting interruption time. Bridges are evaluated independently per Project, so mutual and multi-project overlap is valid; defaults are 60s initial anchor, 120s max interruption and 120s per-project re-arm.
- Device timelines are independent; never cross-device normalize.
- Windows remains offline-first and a Windows user may run only one WorkTracker Agent instance at a time.
- Device Token is least-privilege, bound to its Device UUID and cannot author Billing configuration.
- Project Rule type/operator semantics must match on Laravel and Windows. Canonical rule types are `Path`, `WindowTitle`, `ProcessName`, `ExecutablePath`, `Keyword`; operators are `contains`, `equals`, `starts_with`, `ends_with`, `regex`.
- Billing uses historical rate/multiplier values effective at Activity time.
- Final invoices snapshot pricing and are immutable through application services.
- Laravel WorkTracker pages use the shared contextual Help system; do not duplicate modal implementations in individual pages.
- Do not aggressively minify Blade source. Keep directives/components on structurally safe readable lines.

Current admin surfaces include Projects, Customers, Project Rules, project Tasks, Billing/rate history/overrides, API & Token, Devices, Activities, Reports, Audit, Invoices and Conflicts.

Immediate smoke test: migrate/clear cache, open `/worktracker/projects`, `/worktracker/customers`, `/worktracker/access-tokens`, build the Windows Agent, create a Device Token from the Agent UUID, then verify register + sync.
