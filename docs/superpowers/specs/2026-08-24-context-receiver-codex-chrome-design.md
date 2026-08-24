# WorkTracker Alpha 8.1 — Context Receiver, Chrome, PhpStorm and Codex

Date: 2026-08-24
Branch: `feature/alpha-8.1-browser-context`
Status: Design approved in principle; implementation starts after review of this specification.

## 1. Goal

Replace provider-specific JSON files as the primary transport for external application context. The existing Windows Agent will own a local Context Receiver because it already runs in the interactive user session, observes foreground windows, owns Activity Intelligence, and is the correct lifetime boundary for desktop integrations.

The design must support:

- PhpStorm structured context.
- Chrome active-tab context through Native Messaging.
- A Codex Windows context probe and, if feasible, a permanent Codex provider.
- Future providers such as Edge, VS Code and Visual Studio without changing `TrackingEngine` for every integration.

## 2. Non-goals

- Do not turn BrowserBridge into a time tracker.
- Do not let plugins or browser extensions sync directly to Laravel.
- Do not expose WorkTracker server tokens to IDE/browser integrations.
- Do not capture source-code contents, console contents, debugger variables, cookies, query strings, fragments, form data or Incognito metadata.
- Do not use a Windows Service running in Session 0 for foreground-context collection.

## 3. Target architecture

```text
PhpStorm Plugin ------------------------\
                                         \
Chrome Extension -> Native Messaging -> BrowserBridge ----\
                                                           \
Codex Context Provider / Probe -----------------------------+--> ContextReceiverService
                                                           /          |
Future Providers ------------------------------------------/           v
                                                                ContextHubService
                                                                      |
                                                                      v
                                                                 TrackingEngine
                                                                      |
                                                                      v
                                                            Activity Intelligence
                                                                      |
                                                                      v
                                                                  Local DB / Sync
                                                                      |
                                                                      v
                                                                    Laravel
```

`TrackingEngine` consumes one enriched `ForegroundSnapshot`; it must not know how Chrome, PhpStorm or Codex transport metadata.

## 4. Context Receiver ownership

`ContextReceiverService` runs inside `WorkTracker.Agent.exe`, not as a Windows Service.

Reasons:

1. The Agent already runs in the logged-in user's interactive session.
2. Foreground process/window attribution belongs to that same session.
3. A Windows Service would run in Session 0 and create unnecessary IPC, lifecycle and permission complexity.
4. The Agent already owns logging, diagnostics, local identity and shutdown behavior.

The receiver starts after the local database and device identity are initialized and stops with the Agent.

## 5. Local transport

Primary transport: loopback HTTP bound only to `127.0.0.1` on an Agent-owned local port.

Requirements:

- Never bind to `0.0.0.0`, LAN interfaces or IPv6 wildcard addresses.
- Use protocol versioning.
- Require a per-install local secret for non-browser providers.
- Limit request body size.
- Validate provider id and schema.
- Rate-limit malformed/rejected traffic.
- Structured logs under `context.receiver`.
- No WorkTracker Laravel/Sanctum token is shared with integrations.

The per-install receiver settings are stored in the Agent's protected local configuration. The secret must not be written to normal diagnostic logs.

### 5.1 BrowserBridge authentication

Chrome cannot call the receiver directly with the required trust boundary. The extension continues to use Chrome Native Messaging:

```text
Chrome Extension -> WorkTracker.BrowserBridge.exe -> ContextReceiverService
```

BrowserBridge is a short-lived adapter. It receives Native Messaging frames, sanitizes browser metadata, forwards the sanitized envelope to the Agent receiver, returns an acknowledgement to Chrome, and exits.

BrowserBridge obtains receiver connection details from Agent-owned local configuration. It never stores Laravel credentials.

### 5.2 PhpStorm authentication

The PhpStorm plugin posts its protocol envelope to the local receiver using the per-install local secret. If the Agent is unavailable, the plugin fails silently from the user's editing perspective but logs a rate-limited warning to `idea.log`.

## 6. Context Envelope v1

All providers use one logical envelope even when their provider-specific payload differs.

