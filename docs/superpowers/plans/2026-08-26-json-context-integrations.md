# JSON Context Integrations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Chrome context reliable without replacing the working JSON transport, add unified integration diagnostics, and add a privacy-safe Codex context probe.

**Architecture:** Keep `IContextProvider` and `ContextHubService` as the only integration boundary consumed by `TrackingEngine`. PhpStorm and Chrome continue using atomic JSON files; Codex starts as an internal Windows probe and must remain Unknown unless a stable project path can be proven.

**Tech Stack:** .NET 10 WPF, C#, Chrome Manifest V3 JavaScript, Chrome Native Messaging, PowerShell/GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-08-26-json-context-providers-design.md`

## Global Constraints

- Do not implement localhost HTTP, Named Pipe, Windows Service, or any receiver transport in Alpha 8.1.
- Keep current PhpStorm JSON transport unchanged unless required for diagnostics status.
- Chrome context remains Native Messaging -> BrowserBridge -> atomic JSON.
- Do not capture source contents, query strings, URL fragments, cookies, form values, debugger variables, or Incognito context.
- Agent-side foreground process/title matching remains the final Chrome attribution barrier.
- Codex title `Codex` alone must never assign a project.
- Provider errors must never stop foreground time tracking.
- Existing additive multi-project effort/bridge semantics must not change.

---

### Task 1: Fix Chrome active-tab selection without trusting `window.focused`

**Files:**
- Create: `apps/chrome-extension/src/tab-selection.js`
- Create: `apps/chrome-extension/tests/tab-selection.test.mjs`
- Modify: `apps/chrome-extension/src/service-worker.js`
- Modify: `.github/workflows/alpha81-browser-context.yml`

**Interfaces:**
- Produces: `queryForCandidate(chromeApi, preferredWindowId)` returning `Promise<{tab: object|null, windowId: number|null}>`.
- Consumers: `service-worker.js` uses it before `buildTabContext`.

- [ ] **Step 1: Write the failing Node test**

Test with fake Chrome APIs that `lastFocusedWindow:true` is used when no explicit window id exists and that the result is accepted even when a fake last-focused window reports `focused:false`.

- [ ] **Step 2: Run the test to verify RED**

Run: `node --test apps/chrome-extension/tests/tab-selection.test.mjs`
Expected: FAIL because `src/tab-selection.js` does not exist.

- [ ] **Step 3: Implement `tab-selection.js`**

Implementation contract:

```js
export async function queryForCandidate(chromeApi, preferredWindowId = undefined) {
  if (Number.isInteger(preferredWindowId) && preferredWindowId !== chromeApi.windows.WINDOW_ID_NONE) {
    const [tab] = await chromeApi.tabs.query({ active: true, windowId: preferredWindowId });
    return { tab: tab || null, windowId: preferredWindowId };
  }

  const [tab] = await chromeApi.tabs.query({ active: true, lastFocusedWindow: true });
  return { tab: tab || null, windowId: tab?.windowId ?? null };
}
```

Do not check `window.focused` here.

- [ ] **Step 4: Change `service-worker.js` to call the helper**

Keep event-driven publishing and existing privacy/buildTabContext logic. `publishFocusedTab()` must clear with `browser_not_focused` only when no candidate active tab can be found; it must not reject solely because `chrome.windows.getLastFocused().focused` is false.

- [ ] **Step 5: Run test and syntax checks**

Run:

```powershell
node --test apps/chrome-extension/tests/tab-selection.test.mjs
node --check apps/chrome-extension/src/tab-selection.js
node --check apps/chrome-extension/src/service-worker.js
```

Expected: PASS / no syntax errors.

- [ ] **Step 6: Add the Node test to Alpha 8.1 CI**

CI must run the test before PHP/.NET jobs complete.

- [ ] **Step 7: Commit**

Commit message:

`رفع انتخاب تب فعال Chrome بدون وابستگی به focused`

---

### Task 2: Unify integration status in the Windows Agent UI

**Files:**
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Context/IntegrationStatus.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Ide/IdeContextBridgeService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/Integrations/Browser/BrowserContextBridgeService.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/App.xaml.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml`
- Modify: `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml.cs`
- Modify or remove logic from: `apps/windows-agent/WorkTracker.Agent/MainWindow.BrowserContext.cs`

**Interfaces:**
- Produces common UI model:

```csharp
public sealed record IntegrationStatus(
    string ProviderId,
    string DisplayName,
    string State,
    string Transport,
    int? AgeSeconds,
    string Summary,
    string Message);
```

- `IdeContextBridgeService.GetIntegrationStatus()` returns transport `json`.
- `BrowserContextBridgeService.GetIntegrationStatus()` returns transport `native->json`.
- MainWindow receives the same `ideContext` and `browserContext` instances that ContextHub uses; do not create a second Browser bridge only for UI.

- [ ] **Step 1: Add a deterministic self-test assertion for status mapping**

Extend the existing activity/integration self-test entry point or add a pure helper test so connected/stale/disconnected states map predictably without filesystem timing dependence.

- [ ] **Step 2: Verify RED**

Build/self-test must fail because common `IntegrationStatus` methods do not yet exist.

- [ ] **Step 3: Add `IntegrationStatus` and service adapters**

Keep provider-specific status records if other code uses them, but expose common status for UI.

- [ ] **Step 4: Pass `browserContext` into MainWindow**

Replace `_browserStatusBridge = new()` with the actual shared instance from `App.xaml.cs` so UI and Tracking read identical state.

- [ ] **Step 5: Add an `Integrations` diagnostics block**

Display at minimum:

```text
PhpStorm · Connected/Stale/Disconnected · json · age
project / file / mode / branch

