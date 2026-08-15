# ADR 0007 — Central reporting, device operations and explicit conflict resolution

Status: Accepted — 2026-08-11

## Context
Alpha.3 reliably syncs offline-created projects, rules and activity sessions, but the server lacked an operational view and conflicts were transient response objects. A central report must preserve WorkTracker's additive time semantics: overlapping activities are valid even on the same user/device/project, and work from multiple devices is additive.

## Decision
1. Laravel is the canonical aggregation and operations surface.
2. Central reports expose three distinct metrics: `effort_seconds`, `elapsed_coverage_seconds`, and `concurrent_effort_seconds`.
3. Effort is never normalized to wall-clock time. Coverage is the union of intervals within the selected reporting scope.
4. Reports provide project and device/operator breakdowns so team/device parallelism remains auditable.
5. Devices have optional `operator_label` plus sync-health telemetry. Revoked devices cannot sync.
6. Server sync conflicts are durable records containing both client and server payload snapshots.
7. Conflict resolution is explicit: `keep_server` or `accept_client`. No automatic activity merge is permitted.
8. `accept_client` locks the current server entity and creates a new version based on the latest real server version, not the stale version observed when the conflict was first created.
9. Resolution is delivered to the originating device on a later sync and acknowledged by conflict id. Acknowledgement is retried until the server confirms receipt.
10. The included Blade operations panel is dependency-light and optional; API endpoints remain the stable contract.

## Consequences
- A day can legitimately show 10h of Effort over 7h of Coverage.
- Multiple devices working simultaneously increase total project Effort.
- `Concurrent Effort` is descriptive, not a productivity score.
- Conflict records are auditable and cannot silently discard tracked time.
