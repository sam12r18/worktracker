# Alpha 8.1 Context Receiver, Chrome, PhpStorm and Codex Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move Chrome and PhpStorm context transport into the existing Windows Agent through a loopback-only authenticated Context Receiver, fix Chrome focus attribution, add unified integration diagnostics, and add a non-classifying Codex Windows context probe.

**Architecture:** `WorkTracker.Agent.exe` owns a Kestrel loopback receiver and an in-memory latest-context store. Chrome keeps Native Messaging but `WorkTracker.BrowserBridge.exe` forwards sanitized context to the Agent instead of writing provider context JSON; the PhpStorm plugin posts the same logical envelope using Java `HttpClient`. `ContextHubService` keeps provider isolation, while providers read the receiver store and apply foreground/PID/title attribution rules before enriching `ForegroundSnapshot`.

**Tech Stack:** .NET 10 / WPF / ASP.NET Core Kestrel, C# `HttpClient`, Chrome Manifest V3 JavaScript, Chrome Native Messaging, Java 21+ `java.net.http.HttpClient`, Gson, PowerShell 5.1-compatible build/diagnostic scripts, existing Laravel 12 sync pipeline.

**Spec:** `docs/superpowers/specs/2026-08-24-context-receiver-codex-chrome-design.md`

## Global Constraints

- Windows Agent remains `net10.0-windows`; no separate Windows Service.
- Receiver binds only to IPv4 loopback `127.0.0.1`; never wildcard/LAN.
- Provider write requests require a session bearer token derived from a DPAPI-protected per-install secret.
- No Laravel/Sanctum token is exposed to Chrome, BrowserBridge, PhpStorm, or Codex probe code.
- Provider payload limit is 64 KiB; unsupported protocol/provider payloads are rejected without logging raw payloads.
- No source-code contents, console contents, debugger variables, cookies, query strings, fragments, credentials, form data, or Incognito context are captured.
- Chrome Native Messaging remains the browser trust adapter.
- PhpStorm 2025.1 through 2026.2 compatibility remains unchanged; release compile floor remains 2025.1 / Java 21.
- Existing Activity Intelligence continuity rules remain unchanged: initial anchor 60s, bridge max 120s, per-project rearm 120s, additive multi-project effort.
- Provider context is metadata enrichment only and never creates time independently.
- Legacy provider context files are not the primary transport; fallback is opt-in during migration and removed after the regression gate.
- All new receiver/provider/bridge/probe failures use structured logging and must not stop foreground tracking.

---

## File Structure

### Windows Agent — receiver and shared context

- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextEnvelope.cs` — protocol-v1 envelope and clear request DTOs.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextMemoryStore.cs` — thread-safe latest-context store keyed by provider + instance.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextReceiverCredentialStore.cs` — per-install DPAPI secret and per-run session token creation.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextReceiverDiscoveryStore.cs` — atomic `%LOCALAPPDATA%\WorkTracker\ipc\context-receiver.json` discovery file containing only loopback endpoint/session token metadata, never activity context.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextReceiverService.cs` — Kestrel lifecycle, auth, validation, size limit, POST/clear endpoints.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextIntegrationSelfTest.cs` — deterministic receiver/store/provider tests invoked from the Agent command line.
- Modify `apps/windows-agent/WorkTracker.Agent/WorkTracker.Agent.csproj` — add `Microsoft.AspNetCore.App` framework reference and Codex probe dependencies later in Task 8.
- Modify `apps/windows-agent/WorkTracker.Agent/App.xaml.cs` — start/stop receiver, inject store into providers, self-test switch.
- Modify `tools/build-windows-agent.ps1` — run context integration self-test after existing Activity Intelligence self-test.

### Browser Native Host and extension

- Create `apps/windows-agent/WorkTracker.BrowserBridge/ContextReceiverClient.cs` — discovery + authenticated POST/clear client.
- Create `apps/windows-agent/WorkTracker.BrowserBridge/BrowserBridgeSelfTest.cs` — sanitizer/discovery/request self-test.
- Modify `apps/windows-agent/WorkTracker.BrowserBridge/Program.cs` — forward sanitized Native Messaging messages to receiver; no normal context file writes.
- Create `apps/chrome-extension/src/tab-selection.js` — pure target-selection helpers.
- Create `apps/chrome-extension/tests/tab-selection.test.mjs` — Node assertions for window/tab selection semantics.
- Modify `apps/chrome-extension/src/service-worker.js` — event-carried `windowId`/`tabId`, no `getLastFocused().focused` gate.
- Modify `apps/chrome-extension/popup/popup.js` — send current active tab/window identity with `publish-now`.
- Modify `.github/workflows/alpha81-browser-context.yml` — run BrowserBridge self-test and Chrome selection tests.

### PhpStorm

