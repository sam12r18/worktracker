# New Chat Brief — WorkTracker

Baseline: **WorkTracker 0.1.0-alpha.8.0**.

Before coding read, in order:
1. `AGENTS.md`
2. `docs/status.md`
3. `docs/handoff/current-project-map.md`
4. `docs/product/admin-project-customer-management.md`
5. `docs/architecture/contextual-help.md`
6. ADR 0004, 0005, 0008, 0009, 0010, 0011, 0014 and 0015.

Key invariants:
- Legitimate overlapping Activities are additive, including same user/device/project/time range.
- Raw Activity Sessions are source records; Work Events are derived projections. A short Continuity Bridge may add bounded Project credit without deleting interruption time. Bridges are evaluated independently per Project, so mutual and multi-project overlap is valid; defaults are 60s initial anchor, 120s max interruption and 120s per-project re-arm.
- Device timelines are independent; never cross-device normalize.
- Windows remains offline-first and a Windows user may run only one WorkTracker Agent instance at a time.
- Device Token is least-privilege, bound to its Device UUID and cannot author Billing configuration.
- Project Rule type/operator semantics must match on Laravel and Windows. Canonical rule types are `Path`, `WindowTitle`, `ProcessName`, `ExecutablePath`, `Keyword`; operators are `contains`, `equals`, `starts_with`, `ends_with`, `regex`.
- Project classification and Activity Type classification are separate. Activity Type precedence is explicit IDE signal → Activity Type Rule → Project default → Unknown; manual correction is `user_override` with confidence 1.0.
- Billing uses historical rate/multiplier values effective at Activity time.
- Final invoices snapshot pricing and are immutable through application services.
- Laravel WorkTracker pages use the shared contextual Help system; do not duplicate modal implementations in individual pages.
- Do not aggressively minify Blade source. Keep directives/components on structurally safe readable lines.

Current admin surfaces include Projects, Customers, Project Rules, Activity Type Intelligence, project Tasks, Billing/rate history/overrides, API & Token, Devices, Activities, Reports, Work Events, Audit, Invoices and Conflicts.

Immediate smoke test: migrate/clear cache, build the Windows Agent, build/install the PhpStorm Context Bridge, verify `PhpStorm Context` becomes connected in the Agent Sync tab, then create a new IDE Activity and verify register + sync + `/worktracker/work-events` IDE metadata.


Alpha.8.0 adds a first-party PhpStorm Context Bridge under `apps/phpstorm-plugin`. The plugin publishes local protocol-v1 project/file/Git/run/debug/test metadata; the WPF Agent owns classification, persistence and authenticated Sync.
