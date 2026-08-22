# Context Hub — Alpha 8.1

## Decision

The Windows Agent owns a single provider orchestration point for external application context. TrackingEngine must not know how PhpStorm, Chrome, VS Code or future integrations transport their metadata.

## Contract

Every provider implements `IContextProvider`:

```text
ProviderId
EnrichAsync(ForegroundSnapshot)
```

Current providers:

- `phpstorm` — short-lived heartbeat JSON from the PhpStorm plugin.
- `chrome` — sanitized context from the Chrome Native Messaging adapter.

`ContextHubService` runs providers in deterministic order before classification and returns one enriched `ForegroundSnapshot` to TrackingEngine.

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

Provider transports remain intentionally provider-specific. Chrome keeps Native Messaging lifecycle/framing while PhpStorm keeps its local heartbeat transport. Unification happens inside the Agent, not by forcing IDE plugins to emulate browser protocols.

Future integrations such as VS Code, Edge or Visual Studio can implement `IContextProvider` and register in `App.xaml.cs` without adding provider-specific dependencies to TrackingEngine.
