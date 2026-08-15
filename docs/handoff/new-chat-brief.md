# New Chat Brief — WorkTracker

Baseline: **WorkTracker 0.1.0-alpha.7.2**.

Before coding read, in order:
1. `AGENTS.md`
2. `docs/status.md`
3. `docs/handoff/current-project-map.md`
4. `docs/adr/0004-overlapping-activities-are-additive.md`
5. `docs/adr/0008-public-host-authentication-and-token-scopes.md`
6. `docs/adr/0009-billing-rate-card-and-multipliers.md`
7. `docs/adr/0010-invoice-snapshot-and-billing-sync.md`
8. `docs/adr/0011-effective-dated-rate-history.md`

Current repository paths are authoritative in `docs/handoff/current-project-map.md`. `apps/api` is the complete Laravel 12 backend and includes login/session auth plus the direct Sanctum 4.x dependency.

Key invariants:
- Legitimate overlapping Activities are additive, including same user/device/project/time range.
- Device timelines are independent; never cross-device normalize.
- Windows remains offline-first.
- Device Token is least-privilege and cannot author Billing configuration.
- Billing uses historical rate/multiplier values effective at Activity time.
- Final invoices snapshot pricing and are immutable through application services.

Recommended next phase: continue post-alpha.7.2 stabilization: end-to-end Backend/Agent sync validation, then Windows Manual Timer/Timeline/Sync component extraction and report export refinement.
