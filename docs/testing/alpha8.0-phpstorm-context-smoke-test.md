# alpha.8.0 PhpStorm Context Integration — Smoke Test

## Automated gate

From the repository root:

```powershell
.\tools\test-alpha8-regression.ps1
```

The gate builds the Windows Agent and deterministic Activity Intelligence tests, runs focused Laravel Work Event tests, and builds/verifies the PhpStorm plugin. First plugin build can be slow because Gradle/PhpStorm platform artifacts are downloaded.

## Install plugin

Build separately if needed:

```powershell
.\tools\build-phpstorm-plugin.ps1
```

Then in PhpStorm:

```text
Settings → Plugins → ⚙ → Install Plugin from Disk
```

Select the ZIP from:

```text
apps\phpstorm-plugin\build\distributions\
```

Restart PhpStorm.

## Manual scenarios

1. Open WorkTracker Agent → Sync. `PhpStorm Context` must become connected while PhpStorm is foreground.
2. Open several files in one PhpStorm Project. The Work Event must stay one Project context; file changes must not create separate context identities.
3. Open two PhpStorm Projects in separate windows under the same IDE process. Switching windows must select the matching Project context rather than randomly using the other project.
4. Start a normal Run Configuration. Agent log `ide.context` should show `execution_mode=run`.
5. Start Debug. `execution_mode=debug` and Activity Type should resolve to Debugging when that taxonomy exists; `activity_type_source=ide_plugin` with confidence `1.0`.
6. Run PHPUnit/Pest. `execution_mode=test`; Activity Type should resolve to Testing when that taxonomy exists.
7. Stop execution. Context should return to `idle`; plain IDE work should again use Project default/rules.
8. Verify `git_branch` changes after switching branches.
9. Sync a new Activity and open `/worktracker/work-events`; expanded segment should show IDE Project/mode/branch metadata.
10. Stop/disable the plugin temporarily. Foreground capture must continue using the previous title/rule fallback and no crash should occur.

## Sync / storage checks

After a plugin-enriched Activity is synced, `activity_sessions.ide_context` must contain protocol v1 metadata. No source contents, tokens or console/debug values should be present.
