# WorkTracker Alpha 8.1 — Browser Context Integration

P0 introduces a Chrome Manifest V3 context bridge.

Core rule: **Chrome provides context; Windows Agent owns time.**

## Implemented scope

- Chrome Manifest V3 extension with explicit opt-in.
- Active-tab title, host and path enrichment only; no page-body/content-script collection.
- Query string, fragment and URL credentials removed before context leaves Chrome.
- Incognito context ignored by the extension and rejected again by the Laravel adapter.
- Native Messaging host (`WorkTracker.BrowserBridge`) writes the current Chrome context atomically under `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json`.
- Windows Agent enriches only foreground Chrome observations and keeps time/idle/session ownership locally.
- Browser Context is stored in SQLite, queued in the existing transactional Outbox and persisted to Laravel as JSON.
- Project rules support `BrowserHost`, `BrowserPath` and `BrowserTitle` while existing score/priority semantics remain unchanged.
- Browser host/path feed the Activity Context key so Activity Type rules can consume browser context without a parallel inference engine.

## Privacy contract

Collected: active tab title, normalized host/path/URL, tab/window ids, observation timestamp, browser and extension version.

Never collected: page body, form values, cookies, LocalStorage, passwords, clipboard, request/response bodies, query string, fragment or URL credentials.

Tracking is disabled by default and requires explicit opt-in. Incognito is never tracked in P0.

## Compatibility

The Laravel `BrowserContextSyncController` is a narrow protocol adapter in front of the established SyncController. It validates the Alpha 8.1 fields, delegates authentication/conflicts/projection to the existing pipeline, and persists only context belonging to accepted Activities. This avoids changing the stable Sync core during P0.

## Not in P0

Chrome Web Store publication, Edge/Brave distribution, page-content inspection, server-direct extension sync and automatic browser-rule learning are deferred to later phases.