- Create `apps/phpstorm-plugin/src/main/java/ir/rayaasun/worktracker/context/WorkTrackerReceiverClient.java` — reads discovery metadata and POSTs protocol-v1 envelope/clear requests.
- Modify `apps/phpstorm-plugin/src/main/java/ir/rayaasun/worktracker/context/WorkTrackerContextPublisherService.java` — receiver-first publish; opt-in legacy file fallback only.
- Modify `apps/phpstorm-plugin/README.md` — receiver transport and troubleshooting.

### Agent providers and diagnostics

- Modify `apps/windows-agent/WorkTracker.Agent/Integrations/Ide/IdeContextBridgeService.cs` — consume `ContextMemoryStore`; keep PID/freshness/ambiguity rules.
- Modify `apps/windows-agent/WorkTracker.Agent/Integrations/Browser/BrowserContextBridgeService.cs` — consume `ContextMemoryStore`; keep foreground Chrome + title match as attribution barrier.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Context/IntegrationStatusService.cs` — unified provider/receiver status projection.
- Modify `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml` — Integrations diagnostics card/tab.
- Modify `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml.cs` — inject/use `IntegrationStatusService`; stop owning concrete transport state.
- Delete `apps/windows-agent/WorkTracker.Agent/MainWindow.BrowserContext.cs` after its behavior is represented by the unified status service.
- Modify `tools/check-context-integrations.ps1` — verify receiver discovery, BrowserBridge/native registration, Chrome/PhpStorm receiver heartbeat, no provider context JSON dependency.

### Codex probe

- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexContextProbeService.cs` — foreground-only diagnostic probe returning the input snapshot unchanged.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexSignalResolver.cs` — pure candidate filtering/ranking, no classification side effects.
- Create `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexProbeStatus.cs` — safe diagnostics state.
- Modify `apps/windows-agent/WorkTracker.Agent/WorkTracker.Agent.csproj` — add `System.Management` and UI Automation references required by the probe.

---

### Task 1: Context Envelope, Memory Store and Credential/Discovery Contract

**Files:**
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextEnvelope.cs`
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextMemoryStore.cs`
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextReceiverCredentialStore.cs`
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextReceiverDiscoveryStore.cs`
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextIntegrationSelfTest.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/App.xaml.cs`

**Interfaces:**
- Produces: `ContextEnvelope`, `ContextClearRequest`, `ContextMemoryStore.Upsert`, `ContextMemoryStore.Clear`, `ContextMemoryStore.GetProviderContexts`, `ContextReceiverCredentialStore.GetOrCreateInstallSecretAsync`, `ContextReceiverDiscoveryStore.WriteAsync`.
- Consumes: existing `LocalDatabase`, `AgentLog`, DPAPI behavior already used by `SyncSettingsStore`.

- [ ] **Step 1: Write failing store/credential tests in `ContextIntegrationSelfTest.RunCoreAsync()`**

```csharp
var store = new ContextMemoryStore();
var now = DateTimeOffset.UtcNow;
var envelope = new ContextEnvelope(
    1,
    "phpstorm",
    "0.1.0-alpha.8.1",
    "phpstorm:101:I:\\worktracker",
    101,
    now,
    JsonDocument.Parse("{\"project_name\":\"worktracker\"}").RootElement.Clone());

store.Upsert(envelope, now);
Assert(store.GetProviderContexts("phpstorm", now, TimeSpan.FromSeconds(12)).Count == 1,
    "fresh phpstorm context must be returned");
Assert(store.GetProviderContexts("phpstorm", now.AddSeconds(20), TimeSpan.FromSeconds(12)).Count == 0,
    "stale phpstorm context must be excluded");
store.Clear("phpstorm", envelope.InstanceId, now.AddSeconds(21));
Assert(store.GetProviderContexts("phpstorm", now.AddSeconds(21), TimeSpan.FromMinutes(1)).Count == 0,
    "clear removes provider instance");
```

Also assert provider id is normalized to lowercase, future timestamps beyond +5 minutes are rejected, and a clear older than the currently stored observation does not erase newer context.

- [ ] **Step 2: Run the new self-test switch and verify RED**

Add only the command-line branch first:

```csharp
if (e.Args.Any(x => string.Equals(x, "--self-test-context-integrations", StringComparison.OrdinalIgnoreCase)))
{
    var failures = await ContextIntegrationSelfTest.RunCoreAsync();
    File.WriteAllLines(
        Path.Combine(Path.GetTempPath(), "worktracker-context-integration-self-test.txt"),
        failures.Count == 0 ? ["PASS: Context integration core"] : failures.Select(x => "FAIL: " + x));
    Environment.ExitCode = failures.Count == 0 ? 0 : 2;
    Shutdown(Environment.ExitCode);
    return;
}
```

Run:

```powershell
cd I:\worktracker
.\tools\build-windows-agent.ps1
$agent = 'I:\worktracker\apps\windows-agent\WorkTracker.Agent\bin\Release\net10.0-windows\WorkTracker.Agent.dll'
dotnet $agent --self-test-context-integrations
```

