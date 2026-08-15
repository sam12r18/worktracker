# New Chat Brief — WorkTracker

Baseline: **WorkTracker 0.1.0-alpha.7.2**.

Before coding read, in order:
1. `AGENTS.md`
2. `docs/status.md`
3. `docs/handoff/current-project-map.md`
4. `docs/product/admin-project-customer-management.md`
5. `docs/architecture/contextual-help.md`
6. ADR 0004, 0005, 0008, 0009, 0010 and 0011.

Key invariants:
- Legitimate overlapping Activities are additive, including same user/device/project/time range.
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
