# ADR 0003 — Client-generated UUIDs for device and activity identity

Status: Accepted — 2026-08-11

## Context

The Windows Agent is offline-first. A device and its activity sessions must have stable identifiers before the agent has authenticated or contacted Laravel.

## Decision

- `devices.id` is a client-generated UUID.
- `activity_sessions.id` is a client-generated UUID.
- `projects.id` and `project_rules.id` are also client-generated UUIDs because alpha.2 allows offline project/rule creation.
- Task identifiers may remain server-side ULIDs until offline task creation is implemented.
- Sync uses the UUID as the idempotency identity.
- The Laravel authenticated user remains authoritative for ownership; a payload cannot choose its server-side `user_id`.

## Consequences

A device can capture data immediately offline. Registering the same device UUID is idempotent for the same authenticated user and rejected if the UUID is already owned by another user.