Expected: build/test fails because the context contract/store classes do not exist yet.

- [ ] **Step 3: Implement the protocol DTOs and monotonic store**

`ContextEnvelope.cs`:

```csharp
public sealed record ContextEnvelope(
    int ProtocolVersion,
    string Provider,
    string ProviderVersion,
    string InstanceId,
    int? ProcessId,
    DateTimeOffset ObservedAtUtc,
    JsonElement Context);

public sealed record ContextClearRequest(
    int ProtocolVersion,
    string Provider,
    string InstanceId,
    DateTimeOffset ObservedAtUtc);
```

`ContextMemoryStore` stores immutable cloned `JsonElement` values in a `ConcurrentDictionary<string, StoredContext>` keyed with `provider + "\n" + instanceId`; reject unsupported protocol, empty ids, timestamps older than one day or more than five minutes in the future; only replace/clear when `ObservedAtUtc >= existing.ObservedAtUtc`.

- [ ] **Step 4: Implement per-install secret and per-run session discovery**

Use device-state key `context_receiver_secret_dpapi`. Generate 32 random bytes once, protect with `ProtectedData.Protect(..., DataProtectionScope.CurrentUser)`, and store Base64. On every Agent start derive a fresh session bearer token:

```csharp
var nonce = RandomNumberGenerator.GetBytes(32);
using var hmac = new HMACSHA256(installSecret);
var sessionToken = Convert.ToBase64String(hmac.ComputeHash(nonce));
```

Write discovery atomically to:

```text
%LOCALAPPDATA%\WorkTracker\ipc\context-receiver.json
```

with only:

```json
{
  "protocol_version": 1,
  "base_url": "http://127.0.0.1:PORT",
  "session_token": "...",
  "agent_pid": 1234,
  "started_at_utc": "..."
}
```

Do not log `session_token`.

- [ ] **Step 5: Run core self-test and verify GREEN**

Run the same self-test command. Expected output file:

```text
PASS: Context integration core
```

- [ ] **Step 6: Commit**

```bash
git add apps/windows-agent/WorkTracker.Agent/Integrations/Context apps/windows-agent/WorkTracker.Agent/App.xaml.cs
git commit -m "feat: افزودن قرارداد و حافظه Context Receiver"
```

---

### Task 2: Loopback-only Authenticated ContextReceiverService

**Files:**
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextReceiverService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/WorkTracker.Agent.csproj`
- Modify: `apps/windows-agent/WorkTracker.Agent/App.xaml.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextIntegrationSelfTest.cs`
- Modify: `tools/build-windows-agent.ps1`

**Interfaces:**
- Consumes: `ContextMemoryStore`, discovery/session credential objects from Task 1.
- Produces: `ContextReceiverService.StartAsync`, `StopAsync`, `BaseUrl`, receiver status; endpoints `POST /v1/context/{provider}` and `POST /v1/context/{provider}/clear`.

- [ ] **Step 1: Add failing HTTP receiver tests**

Extend self-test to start the receiver on port `0` and use `HttpClient`:

```csharp
await using var receiver = await ContextReceiverService.StartForSelfTestAsync(store, "test-secret", ct);
var baseUrl = receiver.BaseUrl;
Assert(baseUrl.StartsWith("http://127.0.0.1:", StringComparison.Ordinal), "receiver must bind IPv4 loopback");

var unauthorized = await client.PostAsync(baseUrl + "/v1/context/phpstorm", Json(envelope), ct);
Assert(unauthorized.StatusCode == HttpStatusCode.Unauthorized, "missing token must be rejected");

using var authorized = new HttpRequestMessage(HttpMethod.Post, baseUrl + "/v1/context/phpstorm");
authorized.Headers.Add("X-WorkTracker-Context-Token", "test-secret");
authorized.Content = Json(envelope);
var accepted = await client.SendAsync(authorized, ct);
Assert(accepted.StatusCode == HttpStatusCode.Accepted, "valid context must be accepted");
```

Also test provider path/body mismatch -> 400, protocol != 1 -> 422, 65 KiB request -> 413, and clear -> 202 + store removal.

- [ ] **Step 2: Run self-test and verify RED**

Expected: missing `ContextReceiverService`/endpoint behavior.

- [ ] **Step 3: Add ASP.NET Core framework reference and Kestrel implementation**

In `.csproj`:

```xml
<ItemGroup>
  <FrameworkReference Include="Microsoft.AspNetCore.App" />
