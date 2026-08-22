# WorkTracker Alpha 8.1 — Browser Context Integration

P0 introduces a Chrome Manifest V3 context bridge.

Core rule: **Chrome provides context; Windows Agent owns time.**

## Implemented scope

- Chrome Manifest V3 extension with explicit opt-in.
- Active/focused normal-tab title, host and path enrichment only; no page-body/content-script collection.
- Query string, fragment and URL credentials removed before context leaves Chrome.
- Incognito context ignored by the extension and rejected again by Laravel.
- Native Messaging host (`WorkTracker.BrowserBridge`) writes the current Chrome context atomically under `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json`.
- Windows Agent enriches only foreground Chrome observations and keeps time/idle/session ownership locally.
- Browser Context is stored in SQLite, queued in the existing transactional Outbox and persisted to Laravel as JSON.
- Project rules support `BrowserHost`, `BrowserPath` and `BrowserTitle` while existing score/priority semantics remain unchanged.
- Browser host/path feed the Activity Context key so Activity Type rules can consume browser context without a parallel inference engine.
- Laravel exposes `/worktracker/browser-context` for recent Browser Context observations and Browser Rule management.
- Existing project-rule validation also accepts Browser Rule types so Browser Rules remain editable through normal management flows.

## Privacy contract

Collected: active tab title, normalized host/path/URL, tab/window ids, observation timestamp, browser and extension version.

Never collected: page body, form values, cookies, LocalStorage, passwords, clipboard, request/response bodies, query string, fragment or URL credentials.

Tracking is disabled by default and requires explicit opt-in. Incognito is never tracked in P0.

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
2. Load `apps/chrome-extension` from `chrome://extensions` using **Load unpacked**.
3. Copy the generated Chrome Extension ID.
4. Register the native host:

```powershell
.\tools\install-chrome-native-host.ps1 -ExtensionId "<CHROME_EXTENSION_ID>"
```

5. Restart Chrome and explicitly enable Browser Context from the extension popup.
6. Run Laravel migrations and then the smoke test in `docs/testing/alpha8.1-browser-context-smoke-test.md`.

## Not in P0

Chrome Web Store publication, Edge/Brave distribution, page-content inspection, server-direct extension sync and automatic BrowserHost/BrowserPath learning are deferred to later phases.
