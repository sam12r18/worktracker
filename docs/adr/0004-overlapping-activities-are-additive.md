# ADR 0004 — Overlapping activities are additive

Status: Accepted — 2026-08-11

## Context

Real work can be concurrent. A user may be on a project phone call while reviewing code, uploading a deployment, writing a prompt, or waiting for a query. Those activities can belong to the same project or different projects.

Example: 10:00–10:20 phone call + 10:00–10:20 coding for the same project is 20 minutes of elapsed coverage and 40 minutes of project effort.

## Decision

- Overlapping activities are valid and are never deduplicated for effort accounting.
- This applies even for the same user, same device, same project, and identical time range.
- Multiple manual timers may run concurrently.
- Manual timers do not pause automatic foreground capture.
- Cross-device overlap is also preserved.
- Automatic foreground capture itself remains a single foreground stream per device; tracker bugs must not create duplicate auto-foreground segments for the same stream.
- Reports expose both `Effort` (sum of activity durations) and `Elapsed Coverage` (union of intervals).
- `Concurrent Effort = Effort - Elapsed Coverage` is descriptive only and must not be treated as a productivity score.

## Consequences

Project effort may exceed wall-clock time. That is expected behavior, not an accounting error. Source activity records must never be mutated merely to make totals fit elapsed time.