Chrome · Connected/Stale/Disconnected · native->json · age
host/path
```

Do not expose full URLs or secrets.

- [ ] **Step 6: Build and run deterministic self-test**

Run:

```powershell
dotnet build .\apps\windows-agent\WorkTracker.Agent\WorkTracker.Agent.csproj -c Release --nologo
$exe = Get-ChildItem .\apps\windows-agent\WorkTracker.Agent\bin\Release -Recurse -Filter WorkTracker.Agent.exe | Select-Object -First 1 -ExpandProperty FullName
& $exe --self-test-activity-intelligence
```

Expected: build succeeds; self-test exits 0.

- [ ] **Step 7: Commit**

Commit message:

`یکپارچه‌سازی وضعیت PhpStorm و Chrome در Diagnostics`

---

### Task 3: Add a privacy-safe Codex context probe

**Files:**
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexContextProbe.cs`
- Create: `apps/windows-agent/WorkTracker.Agent/Integrations/Codex/CodexProbeStatus.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/App.xaml.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml.cs`
- Modify: `apps/windows-agent/WorkTracker.Agent/MainWindow.xaml`
- Modify: `apps/windows-agent/WorkTracker.Agent/Tracking/ActivityIntelligenceSelfTest.cs` or the actual self-test file if named differently.

**Interfaces:**
- Produces:

```csharp
public sealed record CodexProbeStatus(
    bool ForegroundDetected,
    int? ProcessId,
    string State,
    string? ProjectPath,
    string Signal,
    string Message,
    DateTimeOffset ObservedAtUtc);
```

- `CodexContextProbe.ObserveAsync(ForegroundSnapshot snapshot, CancellationToken ct)` returns `CodexProbeStatus`.
- Probe never modifies `ForegroundSnapshot` in Alpha 8.1 unless a stable unambiguous path is proven by a separately reviewed provider change.

- [ ] **Step 1: Write failing pure-signal tests**

Tests must prove:

1. process/title `Codex` with no path => `ProjectPath == null`;
2. a signal string containing one explicit existing-style Windows repo path can be extracted;
3. two different path candidates => ambiguous => `ProjectPath == null`.

Path extraction test input must be synthetic and must not depend on the developer machine filesystem.

- [ ] **Step 2: Verify RED**

Build/self-test fails because Codex probe does not exist.

- [ ] **Step 3: Implement minimal probe**

When foreground process name matches Codex:

- record PID;
- inspect safe top-level window/child window text exposed by Win32 APIs;
- optionally inspect process command line only when accessible without elevation and without adding a privileged dependency;
- extract path candidates only from text already exposed by those signals;
- never read source files or private Codex stores;
- log only signal names and sanitized path candidate metadata under `context.codex.probe`.

If no stable path is found, state is `probe`, not connected/classified.

- [ ] **Step 4: Show Codex row in Integrations diagnostics**

Example:

```text
Codex · Probe · internal-probe
foreground detected · workspace signal unresolved
```

- [ ] **Step 5: Build and run self-tests**

Run the same Release build + self-test command from Task 2.
Expected: exit 0.

- [ ] **Step 6: Commit**

Commit message:

`افزودن Codex Context Probe بدون حدس پروژه از عنوان پنجره`

---

### Task 4: End-to-end Alpha 8.1 verification and docs

**Files:**
- Modify: `tools/check-context-integrations.ps1`
- Modify: `docs/testing/alpha8.1-browser-context-smoke-test.md`
- Modify: `.github/workflows/alpha81-browser-context.yml` if needed after actual verification.

**Interfaces:**
- Diagnostics script reports Chrome JSON, BrowserBridge logs, PhpStorm heartbeat JSON, Agent status prerequisites, and Codex probe/log availability.

- [ ] **Step 1: Update diagnostics script**

Make output clearly identify transports:

- Chrome: `native->json`
- PhpStorm: `json`
- Codex: `internal-probe`

- [ ] **Step 2: Update smoke test**

Include Chrome multi-window/popup/DevTools focus regression, PhpStorm heartbeat check, and Codex Unknown-with-static-title behavior.

- [ ] **Step 3: Run full available verification**

Commands:

```powershell
node --test .\apps\chrome-extension\tests\tab-selection.test.mjs
dotnet build .\apps\windows-agent\WorkTracker.BrowserBridge\WorkTracker.BrowserBridge.csproj -c Release --nologo
.\tools\build-windows-agent.ps1
.\tools\check-context-integrations.ps1
```

Also run Laravel Browser Context tests when PHP dependencies are present:

```powershell
cd .\apps\api
php artisan test --filter=BrowserContextSyncControllerTest
```

- [ ] **Step 4: Inspect GitHub Actions for the branch head**

Do not claim green until branch CI reports success or equivalent local commands return exit 0.

- [ ] **Step 5: Commit**

Commit message:

`تکمیل تست و مستندات Context Integrations در Alpha 8.1`
