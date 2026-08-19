# PhpStorm Context Bridge — alpha.8.0

## Goal

Window titles are a useful fallback but are not a reliable source for IDE semantic state. Alpha.8.0 adds a small PhpStorm plugin that publishes structured context to the Windows Agent without opening a network listener.

The plugin writes a short-lived JSON heartbeat under:

```text
%LOCALAPPDATA%\WorkTracker\ide\phpstorm\context-<ide-pid>-<project-hash>.json
```

The Agent reads only files matching the foreground PhpStorm process ID. Multiple PhpStorm projects in one IDE process are supported: the Agent selects the fresh candidate whose Project/current file best matches the foreground window. An ambiguous multi-project match stays unenriched rather than guessing.

## Protocol v1

The heartbeat contains metadata only:

- plugin and IDE version/build
- IDE process ID
- PhpStorm Project name and base path
- current file name/path
- current Git branch
- execution mode: `idle`, `run`, `debug`, or `test`
- active Run Configuration name/type
- UTC observation timestamp

No source-code contents, API token, environment variables, console output, debugger values, or Git credentials are written.

## Execution semantics

The plugin listens to IntelliJ execution lifecycle events. Debug is deterministic from the Debug executor. A normal executor is marked `test` when its Run Configuration identifies PHPUnit/Pest/test execution; otherwise it is `run`. When no tracked execution is active, the mode is `idle`.

Priority when several executions are active:

```text
Debug > Test > Run > Idle
```

This state enriches Activity Type classification but does not directly change Project accounting or timestamps.

## Project classification

The Agent first attempts an exact normalized match between the plugin Project name/path leaf and active WorkTracker Project name/code. When that is uniquely resolvable, plugin metadata wins with confidence `1.0`. If it is ambiguous or not mapped, the existing Project Rule engine remains authoritative.

`ProjectRuleType.Path` uses the IDE Project path when PhpStorm metadata is present. This allows a server-authored Path Rule such as `Path starts_with I:\worktracker` to map an IDE workspace without relying on changing file titles.

## Activity Type classification

Plugin `debug` and `test` states are explicit signals with confidence `1.0`. `run` does not force Development: Project default and Activity Type Rules still decide the semantic type. This avoids treating every running PhpStorm process as coding.

## Raw-data boundary

Raw Activity Sessions keep `ide_context` as JSON for audit/reprojection. The local SQLite source stores the same metadata in `ide_context_json`. Laravel validates and casts this field; Work Event audit can show Project/mode/branch metadata.

## Availability and failure behavior

The Agent considers a heartbeat live for 12 seconds and hard-stale after two minutes. Plugin or file failures never stop foreground capture: the system falls back to title/rule classification. Context decision changes are logged under `ide.context`; Activity Type use is logged under `activity.type`.