</ItemGroup>
```

Build with `WebApplication.CreateSlimBuilder()`, configure Kestrel:

```csharp
builder.WebHost.ConfigureKestrel(options =>
{
    options.Listen(IPAddress.Loopback, 0);
    options.Limits.MaxRequestBodySize = 64 * 1024;
});
```

Do not call `ListenAnyIP`, `ListenLocalhost`, or bind `::1`; the acceptance assertion is an IPv4 `127.0.0.1` address.

Use fixed-time `CryptographicOperations.FixedTimeEquals` on decoded token bytes. Log only provider, instance id, PID, status/rejection reason and payload byte count under `context.receiver`.

- [ ] **Step 4: Wire receiver lifecycle into `App`**

Startup order:

```text
LocalDatabase -> device identity -> ContextMemoryStore -> credential/session token -> ContextReceiverService.StartAsync -> providers -> TrackingEngine
```

Shutdown order:

```text
TrackingEngine -> SyncEngine -> ContextReceiverService.StopAsync -> discovery file delete -> HttpClient/Tray dispose
```

A receiver startup failure must log `context.receiver` and continue Agent foreground tracking without external enrichment; it must not abort the whole Agent startup.

- [ ] **Step 5: Add self-test execution to build script**

After Activity Intelligence self-test:

```powershell
Write-Host '==> Context integration deterministic self-test'
& dotnet $selfTestDll '--self-test-context-integrations'
if ($LASTEXITCODE -ne 0) { throw "Context integration self-test failed with exit code $LASTEXITCODE." }
```

- [ ] **Step 6: Run build/self-tests and verify GREEN**

```powershell
.\tools\build-windows-agent.ps1
```

Expected: Release build succeeds; both Activity Intelligence and Context integration result files contain PASS.

- [ ] **Step 7: Commit**

```bash
git add apps/windows-agent/WorkTracker.Agent tools/build-windows-agent.ps1
git commit -m "feat: افزودن Context Receiver محلی به Windows Agent"
```

---

### Task 3: BrowserBridge Becomes a Native-Messaging-to-Receiver Adapter

**Files:**
- Create: `apps/windows-agent/WorkTracker.BrowserBridge/ContextReceiverClient.cs`
- Create: `apps/windows-agent/WorkTracker.BrowserBridge/BrowserBridgeSelfTest.cs`
- Modify: `apps/windows-agent/WorkTracker.BrowserBridge/Program.cs`
- Modify: `.github/workflows/alpha81-browser-context.yml`

**Interfaces:**
- Consumes: `%LOCALAPPDATA%\WorkTracker\ipc\context-receiver.json` discovery contract from Task 1/2.
- Produces: receiver envelope provider `chrome`, clear request, Native Messaging ACK/error.

- [ ] **Step 1: Add failing BrowserBridge self-test**

Add `--self-test` path before Native Messaging stdin handling. Test that a sanitized browser snapshot becomes:

```json
{
  "protocol_version": 1,
  "provider": "chrome",
  "provider_version": "0.1.0",
  "instance_id": "chrome:window:12:tab:34",
  "process_id": null,
  "observed_at_utc": "...",
  "context": {
    "browser": "chrome",
    "host": "github.com",
    "path": "/sam12r18/worktracker",
    "title": "sam12r18/worktracker"
  }
}
```

Assert query/fragment never occur in serialized context and clear maps to `/v1/context/chrome/clear`.

- [ ] **Step 2: Run self-test and verify RED**

```powershell
dotnet build .\apps\windows-agent\WorkTracker.BrowserBridge\WorkTracker.BrowserBridge.csproj -c Release
& .\apps\windows-agent\WorkTracker.BrowserBridge\bin\Release\net10.0-windows\WorkTracker.BrowserBridge.exe --self-test
```

Expected: RED because receiver forwarding client is missing.

- [ ] **Step 3: Implement `ContextReceiverClient`**

Read and parse discovery file on each short-lived host invocation. Reject discovery if `base_url` is not `http://127.0.0.1:<port>` or protocol version != 1. Use a 3-second `HttpClient.Timeout`; send header:

```text
X-WorkTracker-Context-Token: <session_token>
```

Return clean errors `agent_unavailable`, `receiver_rejected`, or `discovery_missing`; never print the session token.

- [ ] **Step 4: Replace normal context file writes**

`Program.HandleAsync("context.update")` sanitizes as today, then forwards to receiver. `context.clear` forwards clear. Remove normal creation/deletion of `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json`.

During this migration task only, allow opt-in file fallback when environment variable is exactly:

```text
WORKTRACKER_CONTEXT_LEGACY_FILE_FALLBACK=1
```

Default is receiver-only.

- [ ] **Step 5: Add CI BrowserBridge self-test**

After Build BrowserBridge:

```powershell
& .\apps\windows-agent\WorkTracker.BrowserBridge\bin\Release\net10.0-windows\WorkTracker.BrowserBridge.exe --self-test
if ($LASTEXITCODE -ne 0) { throw 'BrowserBridge self-test failed.' }
```

- [ ] **Step 6: Run self-test and verify GREEN**

Expected: `PASS: BrowserBridge receiver adapter` and exit code 0.

- [ ] **Step 7: Commit**

```bash
git add apps/windows-agent/WorkTracker.BrowserBridge .github/workflows/alpha81-browser-context.yml
git commit -m "feat: انتقال BrowserBridge از JSON به Context Receiver"
```

---

