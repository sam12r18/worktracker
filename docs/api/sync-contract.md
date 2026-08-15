# WorkTracker Sync Contract — alpha.4.1


## Authentication and device binding (alpha.4.1)
Sync requires a Sanctum Personal Access Token carrying `device:sync` and `device:{device_id}`. Initial registration additionally requires `device:register`. The server verifies that the UUID in the request matches the UUID-specific token ability. A token issued for one workstation cannot be reused for another UUID. Admin tokens with `admin:write` are reserved for trusted administrative tooling and should not be installed in Windows Agents.

## Authentication
All endpoints are under `/api/v1` and require `auth:sanctum`.

The Windows agent stores the Sanctum token encrypted with Windows DPAPI for the current Windows account.

## Device registration
`POST /api/v1/devices`

```json
{
  "id": "device-uuid",
  "name": "OFFICE-PC",
  "platform": "windows",
  "app_version": "0.1.0-alpha.7"
}
```

The endpoint is idempotent for the same user/device UUID. A UUID already owned by another user is rejected. Revoked devices cannot sync.

## Bidirectional sync
`POST /api/v1/sync`

Request:

```json
{
  "device_id": "device-uuid",
  "cursor": "opaque-cursor-or-null",
  "pull_limit": 500,
  "acknowledged_conflict_ids": [],
  "changes": [
    {
      "entity": "activity_session",
      "id": "activity-uuid",
      "operation": "upsert",
      "version": 1,
      "payload": {}
    }
  ]
}
```

Response:

```json
{
  "accepted": [
    {"entity":"activity_session","id":"...","version":1}
  ],
  "conflicts": [
    {"conflict_id":"...","entity":"project","id":"...","server_version":3,"reason":"server_newer"}
  ],
  "resolutions": [
    {"conflict_id":"...","entity":"project","id":"...","resolution":"keep_server","server_version":3,"server_payload":{}}
  ],
  "remote_changes": [
    {
      "entity":"project",
      "id":"...",
      "version":2,
      "updated_at":"2026-08-11T04:20:00.000000Z",
      "payload": {}
    }
  ],
  "server_cursor": "opaque-cursor"
}
```

### Push entities
- `project`
- `project_rule`
- `activity_session`

### Pull entities in alpha.4
- `project`
- `project_rule`

Activity sessions remain device-local in the Windows timeline while all pushed sessions are retained centrally on Laravel for reporting.

## Idempotency and versions
A client-created entity starts at version `1`. A local correction increments its version. The server accepts a version greater than the stored version, acknowledges an equal version as an idempotent replay, and returns a conflict when the server version is newer.

## Outbox
Local entity mutation and outbox creation must occur within one SQLite transaction. The network layer reads due outbox rows in batches. Successful acknowledgement deletes the corresponding outbox item and marks the local entity `synced`.

## Failure/retry
HTTP/network failure never rolls back already captured activity. The outbox row records `attempt_count`, `last_error`, and `next_attempt_at` using bounded exponential backoff.

## Conflict safety
Conflicts are persisted locally in `sync_conflicts`. Activity-session conflicts are never automatically merged or discarded.

## Cursor
The cursor is an opaque server value. Clients must persist and replay it without parsing or modifying it.

## Alpha.4 conflict resolution extension
Client requests may include `acknowledged_conflict_ids: uuid[]`.

Sync responses additionally include `resolutions[]` with:
- `conflict_id`
- `entity`
- `id`
- `resolution`: `keep_server` or `accept_client`
- `server_version`
- `server_payload`

The device applies explicit resolutions, marks its local conflict resolved, and queues the `conflict_id` for acknowledgement on the next successful sync. The server keeps returning unacknowledged resolutions so a lost HTTP response does not silently lose the decision.

A conflict is not permission to merge or normalize Activity time. `keep_server` explicitly applies the server payload; `accept_client` explicitly creates a newer server version from the client payload.


## Billing configuration in alpha.6
Server-to-Agent `remote_changes` may now include `activity_type` records. Project payloads also include `customer_id`, `rate_multiplier`, and `is_billable_default`. These values are configuration cache only on Windows; Laravel remains authoritative for monetary calculation.

Activity Session push may include:
- `activity_type_id`
- `is_billable` (`true`, `false`, or `null` to inherit pricing defaults)

Activity Types are not pushed by Device Tokens. They are managed on the authenticated server dashboard and pulled through normal Sync.


### Billing authority boundary
`activity_type` is Pull-only configuration. Project Pull includes `customer_id`, `rate_multiplier`, and `is_billable_default` for local context. Device project Push must not mutate these commercial fields on Laravel; only authenticated server-side Billing administration changes them.
