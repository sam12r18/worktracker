# WorkTracker 0.1.0-alpha.7.3 — Activity Intelligence & Work Events

## Windows Agent

- Added Work Event projection over raw Activity Sessions.
- Same-Project foreground segments are aggregated even when application/title changes.
- Unknown IDE sessions use normalized workspace Context so file switching does not flood the visible inbox.
- Added per-project bounded Continuity Bridge with a 60-second initial anchor, 120-second maximum interruption and 120-second per-project re-arm. Mutual and multi-project bridges are intentionally valid and additive.
- Local Effort includes derived bridge credit while Coverage remains wall-clock union.
- Added the always-on-top Project Pulse widget with live counters for the three most recent Projects.
- Event-level Project correction and Activity Type assignment update all represented raw sessions.
- Assign + Learn uses stable IDE workspace patterns.
- Added conservative explicit Debug/Test Activity Type inference; ambiguous IDE foreground remains untyped.

## Web / Laravel

- Project Rule form upgraded to a Pattern-oriented Rule Builder.
- Stable pattern suggestion from a sample window title.
- 7-day recent-context preview shows matches in the current Project, other Projects and unknown activity.
- Added server Work Event materialization with `work_events`, `work_event_segments` and `continuity_bridges`.
- Added Work Event audit page and read API plus explicit rebuild action.
- Accepted Sync Activity changes rebuild affected local dates; historical web corrections rebuild stale old/new dates.
- Central reports now expose Raw/Direct, Bridge, Credited Effort and Work Event counts.
- Project Pulse widget now supports a compact layout.

## Documentation

- ADR 0014 defines Raw Session vs Work Event vs Continuity Bridge.
- Time-accounting and classification ADRs updated.
- Added Activity Intelligence architecture document and alpha.7.3 smoke test.

## Important boundary

Continuity Bridge is derived on both Windows and Laravel, while raw sessions remain the sync source of truth. Server reports may show the derived credit and the dedicated Bridge audit rows. Final invoice calculation still uses raw Activity Sessions; Bridge billing remains deliberately disabled until immutable financial snapshot/parity tests are completed.

## P1 — Activity Type Intelligence

- Added a Project-level default Activity Type as a conservative fallback.
- Added server-managed global and Project-scoped Activity Type Rules and sync to Windows Agent.
- Added separate Activity Type decision provenance (`confidence`, `source`, `reason`) on raw Activity Sessions.
- Added conservative explicit IDE signals for Debugging, Testing and Code Review. Generic filenames containing words such as `Debug` are intentionally not treated as debugger state.
- Added Activity Type diagnostics/logging and deterministic Agent self-tests.
- Manual Activity Type correction is recorded as `user_override` with confidence 1.0.
- Rule removal is represented as a disabled versioned row until generic sync tombstones exist.