### Task 4: Fix Chrome Focus/Tab Selection Without `lastFocusedWindow`

**Files:**
- Create: `apps/chrome-extension/src/tab-selection.js`
- Create: `apps/chrome-extension/tests/tab-selection.test.mjs`
- Modify: `apps/chrome-extension/src/service-worker.js`
- Modify: `apps/chrome-extension/popup/popup.js`
- Modify: `.github/workflows/alpha81-browser-context.yml`

**Interfaces:**
- Produces: `publish-now` message payload `{ action, windowId, tabId }`.
- Consumes: Chrome `tabs.onActivated`, `tabs.onUpdated`, `windows.onFocusChanged` ids; BrowserBridge unchanged Native Messaging API.

- [ ] **Step 1: Write failing pure selection tests using the observed regression**

`tab-selection.test.mjs`:

```javascript
import assert from 'node:assert/strict';
import { chooseCandidateTab } from '../src/tab-selection.js';

const tabs = [
  { id: 1, windowId: 10, active: true, title: 'Bale Web (14)' },
  { id: 2, windowId: 20, active: true, title: 'GitHub' }
];

assert.equal(chooseCandidateTab(tabs, { windowId: 20, tabId: 2 }).id, 2);
assert.equal(chooseCandidateTab(tabs, { windowId: 10 }).id, 1);
assert.equal(chooseCandidateTab(tabs, { windowId: 999 }), null);
```

This directly models the real condition where multiple Chrome windows each have `active:true`, while `getLastFocused().focused` was false and `tabs.query({active:true,lastFocusedWindow:true})` returned `[]` under DevTools.

- [ ] **Step 2: Run test and verify RED**

```powershell
node .\apps\chrome-extension\tests\tab-selection.test.mjs
```

Expected: module/function missing.

- [ ] **Step 3: Implement explicit event/popup identity flow**

Rules:

```text
tabs.onActivated -> schedule {windowId, tabId}
tabs.onUpdated(active tab) -> schedule {windowId, tabId}
windows.onFocusChanged(valid normal window) -> remember windowId and query active tab for that exact window
WINDOW_ID_NONE -> clear context
popup publish-now -> popup queries {active:true,currentWindow:true} and sends exact windowId/tabId
startup/storage change -> use remembered window if present; otherwise choose only a currently focused normal window from windows.getAll; if none, clear
```

Do not call `chrome.windows.getLastFocused()` in the normal publish path. Do not use `lastFocusedWindow:true` as the primary selector.

- [ ] **Step 4: Keep Agent-side final attribution barrier**

Do not weaken privacy because Extension focus logic changed. The Agent must still require foreground process Chrome and title match before applying receiver context.

- [ ] **Step 5: Run JavaScript checks and verify GREEN**

```powershell
node --check .\apps\chrome-extension\src\service-worker.js
node --check .\apps\chrome-extension\popup\popup.js
node .\apps\chrome-extension\tests\tab-selection.test.mjs
```

Expected: syntax checks exit 0 and selection tests print PASS/exit 0.

- [ ] **Step 6: Add the test to CI and commit**

```bash
git add apps/chrome-extension .github/workflows/alpha81-browser-context.yml
git commit -m "fix: اصلاح تشخیص پنجره و تب فعال Chrome"
```

---

### Task 5: Move PhpStorm Publisher to Context Receiver

**Files:**
- Create: `apps/phpstorm-plugin/src/main/java/ir/rayaasun/worktracker/context/WorkTrackerReceiverClient.java`
- Modify: `apps/phpstorm-plugin/src/main/java/ir/rayaasun/worktracker/context/WorkTrackerContextPublisherService.java`
- Modify: `apps/phpstorm-plugin/README.md`

**Interfaces:**
- Consumes: receiver discovery JSON from Task 1/2.
- Produces: protocol-v1 provider `phpstorm` heartbeat every 2s and best-effort clear on dispose.

- [ ] **Step 1: Add a Java-side preflight/unit seam that fails before receiver client exists**

Make envelope creation a package-visible method:

```java
Map<String, Object> buildEnvelope(Map<String, Object> context) {
    var envelope = new LinkedHashMap<String, Object>();
    envelope.put("protocol_version", 1);
    envelope.put("provider", "phpstorm");
    envelope.put("provider_version", pluginVersion());
    envelope.put("instance_id", "phpstorm:" + ProcessHandle.current().pid() + ":" + stableIdentity());
    envelope.put("process_id", Math.toIntExact(ProcessHandle.current().pid()));
    envelope.put("observed_at_utc", Instant.now().toString());
    envelope.put("context", context);
    return envelope;
}
```

Add build-script source preflight asserting `WorkTrackerReceiverClient.java` exists and `WorkTrackerContextPublisherService` references it; run build to verify RED before implementation.

- [ ] **Step 2: Implement `WorkTrackerReceiverClient` using Java 21 `HttpClient`**

