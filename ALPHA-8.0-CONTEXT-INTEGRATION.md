# WorkTracker 0.1.0-alpha.8.0 — Context Intelligence & Integrations

This patch starts alpha.8 with the first-party PhpStorm Context Bridge.

## Required install/update steps

1. Laravel:
   - `cd apps/api`
   - `php artisan migrate`
   - `php artisan optimize:clear`
2. Windows Agent:
   - from repository root run `./tools/build-windows-agent.ps1`
3. PhpStorm plugin:
   - run `./tools/build-phpstorm-plugin.ps1`
   - install the generated ZIP from `apps/phpstorm-plugin/build/distributions/` via PhpStorm **Settings → Plugins → Install Plugin from Disk**
   - restart PhpStorm
4. Optional combined regression gate:
   - `./tools/test-alpha8-regression.ps1`

## Context protocol v1

The plugin writes short-lived JSON heartbeat files under `%LOCALAPPDATA%\WorkTracker\ide\phpstorm`. The plugin does not receive a WorkTracker API token and does not open a localhost network port. The Windows Agent remains responsible for classification, persistence and authenticated Sync.

Published metadata is limited to IDE/product/build, process id, Project name/path, active file name/path, Git branch, Run Configuration identity, execution mode (`idle`, `run`, `debug`, `test`) and observation timestamp. Source contents, debugger values, console output, environment variables and credentials are not published.

## Behavioral changes

- File/tab changes in the same PhpStorm Project no longer define a new context boundary when plugin context is fresh.
- Project name/path from the plugin can resolve a WorkTracker Project before title-based fallback rules.
- Explicit Debug/Test modes classify Activity Type from `ide_plugin` with confidence `1.0` when matching taxonomy exists.
- Normal Run state does not automatically mean Development. Project default/rules remain fallback.
- IDE context is persisted locally, synced to `activity_sessions.ide_context`, preserved in conflict payloads and visible in Work Event audit.
- If plugin context is missing, stale or ambiguous, foreground capture continues using the existing deterministic fallback.

## Migration

Adds nullable JSON column `activity_sessions.ide_context`. Existing records do not require backfill.

## Build note

The PhpStorm plugin source is configured against PhpStorm 2026.1 and compatibility range `261` through `262.*`. The authoritative compatibility/build gate is the Gradle plugin build on the development machine.
