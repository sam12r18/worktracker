# New Chat Brief — WorkTracker

Baseline: **WorkTracker 0.1.0-alpha.7**.

Before coding read, in order:
1. `AGENTS.md`
2. `docs/status.md`
3. `docs/handoff/current-project-map.md`
4. `docs/adr/0004-overlapping-activities-are-additive.md`
5. `docs/adr/0008-public-host-authentication-and-token-scopes.md`
6. `docs/adr/0009-billing-rate-card-and-multipliers.md`
7. `docs/adr/0010-invoice-snapshot-and-billing-sync.md`
8. `docs/adr/0011-effective-dated-rate-history.md`

Current repository paths are authoritative in `docs/handoff/current-project-map.md`. Do not assume `apps/api` is a standalone Laravel distribution; it is an integration module for a normal authenticated Laravel/Sanctum host.

Key invariants:
- Legitimate overlapping Activities are additive, including same user/device/project/time range.
- Device timelines are independent; never cross-device normalize.
- Windows remains offline-first.
- Device Token is least-privilege and cannot author Billing configuration.
- Billing uses historical rate/multiplier values effective at Activity time.
- Final invoices snapshot pricing and are immutable through application services.

Recommended next phase: **alpha.7 historical Activity correction + audit + richer weekly/monthly reporting**. Add central Activity Type/project correction without altering timestamps/duration, then concurrent-lane visualization and export refinement.