Read `%LOCALAPPDATA%\WorkTracker\ipc\context-receiver.json`, validate loopback base URL, send JSON with:

```java
.header("X-WorkTracker-Context-Token", discovery.sessionToken())
.timeout(Duration.ofSeconds(3))
```

Treat 202 as success. Log rate-limited warnings through IntelliJ `Logger`; never log token/body.

- [ ] **Step 3: Change publisher to receiver-first**

Keep existing metadata collection. Replace `writeAtomically(snapshot)` with:

```java
receiverClient.publish(buildEnvelope(snapshot));
```

Only write the old heartbeat file when environment variable `WORKTRACKER_CONTEXT_LEGACY_FILE_FALLBACK` equals `1`. Default false.

On `dispose()`, send best-effort clear for the current instance and delete any legacy fallback file if it exists.

- [ ] **Step 4: Build plugin against 2025.1 and verify GREEN**

```powershell
.\tools\build-phpstorm-plugin.ps1 `
  -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin"
```

Expected: `compileJava`, `buildPlugin`, `verifyPluginProjectConfiguration` succeed and Alpha 8.1 ZIP is produced.

- [ ] **Step 5: Commit**

```bash
git add apps/phpstorm-plugin
git commit -m "feat: انتقال PhpStorm Context به Receiver محلی"
```

---

### Task 6: Make Agent Providers Consume Receiver Memory, Not Transport Files

**Files:**
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Ide/IdeContextBridgeService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Browser/BrowserContextBridgeService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/App.xaml.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextIntegrationSelfTest.cs`

**Interfaces:**
- Consumes: `ContextMemoryStore` provider snapshots.
- Produces: unchanged `IContextProvider.EnrichAsync` contract consumed by `ContextHubService`.

- [ ] **Step 1: Add failing provider-attribution tests**

In Context self-test, insert two PhpStorm contexts for the same PID with different project identities; verify an ambiguous equal score returns no IDE enrichment. Insert one exact PID/project/window match and verify it enriches. Insert Chrome context and verify:

```text
foreground process != chrome -> BrowserContext null
foreground process == chrome + title mismatch -> null
foreground process == chrome + title match -> context applied
stale receiver context -> null
```

- [ ] **Step 2: Run context self-test and verify RED**

Expected: providers still read files and ignore the injected store.

- [ ] **Step 3: Refactor constructors to accept `ContextMemoryStore`**

PhpStorm provider loads `store.GetProviderContexts("phpstorm", now, FreshnessWindow)` and applies existing PID/window scoring. Chrome provider loads `store.GetProviderContexts("chrome", now, FreshnessWindow)` and applies existing `IsChrome` + `TitlesMatch` barrier.

Keep optional legacy file reader only behind constructor/config flag `legacyFileFallbackEnabled`; App passes `false` by default.

- [ ] **Step 4: Wire the same provider instances everywhere**

`App` creates exactly one `IdeContextBridgeService` and one `BrowserContextBridgeService` using the same `ContextMemoryStore`; no UI code may instantiate a second Browser bridge.

- [ ] **Step 5: Run self-tests and verify GREEN**

```powershell
.\tools\build-windows-agent.ps1
```

Expected: both deterministic suites pass.

- [ ] **Step 6: Commit**

```bash
git add apps/windows-agent/WorkTracker.Agent
git commit -m "refactor: مصرف Context Receiver در Providerهای Agent"
```

---

### Task 7: Unified Integrations Diagnostics UI and Script

**Files:**
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/IntegrationStatusService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml`
- Modify: `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml.cs`
- Delete: `apps/windows-agent/WorkTracker.Agent/MainWindow.BrowserContext.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/App.xaml.cs`
- Modify: `tools/check-context-integrations.ps1`

**Interfaces:**
- Produces: `IntegrationStatusService.GetRows()` returning provider/status/transport/age/safe summary/error.
- Consumes: receiver status + provider statuses + Codex probe status after Task 8.

- [ ] **Step 1: Add failing status projection assertions to Context self-test**

Create status rows from one fresh PhpStorm context and one absent Chrome context. Assert rows show:

```text
PhpStorm | Connected | receiver | age <= 12
Chrome   | Disconnected | native->receiver
```

No status row may contain session token.

- [ ] **Step 2: Run self-test and verify RED**

Expected: `IntegrationStatusService` missing.

- [ ] **Step 3: Implement unified status service and UI**

Add a dedicated `TabItem Header="یکپارچه‌سازی‌ها"` with a read-only grid/list columns:

```text
Provider | وضعیت | انتقال | آخرین دریافت | Context ایمن | خطای آخر
```

Examples:

```text
PhpStorm | متصل | receiver | 2s | WorkTracker / main / TrackingEngine.cs / debug | —
Chrome   | متصل | native→receiver | 1s | github.com/sam12r18/worktracker | —
Codex    | Probe | local probe | — | workspace signal unresolved | —
```

Remove the independent `_browserStatusBridge = new()` pattern. MainWindow receives one `IntegrationStatusService` from App.

- [ ] **Step 4: Rewrite `check-context-integrations.ps1` around receiver transport**

Checks:

```text
Agent running
receiver discovery file exists
base_url is 127.0.0.1
receiver health responds
Chrome extension source exists
Native Messaging registry/manifest/exe/origin valid
BrowserBridge log exists after invocation
PhpStorm ZIP exists
PhpStorm process state
Agent context.receiver/context.phpstorm/context.chrome log entries
legacy provider context JSON is not required
```

Never print `session_token`.

- [ ] **Step 5: Run self-tests and PowerShell diagnostics**

```powershell
.\tools\build-windows-agent.ps1
.\tools\check-context-integrations.ps1
```

Expected: script identifies unavailable live integrations as diagnostic failures/warnings without exposing secrets.

- [ ] **Step 6: Commit**

```bash
git add apps/windows-agent/WorkTracker.Agent tools/check-context-integrations.ps1
git commit -m "feat: افزودن Diagnostics یکپارچه برای Context Providerها"
```

---

### Task 8: Add Codex Windows Context Probe Without Project Guessing

**Files:**
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexProbeStatus.cs`
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexSignalResolver.cs`
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexContextProbeService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/WorkTracker.Agent.csproj`
- Modify: `apps/windows-agent/WorkTracker.Agent/App.xaml.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/IntegrationStatusService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/ContextIntegrationSelfTest.cs`

