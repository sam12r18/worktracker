# Browser Context Bridge — Alpha 8.1

## Decision

Chrome Extension is a **context provider**, not a time tracker. Time ownership remains in the Windows Agent: foreground observation, idle detection, session boundaries, additive effort semantics, Continuity Bridge, local SQLite persistence and sync/outbox.

## Flow

```text
Chrome MV3 Extension
  -> chrome.runtime.sendNativeMessage()
  -> WorkTracker.BrowserBridge.exe
  -> %LOCALAPPDATA%\WorkTracker\browser\chrome\context.json
  -> BrowserContextBridgeService
  -> ForegroundSnapshot.BrowserContext
  -> Project / Activity Type classification
  -> ActivitySession.browser_context_json
  -> Sync payload browser_context
  -> Laravel activity_sessions.browser_context
```

## Privacy contract

Collected: active tab title, host, path, normalized URL without query/fragment/credentials, tab/window ids, observation timestamp and extension version.

Never collected: page body, form values, cookies, LocalStorage, passwords, clipboard, request/response bodies or arbitrary content-script data.

Tracking is opt-in and disabled by default. Incognito is ignored.

## Context freshness

- <= 30 seconds: context is accepted as fresh.
- > 30 seconds: context is accepted only if the current foreground Chrome window title still matches the recorded tab title.
- > 12 hours: context is rejected.

Laravel also rejects Incognito context and browser URLs that still contain query, fragment or URL credentials as defense in depth.