```json
{
  "protocol_version": 1,
  "provider": "phpstorm",
  "provider_version": "0.1.0-alpha.8.1",
  "instance_id": "phpstorm:17384:I:\\worktracker",
  "process_id": 17384,
  "observed_at_utc": "2026-08-24T12:00:00Z",
  "context": {}
}
```

Common fields:

- `protocol_version`
- `provider`
- `provider_version`
- `instance_id`
- `process_id` when available
- `observed_at_utc`
- provider-specific `context`

The receiver stores the latest accepted context in memory with freshness metadata. Durable activity records are written only by the normal Tracking/Repository pipeline.

## 7. Provider payloads

### 7.1 PhpStorm

Allowed metadata:

- project name
- project path
- current file name/path
- Git branch
- execution mode: `idle`, `run`, `debug`, `test`
- run configuration name/type
- IDE build
- plugin version
- PhpStorm process id

No source contents are transmitted.

### 7.2 Chrome

Allowed metadata after sanitization:

- browser name
- extension version
- title
- scheme limited to HTTP/HTTPS
- host
- normalized path
- tab id
- window id
- observed timestamp

Must remove/reject:

- query string
- fragment
- username/password
- Incognito
- unsupported schemes
- excluded domains
- unfocused/non-active contexts when attribution cannot be proven

### 7.3 Codex

Codex must not be classified only by Window Title because the title can remain simply `Codex` across different projects.

Alpha 8.1 first introduces a diagnostic `CodexContextProbe`, not a production classifier. The probe investigates, in order:

1. foreground Codex process and PID;
2. process tree and command line metadata available to the current user;
3. Windows UI Automation tree for workspace/repository/project labels;
4. child processes and working-directory clues that are stable and privacy-safe;
5. other documented/public integration surfaces if available.

The probe must log which signals exist and their stability without collecting source contents.

A permanent `CodexContextProvider` is implemented only if at least one stable project/workspace signal can be attributed to the foreground Codex instance. Reading undocumented/private Codex databases or internal files is not the primary strategy.

## 8. Chrome focus bug

The current extension can report `browser_not_focused` too aggressively because publishing depends on `chrome.windows.getLastFocused()` and `windowInfo.focused` before querying the active tab.

The corrected flow is:

1. react to tab/window events;
2. determine the most relevant last-focused Chrome window/tab from extension context;
3. build a candidate context only for a normal active HTTP/HTTPS tab;
4. publish to BrowserBridge;
5. keep the Agent-side foreground-process/title check as the final attribution barrier.

The extension must not rely on opening its popup as proof of Chrome focus because the popup/devtools can alter focus state during diagnostics.

## 9. JSON migration

Current paths:

- `%LOCALAPPDATA%\\WorkTracker\\ide\\phpstorm\\context-*.json`
- `%LOCALAPPDATA%\\WorkTracker\\browser\\chrome\\context.json`

Migration policy:

1. Receiver transport becomes primary.
2. PhpStorm and Chrome retain file transport for one compatibility window only if explicitly enabled as fallback.
3. Diagnostics distinguish `receiver` from `legacy_file` transport.
4. After receiver reliability is proven in Alpha 8.1 regression, legacy JSON transport is removed.

JSON may remain for logs or test fixtures, but not as the normal provider-to-Agent IPC mechanism.

## 10. ContextHub behavior

`IContextProvider` remains the Agent-side abstraction.

Providers query the receiver's in-memory context store instead of independently reading transport files. Provider failure remains isolated:

- log provider id, process id/name and failure metadata;
- continue foreground observation;
- propagate requested cancellation;
- never stop time tracking because one context provider is unavailable.

Provider order remains deterministic.

## 11. Attribution rules

External context enriches a foreground observation; it never creates time by itself.

PhpStorm:

- Prefer matching PID.
- Then use project/window evidence only when unambiguous.
- File changes inside the same project do not split a Work Event.

Chrome:

- Require foreground process = Chrome.
- Match active context against the actual foreground Chrome window/title as a second attribution barrier.
- Different titles may trigger reclassification even when host/path are equal.

Codex:

- Require foreground process = Codex.
- Project attribution requires a stable probe-derived signal.
- Ambiguous project evidence returns Unknown; do not guess.

## 12. Activity Type behavior

Project classification and Activity Type classification remain separate.

PhpStorm explicit signals:

