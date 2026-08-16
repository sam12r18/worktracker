# WorkTracker 0.1.0-alpha.7.3 — Activity Intelligence & Work Events

## Windows Agent

- Added Work Event projection over raw Activity Sessions.
- Same-Project foreground segments are aggregated even when application/title changes.
- Unknown IDE sessions use normalized workspace Context so file switching does not flood the visible inbox.
- Added bounded 120-second Continuity Bridge with 120-second anchor/rearm requirement and anti-oscillation behavior.
- Local Effort includes derived bridge credit while Coverage remains wall-clock union.
- Event-level Project correction and Activity Type assignment update all represented raw sessions.
- Assign + Learn uses stable IDE workspace patterns.
- Added conservative explicit Debug/Test Activity Type inference; ambiguous IDE foreground remains untyped.

## Web

- Project Rule form upgraded to a Pattern-oriented Rule Builder.
- Stable pattern suggestion from a sample window title.
- 7-day recent-context preview shows matches in the current Project, other Projects and unknown activity.

## Documentation

- ADR 0014 defines Raw Session vs Work Event vs Continuity Bridge.
- Time-accounting and classification ADRs updated.
- Added Activity Intelligence architecture document and alpha.7.3 smoke test.

## Important boundary

Continuity Bridge is currently a derived Windows projection and local accounting credit. Raw sessions remain the sync source of truth. Server reporting/final invoice parity for derived continuity credit is intentionally not silently enabled in this release and remains the next Activity Intelligence sub-phase.
