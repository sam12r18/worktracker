# Browser Context Bridge — Alpha 8.1

## Decision

Chrome Extension is a **context provider**, not a time tracker. Time ownership remains in the Windows Agent: foreground observation, idle detection, session boundaries, additive effort semantics, Continuity Bridge, local SQLite persistence and sync/outbox.

Alpha 8.1 intentionally keeps the existing JSON transport. There is no Context Receiver / localhost HTTP / Named Pipe migration in this version.

## Flow

```text
Chrome MV3 Extension
  -> chrome.runtime.sendNativeMessage()
  -> WorkTracker.BrowserBridge.exe
  -> %LOCALAPPDATA%\WorkTracker\browser\chrome\context.json
  -> BrowserContextBridgeService
  -> ContextHubService
  -> ForegroundSnapshot.BrowserContext
  -> Project / Activity Type classification
  -> ActivitySession.browser_context_json
  -> Sync payload browser_context
  -> Laravel activity_sessions.browser_context
```

The extension never calls Laravel directly and never stores a WorkTracker API token.

## Privacy contract

Collected: eligible active normal-tab title, host, path, normalized URL without query/fragment/credentials, tab/window ids, observation timestamp and extension version.

Never collected: page body, form values, cookies, LocalStorage, passwords, clipboard, request/response bodies or arbitrary content-script data.

Tracking is opt-in and disabled by default. Incognito is ignored.

## Candidate selection and focus

`chrome.windows.getLastFocused().focused` is not used as a final truth signal. Extension popup/DevTools and other transient UI can make Chrome report `focused=false` while the last-focused normal Chrome window still has the relevant active tab.

Selection rules:

- when a Chrome event supplies a valid `windowId`, query `{ active: true, windowId }`;
- otherwise query `{ active: true, lastFocusedWindow: true }`;
- if no candidate tab is available, publish no Browser Context;
- the Windows Agent remains the final attribution barrier and only applies Browser Context to an actual foreground Chrome observation whose title matches the candidate tab.

This prevents the extension's own popup/DevTools from causing false `browser_not_focused` failures without weakening Agent-side attribution.

## Clearing active context

`context.json` is the latest eligible Chrome candidate, not a browsing-history file.

The extension sends Native Messaging action `context.clear` when:

- Browser Context tracking is disabled;
- the selected active tab is Incognito;
- the selected active page is a non-HTTP(S) Chrome/internal page;
- privacy/exclusion settings make the selected tab ineligible;
- no eligible active Chrome candidate can be resolved.

A transient desktop focus change alone does **not** require deleting the file. When another desktop application is foreground, `BrowserContextBridgeService` refuses to enrich it because the foreground process is not Chrome. When Chrome becomes relevant again, its tab/window events refresh the candidate.

`WorkTracker.BrowserBridge` deletes `context.json` for `context.clear`. This prevents a prior eligible tab from being reused after the user moves into an ignored/private Chrome context.

## Attribution and freshness defense

The Windows Agent independently validates the local bridge file even if Native Messaging clear fails:

- Context must use the supported protocol and browser provider.
- Incognito and unfocused Context are rejected.
- Context older than 12 hours is rejected.
- **Every** Browser Context must match the actual foreground Chrome window title before it can enrich a `ForegroundSnapshot`; this is not limited to stale Context.
- Context older than 30 seconds is marked stale for diagnostics/UI, but can still be used during long uninterrupted work only while the foreground title continues to match.

This double barrier avoids stale attribution when switching tabs or entering ignored contexts.

## Context Hub

`BrowserContextBridgeService`, `IdeContextBridgeService` and internal probes implement the common `IContextProvider` interface and are orchestrated by `ContextHubService`.

Providers may use different acquisition mechanisms:

- Chrome: `native->json`;
- PhpStorm: `json`;
- Codex diagnostic probe: `internal-probe`.

A provider exception is isolated and logged; it must never stop foreground time tracking.

## Server defense in depth

Laravel validates Browser Context again before persistence:

- only protocol version 1 / Chrome / focused / non-Incognito Context is accepted;
- URL must be absolute HTTP(S);
- query, fragment and URL credentials are rejected;
- control characters are rejected;
- provided host/path must match the normalized URL;
- same-version replay may not silently change Browser Context.

Browser Context is persisted only for Activity ids/versions accepted by the normal Sync pipeline.

## Browser project rules

Project rules add three explicit context types:

- `BrowserHost`
- `BrowserPath`
- `BrowserTitle`

They use the existing project-rule weight, priority and operator semantics. Browser Rule data round-trips through the normal configuration pull and is applied by the existing Windows `ProjectResolver`.

## Sync atomicity

Alpha 8.1 keeps the existing `SyncController` as the owner of authentication, conflict handling and Work Event projection. `BrowserContextSyncController` is a compatibility adapter for the protocol extension.

The adapter wraps the stable Sync transaction in an outer Laravel transaction. Temporary compatibility conversion of a Browser Rule to `Keyword`, acceptance by the legacy Sync validator, restoration of the exact Browser Rule type, and Browser Context persistence therefore commit atomically or roll back together.
