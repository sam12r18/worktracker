# Server Work Event Projection and Bridge Audit

## Goal

Laravel must reproduce the Agent's Work Event/Continuity policy without modifying raw Activity Sessions. This gives Web reports and future billing a deterministic, auditable projection.

## Source of truth

`activity_sessions` remain authoritative capture/correction records. `work_events`, `work_event_segments`, and `continuity_bridges` are rebuildable derived tables.

## Policy parity

- capture merge tolerance: 15 seconds
- initial Project anchor: 60 seconds
- maximum observed Bridge interruption: 120 seconds
- per-Project re-arm after Bridge: 120 direct seconds
- mutual and multi-Project Bridge: allowed
- no global anchor and no wall-clock normalization
- manual Activities remain independent additive events

## Materialization triggers

1. Device Sync accepts a changed Activity Session: affected local dates are rebuilt after the Activity transaction. Rebuild is best-effort and capped to avoid stalling a large historical Sync.
2. Historical Web correction: old and new local dates are rebuilt, because a timestamp correction can move an Activity across midnight.
3. Admin Work Event page/API: an explicit rebuild action is available for audit/backfill. GET endpoints do not mutate projection data.

## Timezone rule

Database/API timestamps stay UTC. Projection-day boundaries are calculated in `WORKTRACKER_DISPLAY_TIMEZONE` (default `Asia/Tehran`) and converted to UTC before MySQL queries. This avoids comparing a Tehran wall-clock Carbon value directly against UTC columns.

## Billing boundary

Reports can display Credited Effort = Raw Effort + Continuity Bridge. Invoice generation is not changed in this phase. A later billing phase must freeze Bridge snapshot/audit semantics before derived credit can affect finalized financial documents.
