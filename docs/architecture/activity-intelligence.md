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
