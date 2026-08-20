# WorkTracker 0.1.0-alpha.8.0 — Context Intelligence & Integrations

This patch starts alpha.8 with the first-party PhpStorm Context Bridge.

## PhpStorm compatibility policy

The same release ZIP is intended to support PhpStorm 2025.1 through 2026.2 (`251` through `262.*`).

The release artifact is compiled against PhpStorm 2025.1 using Java 21. Building against the oldest supported platform prevents accidental compile-time use of APIs introduced only in later versions. The generated plugin descriptor declares `since-build=251` and `until-build=262.*`.

Direct compile checks are also supported for 2025.2, 2025.3, 2026.1, and 2026.2. PhpStorm 2026.2 requires Java 25 only when compiling directly against that platform; the normal cross-version release build remains Java 21 bytecode and is intended to run on the newer Java 25-based IDE as well.

JetBrains Plugin Verifier can be run as an optional compatibility matrix against all five supported IDE releases.

## Required install/update steps

1. Laravel:
   - `cd apps/api`
   - `php artisan migrate`
   - `php artisan optimize:clear`
2. Windows Agent:
   - from repository root run `.\tools\build-windows-agent.ps1`
3. PhpStorm plugin release build:
   - `.\tools\build-phpstorm-plugin.ps1 -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin"`
   - install the generated ZIP from `apps\phpstorm-plugin\build\distributions\` via PhpStorm **Settings -> Plugins -> Install Plugin from Disk**
   - restart PhpStorm
4. Optional combined regression gate:
   - `.\tools\test-alpha8-regression.ps1`
5. Optional full IDE compatibility verification:
   - `.\tools\test-alpha8-regression.ps1 -VerifyPhpStormCompatibility`

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
