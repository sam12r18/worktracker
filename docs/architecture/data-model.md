# Data Model

## devices

- id (ULID)
- user_id
- name
- fingerprint_hash
- platform
- app_version
- last_seen_at
- revoked_at

## projects

- id (ULID)
- user_id / future organization_id
- parent_id nullable
- name
- code nullable
- status
- color nullable
- is_archived

## project_rules

- id
- project_id
- rule_type
- operator
- pattern
- weight
- priority
- is_enabled

Rule types on the sync wire are canonical PascalCase values: `Path`, `WindowTitle`, `ProcessName`, `ExecutablePath`, `Keyword`. Operators are `contains`, `equals`, `starts_with`, `ends_with`, `regex`. Windows persists and executes the operator locally; older local databases are upgraded with `operator=contains`.

## activity_sessions

- id (client-generated ULID)
- user_id
- device_id
- project_id nullable
- source
- process_name nullable
- executable_path nullable
- window_title nullable/redacted
- classification_confidence nullable
- classification_reason nullable
- started_at
- ended_at
- duration_seconds
- idle_seconds
- note nullable
- is_billable
- version
- created_at_device
- updated_at_device
- created_at
- updated_at

### Identity / accounting invariant

`device_id` must never be discarded during aggregation. Cross-device overlap is not a duplicate signal.

## daily_reports

- id
- user_id
- report_date
- timezone
- status
- summary nullable
- generated_at nullable

## local-only sync_outbox

- id
- entity_type
- entity_id
- operation
- payload_json
- attempt_count
- next_attempt_at
- last_error
- created_at

## tasks

- id (ULID)
- project_id
- parent_id nullable
- title
- description nullable
- status
- priority
- due_at nullable
- started_at nullable
- completed_at nullable
- estimated_minutes nullable
- sort_order

`activity_sessions.task_id` is optional. Project time is always available even when no task is selected.

## alpha.3 sync additions

### Server: projects / project_rules
Both entities now carry an unsigned integer `version` used for idempotent device sync and conflict detection. IDs remain UUID-compatible 36-character strings after the alpha.2 widening migration.

### Local: sync_outbox
Queued transport record with entity type/id, operation, serialized payload, attempt count, next retry time and last error. Entity mutation and outbox insertion are transactional.

### Local: sync_conflicts
Quarantine table for unresolved server-newer conflicts:
- id
- entity_type
- entity_id
- server_version
- reason
- created_at
- resolved_at

Activity conflict rows never imply deletion of the local Activity Session.

### Local: device_state sync keys
- `sync_api_url`
- `sync_access_token_dpapi`
- `sync_cursor`
- `sync_last_success`

The access token value is DPAPI ciphertext, not plaintext.

## Alpha.4 operations additions

### devices
Additional fields:
- `operator_label` nullable descriptive label
- `last_sync_started_at`
- `last_sync_succeeded_at`
- `last_sync_error`
- `last_sync_pushed`
- `last_sync_pulled`

### sync_conflicts (server)
Durable conflict audit record:
- id UUID
- user_id / device_id
- entity_type / entity_id
- client_version / server_version
- client_payload / server_payload JSON
- reason
- status (`open`, `resolved`)
- resolution (`keep_server`, `accept_client`)
- resolved_by_user_id / resolved_at
- acknowledged_at

### sync_resolution_acks (Windows SQLite)
Retry-safe queue containing server conflict ids whose resolution has been applied locally and must be acknowledged.


## Billing and invoicing (alpha.6)
- `customers`: customer identity, default currency, multiplicative rate factor.
- `activity_types`: server-managed work categories and base hourly rate.
- `projects.customer_id`, `projects.rate_multiplier`, `projects.is_billable_default`.
- `activity_sessions.activity_type_id`, `activity_sessions.is_billable`.
- `pricing_overrides`: effective-dated final hourly rate for Customer+Activity or Project+Activity.
- `invoices`: Draft/Final monthly billing document.
- `invoice_items`: per-Activity billing lines with copied rate inputs and amount.
- `billing_rate_snapshots`: immutable finalized pricing snapshot keyed by Activity Session.

Finalized invoice items do not rewrite Activity timestamps or durations. Invoice-period clipping is calculated only in the line item.

## Derived Work Event projection (alpha.7.3)

Laravel now materializes the same derived Work Event projection used by the Windows Agent. Raw `activity_sessions` remain the source of truth and are never rewritten by the projection.

### work_events

- `id` deterministic SHA-256 projection id
- `user_id`
- `device_id`
- `project_id` nullable
- `projection_date` local reporting date
- `timezone`
- `event_kind` (`foreground`, `unknown_foreground`, `manual`)
- `context_key`
- `started_at` / `ended_at`
- `direct_seconds`
- `bridge_seconds`
- `credited_seconds`
- `segment_count` / `bridge_count`
- `applications` JSON
- `projection_version`
- `calculated_at`

### work_event_segments

Maps a derived Work Event back to every raw Activity Session used to build it. This table is the audit link that allows the UI to expand an Event without losing raw detail.

### continuity_bridges

Stores each derived continuity credit explicitly:

- `work_event_id`
- `anchor_project_id`
- `device_id` / `user_id`
- `projection_date`
- `started_at` / `ended_at`
- `duration_seconds`
- `interrupted_project_ids` JSON
- `reason`
- `projection_version`

Mutual and multi-project Bridge rows are valid. The same wall-clock interval may therefore appear in several Project projections when each Project independently satisfies the continuity policy.

### Persistence boundary

The projection is derived and rebuildable. Historical edits and accepted Sync changes rebuild affected local dates. Final invoice calculation still uses raw Activity Sessions until Bridge billing parity is explicitly enabled in a later phase.