**Interfaces:**
- Consumes: foreground `ForegroundSnapshot`.
- Produces: unchanged snapshot + safe `CodexProbeStatus`; no production project classification in Alpha 8.1.

- [ ] **Step 1: Write failing pure resolver tests**

Examples:

```csharp
Assert(CodexSignalResolver.Resolve(["Codex"]).WorkspacePath is null,
    "title Codex alone must never resolve a project");
Assert(CodexSignalResolver.Resolve(["I:\\worktracker", "Codex"]).WorkspacePath == "I:\\worktracker",
    "explicit workspace path may be surfaced by the probe");
Assert(CodexSignalResolver.Resolve(["I:\\worktracker", "C:\\projects\\municipal-works"]).WorkspacePath is null,
    "ambiguous workspace candidates must remain unresolved");
```

- [ ] **Step 2: Run context self-test and verify RED**

Expected: Codex resolver/probe types missing.

- [ ] **Step 3: Add Windows-only signal collection**

Add `System.Management` package version matching .NET 10 and UI Automation references. When foreground process name/path identifies Codex, collect only:

```text
process id/name/executable path
Win32_Process command line for that PID and direct children
UI Automation Name/AutomationId strings from a bounded tree walk
child executable names and command-line working-directory-like path candidates
```

Limits:

```text
max UI elements inspected: 250
max candidate string length: 512
max probe frequency for same foreground PID: once per 5 seconds
no source/editor text bodies
```

Only strings that look like filesystem workspace/repository paths, Git branch/repository labels, or explicit workspace labels are passed to `CodexSignalResolver`. Log discovered signal *types* and sanitized candidates under `context.codex.probe`; do not log full automation tree.

- [ ] **Step 4: Register probe as a no-op `IContextProvider`**

`ProviderId => "codex-probe"`; `EnrichAsync` returns the original `ForegroundSnapshot`. It updates probe status only when foreground is Codex. If no stable signal exists, status is `Probe / unresolved`, never a guessed project.

- [ ] **Step 5: Run self-tests and verify GREEN**

```powershell
.\tools\build-windows-agent.ps1
```

Expected: `Codex` title-only case remains unresolved; explicit unique path resolves in probe status only.

- [ ] **Step 6: Commit**

```bash
git add apps/windows-agent/WorkTracker.Agent
git commit -m "feat: افزودن Codex Context Probe برای ویندوز"
```

---

### Task 9: Regression Gate, Manual Integration Smoke Test and Legacy Context File Removal

**Files:**
- Modify: `.github/workflows/alpha81-browser-context.yml`
- Modify: `tools/check-context-integrations.ps1`
- Modify: `docs/testing/alpha8.1-browser-context-smoke-test.md`
- Modify: `docs/architecture/browser-context-bridge.md`
- Modify: `apps/phpstorm-plugin/README.md`
- Modify/Delete legacy fallback branches in:
  - `apps/windows-agent/WorkTracker.Agent/Integrations/Ide/IdeContextBridgeService.cs`
  - `apps/windows-agent/WorkTracker.Agent/Integrations/Browser/BrowserContextBridgeService.cs`
  - `apps/windows-agent/WorkTracker.BrowserBridge/Program.cs`
  - `apps/phpstorm-plugin/src/main/java/ir/rayaasun/worktracker/context/WorkTrackerContextPublisherService.java`

**Interfaces:**
- Produces: receiver-only primary transport with no provider context JSON dependency.

- [ ] **Step 1: Run full automated regression before deleting fallback**