- `debug` -> Debugging with source `ide_plugin`, confidence 1.0.
- `test` -> Testing with source `ide_plugin`, confidence 1.0.
- `run` is not automatically Development.
- otherwise use Activity Type rules, project default, then Unknown.

Chrome and Codex do not invent activity types solely from application name.

## 13. Diagnostics UI

The Windows Agent receives one `Integrations` diagnostics card with one row per provider.

Example:

```text
Integrations

PhpStorm  Connected  receiver  2s ago
  WorkTracker / main / TrackingEngine.cs / debug

Chrome    Connected  native->receiver  1s ago
  github.com/sam12r18/worktracker

Codex     Probe       foreground detected
  workspace signal: not resolved
```

Show:

- provider
- connected/disconnected/stale/probe state
- transport
- last heartbeat age
- safe context summary
- last error summary

Do not display secrets.

## 14. Logging

Structured categories:

- `context.receiver`
- `context.hub`
- `context.phpstorm`
- `context.chrome`
- `context.codex.probe`
- `browser.native`

Important events:

- receiver started/stopped
- provider accepted/rejected
- schema/protocol rejection
- stale context
- attribution match/mismatch
- provider exception
- Native Messaging forward/ack failure
- Codex probe signal discovery

Secrets and full sensitive URLs must never be logged.

## 15. Failure behavior

Agent unavailable:

- PhpStorm logs a rate-limited warning and continues normally.
- BrowserBridge returns a clean Native Messaging error; extension displays `Agent unavailable`.

Malformed payload:

- receiver rejects with 4xx and logs reason without raw sensitive payload.

Stale context:

- provider does not enrich the foreground observation.

Receiver unavailable during Agent shutdown/startup:

- no raw activity is lost; only enrichment may be absent for that observation.

## 16. Testing strategy

### Receiver

- binds to loopback only;
- rejects missing/invalid secret;
- rejects unsupported protocol/provider;
- enforces body-size limit;
- stores latest accepted context in memory;
- expires stale context;
- isolates provider failures.

### Chrome

- real unpacked extension id registered in Native Messaging manifest;
- valid HTTPS tab reaches BrowserBridge and receiver;
- query/fragment removed;
- Incognito/excluded/internal pages rejected/cleared;
- switching tabs updates classification;
- opening popup does not permanently force `browser_not_focused`;
- Agent-side title/foreground check prevents stale attribution.

### PhpStorm

- plugin posts heartbeat through receiver;
- project/file/branch metadata arrives;
- file switches in one project do not split Work Event;
- Debug/Test signals remain explicit;
- Agent unavailable does not affect IDE stability.

### Codex

- probe identifies foreground Codex process;
- records which candidate project signals are available;
- never infers a project from title `Codex` alone;
- ambiguous/no signal remains Unknown.

### Regression

- Work Event continuity behavior is unchanged;
- mutual and multi-project bridges remain additive;
- Sync/ACK behavior is unchanged;
- Browser/IDE context remains metadata enrichment, not an independent timer.

## 17. Delivery sequence

1. Add receiver contract and in-memory store inside Windows Agent.
2. Add receiver unit/self-tests.
3. Change BrowserBridge from file writer to receiver forwarder; retain optional legacy fallback temporarily.
4. Fix Chrome active-window/tab selection and diagnostics.
5. Change PhpStorm publisher from file heartbeat to receiver heartbeat; retain optional legacy fallback temporarily.
6. Change Agent providers to consume the receiver store.
7. Add unified Integrations diagnostics UI.
8. Add Codex diagnostic probe.
9. Run Alpha 8.1 regression and only then remove legacy JSON transport.

## 18. Acceptance criteria

Alpha 8.1 Context integration is ready when:

- Chrome and PhpStorm enrich activities without JSON being the primary IPC transport;
- Chrome Native Messaging reports connected on a focused supported tab;
- PhpStorm structured context is visible in Agent diagnostics;
- context provider failure never stops foreground time tracking;
- receiver is loopback-only and authenticated where applicable;
- no Laravel token is exposed to integrations;
- Codex probe produces enough evidence to decide whether a stable production provider is feasible;
- all existing Activity Intelligence/continuity regression tests remain green.
