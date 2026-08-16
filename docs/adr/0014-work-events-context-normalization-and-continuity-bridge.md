# ADR 0014 — Work Events, Context Normalization and Short Continuity Bridges

Status: Accepted for 0.1.0-alpha.7.3

## Context

The foreground observer samples Windows accurately, but a raw window transition is not necessarily a new unit of work. IDEs change their window title when the active file changes, browsers change titles when tabs change, and one project can legitimately move between PhpStorm and a browser without representing two independent work events.

WorkTracker also intentionally models additive effort. A short interruption on Project B can happen while the user is still mentally carrying Project A. In that case Project B keeps its direct foreground time and, under strict continuity rules, Project A may receive a short derived continuity credit for the same wall-clock interval.

## Decision

WorkTracker separates three concepts:

1. **Raw Activity Session** — the observed/corrected source record. These records remain independently queryable and syncable.
2. **Work Event** — a derived UI/domain projection that groups adjacent raw sessions representing the same project/context.
3. **Continuity Bridge** — a derived additive credit inside a Work Event for a short, observed interruption followed by return to the anchor project.

A Work Event is a projection. It does not destructively compact or rewrite the raw Activity Sessions.

## Context normalization

Application-specific context parsing is centralized in `ActivityContextNormalizer`.

Current alpha.7.3 providers/heuristics:

- PhpStorm: stable workspace/project part of the window title.
- VS Code: project/workspace part after removing the application suffix.
- Visual Studio: solution/project part after removing the application suffix.
- Browsers: browser suffix is removed, but different tab titles remain distinct unless project classification maps them to the same Project. Learning prefers a Project name/code that is visibly present in the title; broad browser-process rules are never generated automatically.
- Generic applications: normalized process + title.

For known Projects, project identity is stronger than application identity. Therefore consecutive `PhpStorm / Project A` and `Chrome / Project A` sessions are rendered as one Work Event when they are contiguous.

## Event aggregation rules

- Automatic foreground sessions are grouped by Project when `project_id` is known.
- Unknown foreground sessions are grouped only by stable normalized context.
- Adjacent segments of the same group tolerate a small capture gap (currently 15 seconds).
- Manual timers and manual entries remain independent additive events and are never merged merely because they share a Project.
- Changing a file/tab inside the same IDE workspace does not create a new visible Work Event when the resulting sessions classify to the same Project/context.
- Event-level correction applies to all raw sessions represented by that event.

## Continuity Bridge rules

Default alpha.7.3 values:

- `continuity_bridge_max_seconds = 120`
- `continuity_anchor_minimum_seconds = 120`

A bridge may be created only when all of the following are true:

1. Project A has at least 120 seconds of direct foreground work in the current anchor run.
2. Foreground leaves Project A.
3. At least one other foreground interruption is actually observed. Unobserved gaps, pause, idle, sleep and time inside the WorkTracker UI are not bridged.
4. The user returns to Project A within at most 120 seconds.
5. After a bridge is used, at least another 120 seconds of direct Project A foreground work is required before a second bridge can be applied. This prevents rapid A/B oscillation from automatically crediting both Projects for nearly the whole period.

Example:

- 10:00–10:10 Project A / PhpStorm
- 10:10–10:11 Project B / Browser
- 10:11–10:20 Project A / PhpStorm

Derived result:

- Project A Work Event: 10:00–10:20, direct 19m, continuity bridge 1m, credited 20m.
- Project B Work Event: 10:10–10:11, direct/credited 1m.
- Wall-clock coverage: 20m.
- Additive effort: 21m.

If the interruption is 5 minutes, no bridge is created and Project A is split into separate Work Events.

If the middle browser activity is also Project A, it is direct Project A time, not a bridge, and is counted exactly once for Project A.

## Accounting boundary in alpha.7.3

The Windows Agent uses the derived bridge when showing local Work Events and local Effort/Concurrent metrics. Raw Activity Sessions remain the sync source of truth.

Server-side report/invoice materialization of the same derived bridge policy is intentionally a separate follow-up inside the Activity Intelligence workstream. Until that materialization is implemented, server billing continues to use raw persisted Activity Sessions only. This avoids silently changing invoice semantics before the derived-credit audit representation is finalized.

## Activity Type inference

Project classification and Activity Type classification are separate concerns.

Alpha.7.3 adds a conservative Activity Type inference layer for explicit IDE signals only. For example, a title containing a Debugger signal may map to an existing Debug/دیباگ Activity Type, and explicit test-runner signals may map to a Testing type. Plain PhpStorm/VS Code foreground is **not** automatically labeled Development because the IDE can also be used for debugging, tests, Git, terminal or review.

Deep IDE adapters in 0.2 are the authoritative path for run/debug/test state.

## Consequences

- Raw data remains auditable.
- WPF becomes substantially less noisy without deleting detail.
- Rule learning can use stable IDE workspace patterns rather than one rule per file.
- Same-project application switching does not create duplicate project effort.
- Short cross-project interruptions can intentionally create additive effort while remaining bounded and explainable.
- Server-side derived-credit parity must be completed before continuity bridges participate in finalized billing.