```powershell
cd I:\worktracker
.\tools\build-windows-agent.ps1

dotnet build .\apps\windows-agent\WorkTracker.BrowserBridge\WorkTracker.BrowserBridge.csproj -c Release --nologo
& .\apps\windows-agent\WorkTracker.BrowserBridge\bin\Release\net10.0-windows\WorkTracker.BrowserBridge.exe --self-test

node --check .\apps\chrome-extension\src\privacy.js
node --check .\apps\chrome-extension\src\tab-context.js
node --check .\apps\chrome-extension\src\native-bridge.js
node --check .\apps\chrome-extension\src\service-worker.js
node --check .\apps\chrome-extension\popup\popup.js
node .\apps\chrome-extension\tests\tab-selection.test.mjs

.\tools\build-phpstorm-plugin.ps1 -JavaHome "C:\Program Files\Eclipse Adoptium\jdk-21.0.12+8\bin"

cd .\apps\api
php artisan test --filter=BrowserContextSyncControllerTest
cd ..\..
```

Expected: every command exits 0. Do not remove legacy fallback if any automated regression fails.

- [ ] **Step 2: Perform Chrome receiver smoke test**

With Agent running and real unpacked extension ID registered:

```text
1. Open a normal HTTPS tab and click Send new Context.
2. Popup status becomes Connected.
3. Agent Integrations row shows Chrome / native→receiver / fresh host+path.
4. No `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json` is required for the row to update.
5. Switch between two Chrome windows; event-specific active tab follows the correct window.
6. Open Extension popup/Service Worker DevTools; diagnostic focus changes must not permanently force browser_not_focused.
7. Switch to chrome://extensions, excluded domain, Incognito, or another application; Agent stops applying browser context.
8. Query string/fragment never appear in Agent/Laravel browser context.
```

- [ ] **Step 3: Perform PhpStorm receiver smoke test**

```text
1. Install Alpha 8.1 ZIP and restart PhpStorm.
2. Open WorkTracker and wait <=5 seconds.
3. Agent Integrations row shows PhpStorm / receiver / project/file/branch.
4. No `%LOCALAPPDATA%\WorkTracker\ide\phpstorm\context-*.json` is required.
5. Switch files: same project remains same normalized project context.
6. Start Debug: activity type source remains ide_plugin, confidence 1.0.
7. Start PHPUnit/Pest Test: Testing signal remains explicit.
8. Stop Agent: PhpStorm stays stable and idea.log gets only rate-limited receiver-unavailable warning.
```

- [ ] **Step 4: Perform Codex probe smoke test**

```text
1. Bring Codex to foreground.
2. Integrations row shows Codex / Probe.
3. Logs show available signal types.
4. If only title `Codex` is visible, workspace remains unresolved.
5. If exactly one stable workspace path is exposed, show it only as probe evidence; do not assign a project yet.
```

- [ ] **Step 5: Remove opt-in legacy provider context file fallback after Steps 1-4 pass**

Delete old normal/fallback file read/write code and environment switch `WORKTRACKER_CONTEXT_LEGACY_FILE_FALLBACK` from BrowserBridge, PhpStorm publisher, IDE provider and Browser provider. Keep JSON only for receiver discovery, logs, tests/fixtures, and durable `ide_context`/`browser_context` inside normal activity persistence/sync.

Add regression assertion to `check-context-integrations.ps1` that provider context JSON paths are absent or ignored and receiver is the active transport.

- [ ] **Step 6: Run the full automated regression again after fallback deletion**

Repeat Step 1 exactly. Expected: every command exits 0.

- [ ] **Step 7: Update docs/CI and commit**

CI must run:

```text
Chrome JS syntax + selection tests
Laravel BrowserContext tests
BrowserBridge build + self-test
Windows Agent build + Activity Intelligence self-test + Context integration self-test
```

Commit:

```bash
git add .github tools docs apps
git commit -m "feat: نهایی‌سازی Context Receiver و حذف انتقال JSON قدیمی"
```

---

## Plan Self-Review

- Spec coverage: receiver ownership, loopback binding, authentication, payload limits, Chrome Native Messaging, PhpStorm receiver transport, JSON migration, ContextHub provider isolation, Chrome/PhpStorm attribution, Activity Type behavior, unified diagnostics, structured logging, failure isolation, Codex probe, testing, and final legacy removal are each mapped to explicit tasks.
- Placeholder scan: no `TBD`, `TODO`, or unspecified implementation steps remain.
- Type consistency: `ContextEnvelope`, `ContextMemoryStore`, `ContextReceiverService`, `IntegrationStatusService`, and `CodexContextProbeService` names/signatures are consistent across producer/consumer tasks.
- Chrome regression evidence incorporated: `getLastFocused().focused == false`, multiple `active:true` tabs across windows, and `tabs.query({active:true,lastFocusedWindow:true}) == []` are directly covered by Task 4's event/popup identity design and tests.
