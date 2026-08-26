# WorkTracker Alpha 8.1 — JSON Context Providers Design

Date: 2026-08-26
Branch: `feature/alpha-8.1-browser-context`
Status: Approved

## Goal

Keep the existing provider-specific JSON transport because it is already working for PhpStorm and is simple to extend, while preserving `IContextProvider`/`ContextHubService` as the architectural boundary between external integrations and `TrackingEngine`.

## Architecture

```text
PhpStorm Plugin -> atomic context-*.json -> IdeContextBridgeService --\
                                                                  \
Chrome Extension -> Native Messaging -> BrowserBridge -> context.json -> BrowserContextBridgeService ---> ContextHubService ---> TrackingEngine
                                                                  /
CodexContextProbe / future CodexContextProvider ------------------/
```

`TrackingEngine` must not know transport details. External context enriches a foreground observation and never creates time by itself.

## Transport decision

Do not implement `ContextReceiverService`, loopback HTTP, Named Pipe transport, local receiver secrets, or JSON-to-receiver migration in Alpha 8.1.

JSON transport remains first-class for external providers with these requirements:

- atomic write via temp file + replace/move;
- explicit `protocol_version`;
- freshness/TTL checks in the Agent;
- provider/process attribution checks;
- structured logs;
- stale-file cleanup;
- no source contents, console contents, debugger variables, cookies, query strings, URL fragments, form values, or Incognito metadata.

## PhpStorm

The existing plugin transport remains unchanged unless a concrete defect is found.

Current path:

`%LOCALAPPDATA%\WorkTracker\ide\phpstorm\context-<pid>-<project-hash>.json`

Agent rules:

- require foreground process `phpstorm64`/`phpstorm`;
- prefer matching PID;
- resolve multiple project contexts only when window evidence is unambiguous;
- context older than the hard TTL is ignored;
- changing file/tab inside the same project must not split a Work Event;
- `debug` and `test` remain explicit Activity Type signals; `run` must not automatically imply Development.

## Chrome

Chrome continues to use Native Messaging because the browser extension cannot write arbitrary local files directly.

```text
Chrome Extension -> ir.rayaasun.worktracker.browser -> WorkTracker.BrowserBridge.exe -> atomic context.json
```

Current path:

`%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json`

The current focus bug is caused by treating `chrome.windows.getLastFocused().focused === false` as proof that Chrome is not the relevant browser window. DevTools and the extension popup can temporarily change focus and cause false negatives.

Corrected selection flow:

1. react to tab/window/storage events;
2. select the active tab from the last-focused Chrome window using `chrome.tabs.query({ active: true, lastFocusedWindow: true })` when no explicit window id is available;
3. when an explicit valid `windowId` is supplied by a Chrome event, query that window directly;
4. build context only for supported normal HTTP/HTTPS tabs and existing privacy rules;
5. send the candidate to BrowserBridge;
6. keep Agent-side foreground Chrome process + title matching as the final attribution barrier;
7. clear persisted browser context when tracking is disabled or the active page is unsupported/incognito/excluded.

The extension must not rely on popup/DevTools focus state as the final attribution decision.

## ContextHub

Keep the existing `IContextProvider` contract and `ContextHubService` failure isolation.

Providers may use different acquisition mechanisms:

- external integration -> JSON file -> provider;
- internal Windows probe -> direct provider logic.

A provider failure must be logged and must never stop foreground tracking.

## Unified diagnostics

Windows Agent gets one integration diagnostics area for:

- PhpStorm: connected/stale/disconnected, heartbeat age, project/file/mode/branch;
- Chrome: connected/stale/disconnected, heartbeat age, host/path;
- Codex: probe state and safe signal summary.

Diagnostics must show transport (`json`, `native->json`, `internal-probe`) and never show secrets or sensitive URL parts.

## Codex

Do not classify Codex by Window Title alone because the Windows app can remain titled simply `Codex` across projects.

Alpha 8.1 adds `CodexContextProbe` inside the Windows Agent. The probe runs only when the foreground process appears to be Codex and collects privacy-safe candidate signals in this order:

1. foreground PID/process metadata;
2. top-level/child window text that Windows exposes;
3. process command line when accessible without elevation;
4. stable filesystem/repository path candidates visible in those signals.

The probe must:

- never read source file contents;
- never read undocumented/private Codex databases as its primary method;
- log discovered signal types under `context.codex.probe`;
- return Unknown when no stable project path can be proven;
- avoid assigning a project from title `Codex` alone.

A production `CodexContextProvider` may be enabled only when the probe resolves a stable path/repository signal with unambiguous attribution.

## Logging

Use structured categories:

- `context.hub`
- `ide.context`
- `browser.context`
- `browser.native`
- `context.codex.probe`

Log state transitions/signature changes rather than every heartbeat.

## Testing

Chrome:

- active tab is selected when `getLastFocused().focused` is false;
- explicit window events select the active tab in that window;
- unsupported/internal/incognito/excluded pages clear context;
- query strings/fragments remain stripped by BrowserBridge;
- Agent rejects stale or title-mismatched context.

PhpStorm:

- existing heartbeat JSON remains valid;
- PID/project matching works;
- stale contexts are ignored;
- Debug/Test semantics remain unchanged.

Codex:

- title `Codex` alone never resolves a project;
- probe logs safe signal availability;
- ambiguous/no signal stays Unknown.

Regression:

- ContextHub provider failure isolation remains intact;
- Work Event continuity and additive multi-project bridge rules remain unchanged;
- context metadata never creates independent time.

## Acceptance criteria

Alpha 8.1 integration work is complete when:

- Chrome reliably produces `context.json` on supported tabs even when popup/DevTools altered the `focused` flag;
- PhpStorm continues producing and consuming its existing JSON heartbeat without transport refactor;
- Agent shows unified integration status for PhpStorm, Chrome and Codex probe;
- Codex probe produces evidence about available project signals without guessing from the static title;
- Agent/BrowserBridge builds and Activity Intelligence regression tests remain green.
