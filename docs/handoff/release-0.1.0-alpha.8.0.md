# WorkTracker 0.1.0-alpha.8.0 — Context Intelligence & Integrations

## Scope of this release slice

Alpha.8 starts the deep-context phase with a first-party PhpStorm Context Bridge.

Implemented:

- PhpStorm plugin project under `apps/phpstorm-plugin`.
- Structured local heartbeat protocol v1; no localhost server/port required.
- Project name/path, current file, Git branch, Run Configuration and Run/Debug/Test state.
- Foreground-PID and multi-project disambiguation in Windows Agent.
- Stable IDE context survives file/tab title changes.
- Direct PhpStorm Project matching before fallback Project Rules.
- `Path` Project Rules can match plugin Project paths.
- Plugin Debug/Test signals drive Activity Type with confidence 1.0.
- IDE metadata persisted locally and synced to Laravel `activity_sessions.ide_context`.
- Work Event audit exposes IDE Project/mode/branch metadata.
- Sync tab exposes PhpStorm Context connection status.
- Deterministic Agent tests include plugin context selection and Debug classification.
- Unified `tools/test-alpha8-regression.ps1` test entry point.

Not yet completed in alpha.8:

- Browser URL/domain extension.
- PhpStorm settings UI / privacy allowlist.
- semantic editor actions such as code-review/database/terminal mode.
- Run/Debug/Test integration for VS Code and Visual Studio.
- Bridge financial snapshot/final invoice inclusion.
