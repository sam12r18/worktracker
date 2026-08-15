# WorkTracker 0.1.0-alpha.3 — Bidirectional Device Sync

## Delivered
- Real Windows → Laravel outbox transport.
- Stable device registration on every sync cycle.
- Sanctum bearer authentication.
- Windows DPAPI protection for the locally persisted token.
- Background sync every 60 seconds plus manual `Sync now`.
- Bounded exponential retry/backoff.
- Local conflict quarantine table and conflict UI.
- Server-side version checks for projects, rules and activity sessions.
- Pull of projects and project rules to other devices.
- Opaque paginated sync cursor.
- Device revocation enforcement.
- Last-success/pending/conflict status in Windows UI.

## Deliberate alpha.3 boundaries
- Activity sessions are stored centrally but not pulled into another device's local timeline.
- Conflict resolution UI is read-only; activity conflicts require a later explicit resolution workflow.
- Login/password exchange for a device token is not implemented in this source bundle. Deployment must issue a Sanctum token for the user through the host Laravel application's trusted authentication/admin flow.
- Browser extension remains out of scope.

## Critical invariant
No sync operation may normalize overlapping time. `Effort`, `Elapsed Coverage`, and additive concurrent activity rules remain unchanged.

## Next recommended phase
0.1.0-alpha.4: sync observability + server reporting foundation + device/user management, then browser/IDE enrichment.
