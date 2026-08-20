# alpha.8.0 PhpStorm Context Integration — Smoke Test

## Supported IDE range

The plugin release package is intended for PhpStorm 2025.1 through 2026.2:

- 2025.1 / branch 251 / Java 21
- 2025.2 / branch 252 / Java 21
- 2025.3 / branch 253 / Java 21
- 2026.1 / branch 261 / Java 21
- 2026.2 / branch 262 / Java 25 runtime

The normal release build compiles against PhpStorm 2025.1 with Java 21 and declares compatibility through `262.*`. This keeps the compile-time API floor at the oldest supported version.

## Automated gate

From repository root:

```powershell
.\tools\test-alpha8-regression.ps1
```

For the expensive JetBrains Plugin Verifier matrix as well:

```powershell
.\tools\test-alpha8-regression.ps1 -VerifyPhpStormCompatibility
```

The compatibility verifier checks the built plugin against PhpStorm 2025.1, 2025.2, 2025.3, 2026.1 and 2026.2. It can download several large IDE distributions.

## Build the release plugin

With the existing JDK 21 installation:

```powershell
.\tools\build-phpstorm-plugin.ps1 `
    -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin"
```

Do not pass `-PlatformVersion 2026.2` for the normal cross-version release build. The default target is 2025.1 intentionally.

A direct 2026.2 compile check remains available and requires Java 25:

```powershell
.\tools\build-phpstorm-plugin.ps1 -PlatformVersion 2026.2 -JavaHome "<JDK-25>"
```

## Install plugin

In PhpStorm:

```text
Settings -> Plugins -> gear icon -> Install Plugin from Disk
```

Select the ZIP from:

```text
apps\phpstorm-plugin\build\distributions\
```

Restart PhpStorm.

## Manual scenarios

1. Install the same release ZIP on at least one PhpStorm 2025.x instance and the current PhpStorm instance used for development.
2. Open WorkTracker Agent -> Sync. `PhpStorm Context` must become connected while PhpStorm is foreground.
3. Open several files in one PhpStorm Project. The Work Event must stay one Project context; file changes must not create separate context identities.
4. Open two PhpStorm Projects in separate windows under the same IDE process. Switching windows must select the matching Project context rather than randomly using the other project.
5. Start a normal Run Configuration. Agent log `ide.context` should show `execution_mode=run`.
6. Start Debug. `execution_mode=debug` and Activity Type should resolve to Debugging when that taxonomy exists; `activity_type_source=ide_plugin` with confidence `1.0`.
7. Run PHPUnit/Pest. `execution_mode=test`; Activity Type should resolve to Testing when that taxonomy exists.
8. Stop execution. Context should return to `idle`; plain IDE work should again use Project default/rules.
9. Verify `git_branch` changes after switching branches.
10. Sync a new Activity and open `/worktracker/work-events`; expanded segment should show IDE Project/mode/branch metadata.
11. Stop/disable the plugin temporarily. Foreground capture must continue using the previous title/rule fallback and no crash should occur.

## Sync / storage checks

After a plugin-enriched Activity is synced, `activity_sessions.ide_context` must contain protocol v1 metadata. No source contents, tokens or console/debug values should be present.
