# WorkTracker Alpha 8.1 — Context Integrations

P0 introduces Chrome Browser Context and keeps the existing PhpStorm Context integration under a shared Agent-side provider architecture.

Core rule: **integrations provide context; Windows Agent owns time.**

## Architecture decision

Alpha 8.1 intentionally keeps the proven JSON transport instead of adding a Context Receiver, localhost HTTP server, Named Pipe or Windows Service.

```text
PhpStorm Plugin -> atomic context-*.json -> IdeContextBridgeService --\
                                                                  \
Chrome Extension -> Native Messaging -> BrowserBridge -> context.json -> BrowserContextBridgeService ---> ContextHubService ---> TrackingEngine
                                                                  /
CodexContextProbe ------------------------------------------------/
```

External context only enriches foreground observations. It never creates tracked time by itself.

## Implemented scope

- Chrome Manifest V3 extension with explicit opt-in.
- Eligible active normal-tab title, host and path enrichment only; no page-body/content-script collection.
- Query string, fragment and URL credentials removed before context leaves BrowserBridge.
- Incognito context ignored by the extension and rejected again by Laravel.
- Native Messaging host (`WorkTracker.BrowserBridge`) writes the current Chrome candidate atomically under `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json`.
- Chrome candidate selection no longer treats `getLastFocused().focused=false` as proof that Chrome context is unavailable; it uses the active tab from the explicit event window or `lastFocusedWindow` and leaves final attribution to the Agent.
- PhpStorm keeps its working atomic JSON heartbeat under `%LOCALAPPDATA%\WorkTracker\ide\phpstorm`.
- `ContextHubService` isolates PhpStorm, Chrome and Codex provider failures from foreground tracking.
- Windows Agent enriches only relevant foreground observations and keeps time/idle/session ownership locally.
- Unified WPF integration diagnostics exposes provider state and transport (`json`, `native->json`, `internal-probe`).
- A privacy-safe Codex diagnostic probe observes only safe Windows-exposed signals and never assigns a project from the static title `Codex` alone.
- Browser Context is stored in SQLite, queued in the existing transactional Outbox and persisted to Laravel as JSON.
- Project rules support `BrowserHost`, `BrowserPath` and `BrowserTitle` while existing score/priority semantics remain unchanged.
- Browser host/path feed the Activity Context key so Activity Type rules can consume browser context without a parallel inference engine.
- Laravel exposes `/worktracker/browser-context` for recent Browser Context observations and Browser Rule management.

## Chrome focus model

Extension popup/DevTools can temporarily make Chrome report its last-focused window with `focused=false`. That flag is therefore not used as the final attribution signal.

- event with a valid `windowId` -> query active tab in that window;
- otherwise -> query `{ active: true, lastFocusedWindow: true }`;
- unsupported/incognito/excluded tab -> clear candidate;
- another desktop app in foreground -> the existing JSON candidate may remain, but Agent does not apply it because foreground process/title attribution fails;
- returning to Chrome -> Chrome events refresh the current candidate.

## Privacy contract

Collected: active eligible tab title, normalized host/path/URL, tab/window ids, observation timestamp, browser and extension version; PhpStorm project/file/branch/execution metadata; privacy-safe Codex probe signal names/path candidates when Windows already exposes them.

Never collected: page body, form values, cookies, LocalStorage, passwords, clipboard, request/response bodies, query string, fragment, URL credentials, source contents, debugger variables or private Codex databases.

Chrome tracking is disabled by default and requires explicit opt-in. Incognito is never tracked in P0.

## Sync compatibility and atomicity

`BrowserContextSyncController` is a narrow protocol adapter in front of the established `SyncController`.

It:

1. validates Browser Context and Browser Rule extensions;
2. strips the Alpha 8.1-only fields before delegating to the stable Sync pipeline;
3. persists Browser Context only for accepted Activity ids/versions;
4. restores `BrowserHost` / `BrowserPath` / `BrowserTitle` only for accepted Rule ids/versions;
5. wraps the stable Sync transaction in an outer Laravel transaction so the compatibility `Keyword` representation and the final Browser Rule type cannot be committed separately.

Same-version replays are idempotent. A changed Browser Context or changed Browser Rule type requires the normal Activity/Rule version increment and conflict rules.

## Database

Laravel migration:

`2026_08_22_180000_add_browser_context_to_activity_sessions.php`

Windows SQLite upgrades existing databases with nullable `activity_sessions.browser_context_json`.

## Development install

1. Build `WorkTracker.BrowserBridge` and the Windows Agent.
2. Load `apps/chrome-extension` from `chrome://extensions` using **Load unpacked** or press **Reload** after source changes.
3. Copy the generated Chrome Extension ID.
4. Register the native host:

```powershell
.\tools\install-chrome-native-host.ps1 -ExtensionId "<CHROME_EXTENSION_ID>"
```

5. Restart Chrome and explicitly enable Browser Context from the extension popup.
6. Keep/install the PhpStorm Alpha 8.1 plugin if IDE context is required.
7. Run Laravel migrations and the smoke test in `docs/testing/alpha8.1-browser-context-smoke-test.md`.
8. Run `.\tools\check-context-integrations.ps1` for Chrome/PhpStorm/Codex diagnostics.

## Codex Alpha 8.1 behavior

The Windows Codex app can keep a generic title such as `Codex`, so Window Title alone is never enough for project classification.

`CodexContextProbe` runs inside the Agent only while Codex is observed. It inspects privacy-safe Windows-exposed window text for explicit path candidates, logs state changes under `context.codex.probe`, and reports `probe`, `resolved`, `ambiguous`, or the last-seen state to diagnostics. A resolved probe result is evidence only in Alpha 8.1; automatic Codex project classification is deferred until the signal is proven stable in real usage.

## Not in P0

Chrome Web Store publication, Edge/Brave distribution, page-content inspection, server-direct extension sync, automatic BrowserHost/BrowserPath learning, ContextReceiver/HTTP/Named Pipe transport migration, and automatic Codex project assignment are deferred.
