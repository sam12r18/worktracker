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

`Assign + Learn` is event-aware. For IDE contexts the learned `WindowTitle contains ...` pattern uses the stable workspace name rather than the entire file title. For browsers, the Agent first prefers the selected Project name/code when that text is actually present in the observed tab title. If that hint is unavailable but the explicitly selected browser Work Event contains a segmented stable suffix/prefix (for example `پیشنهاد ارسال فایل ZIP - Ketabnow`), the learner may derive the bounded reusable segment `Ketabnow`. It still refuses broad `ProcessName=chrome` rules and refuses an unsplit volatile single title such as `Bale Web (17)`. Learned rules are queued as `project_rule`, prioritized in Sync, and immediately synced after an explicit Learn action when the connection is configured. Web Project Rule management also includes a Pattern suggestion and a recent-activity preview to detect cross-project overmatching.

## Corrections

Reassigning a Work Event updates every raw foreground session represented by that event. The correction does not alter timestamps. Learning is still explicit.

## Activity Type safety

Automatic Project inference may be deterministic enough for billing. Automatic Activity Type inference has a higher semantic risk. Therefore alpha.7.3 infers only explicit Debug/Test signals and leaves ambiguous IDE work untyped. Deep PhpStorm integration starts in alpha.8.0; VS Code/Visual Studio and browser enrichment remain later work.


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

The widget has both **full** and **compact** layouts. Compact mode keeps the three Project names and live credited counters visible in a narrow 238×146 window with a one-line Effort/Concurrent summary; toggling back restores the previous full-size dimensions.


## Laravel projection parity

The backend now contains `WorkEventProjectionService`, `WorkEventMaterializer`, and materialized `work_events`, `work_event_segments`, and `continuity_bridges` tables. The server uses the same policy values as the Agent: 60s initial anchor, 120s maximum Bridge, 120s per-Project re-arm, 15s capture merge tolerance, and independent mutual/multi-Project continuity.

Accepted Activity Sync changes rebuild only affected local dates with a bounded number of dates per request. Historical Web corrections rebuild both the previous and new local dates when an Activity crosses a date boundary. The Work Event audit page can also explicitly rebuild a selected day.

Reports may display Direct + Bridge credited Effort, while finalized Billing intentionally remains on raw Activity Sessions until financial parity tests and immutable Bridge snapshot semantics are completed.


## Activity Type Intelligence — P1

Project classification and Activity Type classification are separate pipelines. The Agent resolves Activity Type in this order:

1. Explicit IDE signal (`debug`, debugger, PHPUnit/test runner, explicit review signals).
2. User-managed `activity_type_rules`, optionally scoped to the resolved Project.
3. `projects.default_activity_type_id` as a conservative fallback.
4. Unknown when no reliable signal exists.

A plain IDE process is **not** intrinsically equal to Development. A software Project can opt into Development by setting its project default, while Debugging/Testing rules or explicit IDE signals override that default.

Each automatic decision stores separate provenance fields on the raw Activity Session:

- `activity_type_confidence`
- `activity_type_source`
- `activity_type_reason`

This lets Web diagnostics and future billing review distinguish an explicit IDE signal from a configurable Rule, a Project fallback, or a user correction.

Configured Rule types are `ProcessName`, `WindowTitle`, `ExecutablePath`, `ContextKey`, and `Keyword`; operators are the same safe set used by Project Rules. Regex evaluation on the Agent uses a timeout.

### Rule precedence details

Rule `priority` is evaluated before accumulated `weight`. At the same priority, a Project-scoped Rule is considered more specific than a global Rule. Two candidates with the same priority and scope remain Unknown when their scores are within the ambiguity margin; the resolver does not guess.

Explicit IDE signals are intentionally narrow. A source filename such as `DebugService.php` is not by itself a Debugging signal. Without plugin context, fallback signals still require debugger/test/review UI wording such as `Debugger`, `[Debug]`, `PHPUnit`, `Test Runner`, `Code Review`, or `Git Diff`. If such a strong signal is present but the matching Activity Type taxonomy does not exist, classification remains Unknown rather than silently falling back to the Project default.

## PhpStorm Context Intelligence — alpha.8.0

Alpha.8 adds a first-party PhpStorm Context Bridge. For fresh plugin-enriched foreground observations, the stable IDE identity comes from Project name/path rather than parsing the current file from the title. The plugin also provides current file, Git branch, Run Configuration and explicit `idle/run/debug/test` state.

The old window-title parser remains a fallback. Plugin absence, staleness, malformed JSON, or multi-project ambiguity must never stop capture.

`debug` and `test` plugin states are deterministic Activity Type signals with confidence `1.0`. `run` remains semantic context only; it does not bypass configured Activity Type rules/defaults. Raw Activity Sessions retain the IDE JSON so Laravel and Windows can audit/reproject from the same source metadata.
