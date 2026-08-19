# ADR 0015 — PhpStorm Context Bridge via local heartbeat metadata

## Status
Accepted for `0.1.0-alpha.8.0`.

## Context
Window title parsing cannot reliably tell whether PhpStorm is editing, running, debugging, or testing, and file/tab changes create noisy context. WorkTracker needs semantic IDE metadata without turning the IDE plugin into another authenticated network client.

## Decision
A first-party PhpStorm plugin publishes protocol-v1 metadata to a short-lived JSON file under the current Windows user's LocalAppData. The WPF Agent remains the only component responsible for API authentication and Sync.

The plugin may publish Project name/path, active file path, Git branch, Run Configuration, IDE build, and execution mode (`idle/run/debug/test`). It must not publish source content, console output, debugger values, environment variables, credentials, or WorkTracker tokens.

The Agent selects files by foreground PhpStorm PID and, where one IDE process hosts multiple projects, uses Project/file hints from the foreground title to disambiguate. Ambiguity means fallback, not guessing.

Debug/Test plugin states are explicit Activity Type signals. Run is context only and does not automatically equal Development. Project Rules/default Activity Types remain fallbacks.

Raw sessions retain the metadata for audit/reprojection. Laravel stores it as JSON and never treats it as a monetary input by itself.

## Consequences
- File/tab switches inside one IDE Project no longer define work boundaries.
- Debug/Test classification can become deterministic without title heuristics.
- Agent operation remains safe if the plugin is missing or stale.
- Local project/file paths become diagnostic metadata and must be covered by privacy controls in a later alpha.8 slice.
