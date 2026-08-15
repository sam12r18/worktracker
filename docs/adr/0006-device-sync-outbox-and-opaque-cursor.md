# ADR 0006 — Device Sync, Outbox, Conflict Safety and Opaque Cursor

Status: Accepted — 2026-08-11

## Decision
Windows agents are offline-first. Every locally-created syncable entity is committed to SQLite together with an outbox record in the same transaction. Network delivery is asynchronous and must never block foreground tracking.

The Windows agent authenticates with a Laravel Sanctum bearer token. The token is protected with Windows DPAPI (`CurrentUser`) before being stored in local `device_state`; plaintext tokens are never persisted.

Each device registers its stable UUID with the server before sync. A revoked device is rejected by the API.

## Push
`POST /api/v1/sync` accepts up to 1000 idempotent `upsert` changes. Entity IDs are client-generated UUID-compatible identifiers. Current alpha.3 sync entities are:

- `project`
- `project_rule`
- `activity_session`

The server compares monotonically increasing entity versions. Equal versions are idempotent acknowledgements. If the server has a newer version, the client receives a conflict.

## Pull
Project and project-rule configuration is pulled after every sync so multiple Windows devices converge on the same project classification configuration.

Activity sessions are not pulled into another device's local timeline in alpha.3. The server stores them centrally for reporting; each local agent keeps its own captured timeline.

The pull cursor is opaque to the client. It encodes `(updated_at, entity_type, entity_id)` and supports stable pagination even when several rows share a timestamp.

## Conflicts
Conflicts must never silently destroy tracked time.

- Activity-session conflict: keep local row, mark `sync_state=conflict`, persist a `sync_conflicts` row, and require explicit future resolution.
- Project/project-rule conflict: log the conflict; server configuration may be pulled after the local outbox item is quarantined.
- Automatic merge of activity time ranges is forbidden.

## Retry
Failed outbox delivery uses bounded exponential backoff. Capture continues normally while offline or while the server is unavailable.

## Time accounting invariant
Sync does not normalize, deduplicate, merge, or cap overlapping activity effort. Additive concurrency semantics from ADR 0004 remain authoritative.
