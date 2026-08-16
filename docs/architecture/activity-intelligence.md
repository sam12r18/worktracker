# Activity Intelligence and Work Event Projection

## Pipeline

```text
Windows foreground observation
        ↓
Raw Activity Session
        ↓
Project classification + conservative Activity Type inference
        ↓
Application Context normalization
        ↓
Work Event aggregation
        ↓
Optional short Continuity Bridge
        ↓
WPF Timeline / correction / learning
```

The pipeline deliberately keeps raw capture separate from presentation/accounting projections.

## Why window title is not an Activity boundary

A title change can mean only that the user selected another file or tab. For IDEs, WorkTracker derives a stable `ContextKey` such as:

```text
ide:phpstorm:ketabnow2
```

Examples that share one stable context:

```text
Ketabnow2 – README.md
Ketabnow2 – laravel.log
Ketabnow2 – build.php
```

When classification maps these segments to the same Project, WPF presents one Work Event.

## Browser behavior

Browser process identity is not sufficient. `Chrome` can contain unrelated projects. Until the Browser Extension exists, WorkTracker uses title patterns and explicit Project Rules. Once different browser tabs classify to the same Project and are contiguous, they can belong to the same Project Work Event.

## Rule learning

`Assign + Learn` is event-aware. For IDE contexts the learned `WindowTitle contains ...` pattern uses the stable workspace name rather than the entire file title. For browsers, the Agent first prefers the selected Project name/code when that text is actually present in the observed tab title; it does not learn a broad `ProcessName=chrome` rule or fabricate an exact tab-title rule when no stable hint exists. Web Project Rule management also includes a Pattern suggestion and a recent-activity preview to detect cross-project overmatching.

## Corrections

Reassigning a Work Event updates every raw foreground session represented by that event. The correction does not alter timestamps. Learning is still explicit.

## Activity Type safety

Automatic Project inference may be deterministic enough for billing. Automatic Activity Type inference has a higher semantic risk. Therefore alpha.7.3 infers only explicit Debug/Test signals and leaves ambiguous IDE work untyped. Deeper IDE integrations are scheduled for 0.2.


## Per-project continuity state machine

Continuity is not a single global state. Every classified Project owns an independent projection state:

```text
Direct → Suspended → BridgeCandidate → Bridged → Direct
                              ↘ Closed
```

Defaults in alpha.7.3:

- initial direct anchor: 60s
- maximum observed interruption: 120s
- re-arm after a successful bridge: 120s of new direct work for that same Project

Mutual and multi-project bridges are allowed. A direct span of Project B remains available as an interruption span for Project A while also remaining direct work for Project B's own continuity projection. No global `Absorbed` flag may remove a span from another Project's projection.

Idle, pause, sleep, WorkTracker's own UI and genuinely unobserved foreground gaps close continuity because the interruption cannot be proven from captured foreground spans.

## Project Pulse widget

The Windows Agent includes a small always-on-top `ProjectPulseWidget`. It shows the three most recently active Projects, today's credited time per Project, direct vs continuity time, current application/state, and global Effort/Coverage/Concurrent counters. The current foreground segment is projected in memory before it is flushed to SQLite, so the active Project counter advances live without changing the raw persistence model.
