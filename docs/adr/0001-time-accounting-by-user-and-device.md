# ADR 0001 — Time Accounting by User and Device

Status: **Accepted**

## Context

A single account may be used on multiple PCs. Concurrent activity does not necessarily mean duplicate tracking; two people may be working on the same project from separate systems.

## Decision

Every activity session is attributed to both a user and device. Concurrent sessions on different devices remain independent and are summed as separate effort/person-hours.

Cross-device deduplication is prohibited.

## Consequences

Positive:

- Correct for collaborative/parallel work.
- Full audit trail by device.
- Supports future team attribution.

Tradeoff:

- A single human using two PCs simultaneously can produce more than one wall-clock hour in an hour. This is expected in raw/person-hour reporting.
- Reports may expose Elapsed Coverage as a separate non-additive metric, but project Effort must never be capped or normalized to wall-clock time.
