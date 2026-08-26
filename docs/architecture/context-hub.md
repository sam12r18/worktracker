# Context Hub — Alpha 8.1

## Decision

The Windows Agent owns a single provider orchestration point for external application context. `TrackingEngine` must not know how PhpStorm, Chrome, Codex, VS Code or future integrations acquire/transport their metadata.

Alpha 8.1 keeps the existing JSON transport for integrations where it already works. The abstraction boundary is `IContextProvider`, not a forced common IPC transport.

## Contract

Every provider implements `IContextProvider`:

```text
ProviderId
EnrichAsync(ForegroundSnapshot)
```

Current providers:

- `phpstorm` — atomic heartbeat JSON from the PhpStorm plugin (`json`).
- `chrome` — Chrome Native Messaging -> BrowserBridge -> atomic browser JSON (`native->json`).
- `codex-probe` — privacy-safe Agent-side diagnostic probe (`internal-probe`); Alpha 8.1 does not automatically assign a project from it.

`ContextHubService` runs providers in deterministic order before classification and returns one enriched `ForegroundSnapshot` to `TrackingEngine`.

## Failure isolation

Provider failure must never stop foreground capture. The Hub logs provider id, process id/name and exception metadata under `context.hub`, then continues with the next provider. Requested cancellation still propagates normally.

## Ownership boundaries

- Providers own metadata acquisition only.
- ContextHub owns provider orchestration only.
- TrackingEngine owns foreground/idle time and raw session boundaries.
- Activity Intelligence owns project/activity-type classification and Work Event normalization.
- Sync owns durable delivery to Laravel.

No provider may become an independent time tracker or bypass Agent Sync.

## Transport policy

Provider transports remain intentionally provider-specific:

```text
PhpStorm -> atomic context-*.json -> IdeContextBridgeService
Chrome   -> Native Messaging -> BrowserBridge -> context.json -> BrowserContextBridgeService
Codex    -> safe Windows probe -> CodexContextProbe
```

Do not add ContextReceiver HTTP/Named Pipe/Windows Service infrastructure merely to make transport uniform. Revisit transport only if a concrete requirement appears that the file-based model cannot satisfy.

External JSON providers must use atomic write, protocol versioning, freshness/TTL checks, structured logging and stale-file cleanup.

## Diagnostics

The Agent maps provider-specific status into a common `IntegrationStatus` presentation model. Diagnostics expose:

- provider display name;
- state;
- transport;
- heartbeat/probe age when applicable;
- safe context summary;
- safe error/status message.

Diagnostics never expose WorkTracker server tokens, browser query/fragment data, source contents or private application state.

## Future integrations

Future providers such as VS Code, Edge or Visual Studio can implement `IContextProvider` and register in `App.xaml.cs` without adding provider-specific dependencies to `TrackingEngine`.

A future provider may use JSON or an internal probe. Transport should be chosen for that provider's actual constraints rather than redesigned globally.
