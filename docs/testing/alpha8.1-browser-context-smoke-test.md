# Alpha 8.1 — Context Integrations Smoke Test

## Preconditions

- Chrome installed
- .NET 10 SDK installed
- WorkTracker Agent builds/runs
- Laravel migration executed
- Chrome extension loaded unpacked from `apps/chrome-extension`
- Native Messaging host registered with the actual Chrome Extension ID
- PhpStorm plugin Alpha 8.1 installed when PhpStorm checks are run

## Transport model

Alpha 8.1 keeps the existing file-based context transport:

- Chrome: `Chrome Extension -> Native Messaging -> BrowserBridge -> context.json` (`native->json`)
- PhpStorm: `Plugin -> context-*.json` (`json`)
- Codex: Agent-side diagnostic probe (`internal-probe`)

There is no ContextReceiver/localhost HTTP/Named Pipe migration in this version.

## Automated checks

From the repository root:

```powershell
node --test .\apps\chrome-extension\tests\tab-selection.test.mjs
node --check .\apps\chrome-extension\src\privacy.js
node --check .\apps\chrome-extension\src\tab-context.js
node --check .\apps\chrome-extension\src\tab-selection.js
node --check .\apps\chrome-extension\src\native-bridge.js
node --check .\apps\chrome-extension\src\service-worker.js
node --check .\apps\chrome-extension\popup\popup.js

dotnet build .\apps\windows-agent\WorkTracker.BrowserBridge\WorkTracker.BrowserBridge.csproj -c Release --nologo
.\tools\build-windows-agent.ps1

$exe = Get-ChildItem .\apps\windows-agent\WorkTracker.Agent\bin\Release -Recurse -Filter WorkTracker.Agent.exe | Select-Object -First 1 -ExpandProperty FullName
& $exe --self-test-activity-intelligence
if ($LASTEXITCODE -ne 0) { throw "Context/Activity self-test failed with exit code $LASTEXITCODE" }

.\tools\check-context-integrations.ps1

cd .\apps\api
php artisan test --filter=BrowserContextSyncControllerTest
php artisan migrate --force
cd ..\..
```

The Agent self-test includes Activity Intelligence invariants, common Integration status mapping and deterministic Codex probe path resolution rules.

## Chrome end-to-end tests

1. **Consent default** — install/reload the extension and verify tracking is OFF by default and no browser `context.json` is created before opt-in.
2. **Native host** — enable tracking, open a normal HTTPS page, and verify `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json` is created.
3. **Focus regression** — open the extension popup or inspect the Service Worker so `chrome.windows.getLastFocused()` may report `focused:false`; press the extension's publish/retry action and verify the eligible active tab can still be published.
4. **Last-focused window selection** — with multiple Chrome windows, verify the no-explicit-window publish path uses the active tab from `lastFocusedWindow`; an event carrying a valid `windowId` must query that exact Chrome window.
5. **Desktop application switch** — switch from Chrome to another desktop application. The JSON file may remain as the latest candidate; WorkTracker must not apply Browser Context because the actual foreground process is not Chrome. Return to Chrome and verify the eligible tab is published/refreshed.
6. **Privacy normalization** — open `https://example.com/task/1?token=secret#private`; verify saved `url` contains neither query nor fragment and `path` is `/task/1`.
7. **Context clear on tracking disable** — while a tracked HTTPS tab is active, disable Browser Context from the popup; verify `context.json` is deleted.
8. **Ignored/internal page clear** — publish a normal page, then switch to `chrome://extensions` or another non-HTTP(S) page; verify the previous `context.json` is deleted and no previous host/path remains active.
9. **Incognito clear** — even if the extension is manually allowed in Incognito, switch from a tracked normal tab into Incognito and verify the prior normal-tab context is cleared. Incognito context must never be written or accepted by Laravel.
10. **Excluded-domain clear** — add an excluded domain, activate it after another tracked page, and verify the previous `context.json` is removed rather than reused.
11. **Foreground title defense** — leave or simulate an old browser JSON file, switch Chrome to a different foreground tab title, and verify the Agent refuses the stale/mismatched Browser Context because title matching fails.
12. **Long-tab freshness** — remain on one eligible tab for more than 30 seconds and verify diagnostics marks the Context stale; attribution still requires the actual foreground Chrome title to match.
13. **Tab switch** — switch between two eligible tabs with different path/title and verify the Activity boundary follows Browser Context change.
14. **Integration status** — verify the WorkTracker diagnostics area shows Chrome state, transport `native->json`, age and safe host/path summary.
15. **SQLite** — verify Chrome sessions populate `activity_sessions.browser_context_json`; non-Chrome sessions must keep it NULL.
16. **Sync persistence** — sync and verify Laravel stores `activity_sessions.browser_context` as JSON only for accepted Activities.
17. **Atomic Sync** — intentionally make the Browser extension portion invalid and verify browser-only post-processing cannot leave a partially committed compatibility Rule. The complete Sync request must fail/roll back instead.
18. **Idempotent replay** — resend exactly the same Activity id/version/browser context and verify it succeeds without changing the stored Context.
19. **Replay tamper protection** — resend the same Activity id/version with a different Browser Context and verify the API returns a validation error. Increment the Activity version and verify the changed Context can then be accepted through the normal Sync/conflict path.
20. **Browser Rule replay** — resend the same Browser Rule id/version/type and verify it is idempotent; changing `BrowserHost`/`BrowserPath`/`BrowserTitle` without a version increment must be rejected.
21. **Browser Rule resolution** — create `BrowserHost contains github.com` and `BrowserPath contains /sam12r18/worktracker`; verify the Agent resolves the configured project and existing priority/weight semantics still control the winner.
22. **Browser Context web page** — open `/worktracker/browser-context`, verify recent Context rows render, create a Browser Rule there, then confirm it appears in the Agent after Sync.
23. **Privacy defense-in-depth** — send a crafted browser context containing a query, fragment, URL credentials, control characters, `incognito=true`, or `focused=false`; verify the API rejects it with validation error.
24. **Host/path integrity** — alter `host` or `path` so it no longer matches the normalized URL and verify the API rejects the payload.

