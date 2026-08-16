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

## Agent response mapping and full-pull recovery (alpha.7.2 hotfix)
Laravel response field names are authoritative snake_case (`remote_changes`, `server_cursor`, `updated_at`, `server_version`, `conflict_id`, `server_payload`). The Windows Agent DTO layer must map these names explicitly; case-insensitive mapping alone is insufficient because underscores are semantically significant for `System.Text.Json` property matching.

Sync protocol version `2` resets the locally persisted cursor once on upgrade so configuration missed by older clients is pulled again. The Sync tab also exposes **بازخوانی کامل از سرور**, which clears only the local cursor and re-pulls Project, Project Rule, and Activity Type configuration without deleting the local activity timeline or token.

## Sync diagnostics and correlation (alpha.7.2 hotfix)
Windows Agent sends `X-WorkTracker-Correlation-ID` on both device registration and sync requests. Laravel returns the same identifier on successful responses and writes it to the dedicated `worktracker_sync` log channel. The Agent writes the same identifier to `%LOCALAPPDATA%\\WorkTracker\\logs\\agent-YYYY-MM-DD.log`, allowing one sync cycle to be traced across both sides without logging the Sanctum token.

Agent queue diagnostics distinguish:
- `Total`: all outbox rows.
- `Due`: immediately eligible for the next request.
- `Delayed`: temporarily held by exponential backoff.
- `Failed`: rows carrying a previous `last_error`.
- `NextRetryAt`: earliest scheduled retry.

The UI action **تلاش مجدد صف** clears only `next_attempt_at`; it does not delete the outbox row, reset captured activity, or remove the last error/attempt history.


## Activity Type Intelligence configuration (alpha.7.3 P1)

The server can pull four configuration entity families to a Windows Agent:

- `project` — now also carries `default_activity_type_id`.
- `project_rule` — identifies the Project.
- `activity_type` — billing/activity taxonomy.
- `activity_type_rule` — identifies the Activity Type after Project classification.

`activity_type_rule` is server-authored in this phase and is pull-only. Its payload contains:

```json
{
  "project_id": "optional-project-id",
  "activity_type_id": "uuid",
  "rule_type": "ProcessName|WindowTitle|ExecutablePath|ContextKey|Keyword",
  "operator": "contains|equals|starts_with|ends_with|regex",
  "pattern": "phpstorm64",
  "weight": 80,
  "priority": 0,
  "confidence": 0.9,
  "is_enabled": true
}
```

Activity sessions may include `activity_type_confidence`, `activity_type_source`, and `activity_type_reason`. These fields describe **why** a type was selected and must not be confused with Project classification confidence. Manual type correction uses `activity_type_source=user_override` and confidence `1.0`.

Because the cursor protocol does not yet have generic delete tombstones, deleting an Activity Type Rule in the Web UI is represented as `is_enabled=false` with an incremented version.

## Exact outbox acknowledgement — alpha.7.3 reliability patch

Each pushed change now carries an optional `client_outbox_id`, which is the immutable SQLite outbox-row id selected for that HTTP request. Laravel echoes the same value in the matching `accepted[]` row.

Request item:

```json
{
  "entity": "project_rule",
  "id": "rule-id",
  "client_outbox_id": "local-outbox-row-id",
  "operation": "upsert",
  "version": 1,
  "payload": {}
}
```

Accepted item:

```json
{
  "entity": "project_rule",
  "id": "rule-id",
  "version": 1,
  "client_outbox_id": "local-outbox-row-id"
}
```

The Agent acknowledges by `client_outbox_id` first. Entity/id matching remains as a backward-compatible fallback for older servers. Whole-batch fallback is retained only when the response proves that every sent item was accepted and there were no conflicts. Configuration mutations such as `project_rule` are prioritized ahead of ordinary Activity Session rows in the local outbox, so explicit user learning reaches the server promptly even when Activity capture has a backlog.