## PhpStorm regression tests

1. Open a real project and verify `%LOCALAPPDATA%\WorkTracker\ide\phpstorm\context-*.json` is updated approximately every two seconds.
2. Verify project name/path, active file, Git branch and execution mode are present without source contents.
3. Switch files/tabs inside the same project and verify the Work Event does not split solely because the file changed.
4. Start Debug and a test configuration and verify explicit `debug`/`test` signals remain available; `run` must not automatically mean Development.
5. Verify WorkTracker diagnostics shows PhpStorm state, transport `json`, heartbeat age and safe project/file/mode/branch summary.

## Codex probe tests

1. Start WorkTracker Agent, foreground the Codex Windows app and wait 3–5 seconds.
2. Verify Agent log contains category `context.codex.probe` when the process is recognized.
3. A static window title `Codex` with no stable path signal must remain unresolved/Unknown.
4. If one stable Windows workspace path is exposed through safe window text, diagnostics may show `resolved` and the path as probe evidence, but Alpha 8.1 does not automatically classify a project from this probe yet.
5. If multiple path candidates are visible, state must be `ambiguous` and `ProjectPath` must remain null.
6. Verify diagnostics shows Codex transport `internal-probe` and never displays source contents.

Useful log command:

```powershell
Select-String -Path "$env:LOCALAPPDATA\WorkTracker\logs\agent-*.log" -Pattern "context.codex.probe" | Select-Object -Last 20
```

## Regression invariants

- `ContextHubService` must continue foreground observation when one provider fails.
- Browser/IDE/Codex metadata only enriches observations; it never creates independent tracked time.
- Work Event continuity rules remain unchanged.
- Mutual and multi-project continuity bridges remain additive and are never capped to wall-clock coverage.

## Acceptance

Alpha 8.1 Context Integrations are accepted only when:

- Chrome Extension -> Native Messaging -> local `context.json` works even when popup/DevTools causes `getLastFocused().focused` to be false;
- Chrome privacy normalization and clear behavior for disabled/internal/incognito/excluded contexts remains intact;
- Agent independently rejects foreground-process/title mismatches and exposes Chrome status as `native->json`;
- PhpStorm continues using its working atomic JSON heartbeat and exposes status as `json` without a transport refactor;
- Codex title `Codex` alone never causes project attribution, and the internal probe exposes only safe evidence;
- Agent persists and Syncs accepted Browser Context as before;
- existing PhpStorm Context, Continuity Bridge and additive Effort semantics remain unchanged;
- automated Node, .NET Agent/BrowserBridge and Laravel Browser Context tests pass.
