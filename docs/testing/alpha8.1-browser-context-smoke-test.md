# Alpha 8.1 — Browser Context Smoke Test

## Preconditions

- Chrome installed
- .NET 10 SDK installed
- WorkTracker Agent builds/runs
- Laravel migration executed
- Extension loaded unpacked from `apps/chrome-extension`
- Native Messaging host registered with the actual Extension ID

## Automated checks

From the repository root:

```powershell
node --check .\apps\chrome-extension\src\privacy.js
node --check .\apps\chrome-extension\src\tab-context.js
node --check .\apps\chrome-extension\src\native-bridge.js
node --check .\apps\chrome-extension\src\service-worker.js
node --check .\apps\chrome-extension\popup\popup.js

dotnet build .\apps\windows-agent\WorkTracker.BrowserBridge\WorkTracker.BrowserBridge.csproj -c Release
.\tools\build-windows-agent.ps1

cd .\apps\api
php artisan test --filter=BrowserContextSyncControllerTest
php artisan migrate --force
cd ..\..
```

The Laravel test verifies privacy normalization, Incognito/focus rejection, host/path integrity and the Browser Rule compatibility mapping before the end-to-end smoke test is run.

## End-to-end tests

1. **Consent default** — install the extension and verify tracking is OFF by default and no browser `context.json` is created before opt-in.
2. **Native host** — enable tracking, open a normal HTTPS page, and verify `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json` is created.
3. **Privacy normalization** — open `https://example.com/task/1?token=secret#private`; verify saved `url` contains neither query nor fragment and `path` is `/task/1`.
4. **Context clear on tracking disable** — while a tracked HTTPS tab is active, disable Browser Context from the popup; verify `context.json` is deleted and the Agent header changes to `Chrome Context: —`.
5. **Context clear on browser blur** — with a tracked tab active, switch to another desktop application; verify `context.json` is deleted. Return to Chrome and verify the current eligible tab is published again.
6. **Ignored/internal page clear** — publish a normal page, then switch to `chrome://extensions` or another non-HTTP(S) page; verify the previous `context.json` is deleted and no previous host/path remains active.
7. **Incognito clear** — even if the extension is manually allowed in Incognito, switch from a tracked normal tab into Incognito and verify the prior normal-tab context is cleared. Incognito context must never be written or accepted by Laravel.
8. **Excluded-domain clear** — add an excluded domain, activate it after another tracked page, and verify the previous `context.json` is removed rather than reused.
9. **Foreground title defense** — prevent/interrupt Native Messaging temporarily so a stale file cannot be cleared, switch Chrome to another title, and verify the Agent refuses the stale Browser Context because foreground title matching fails.
10. **Long-tab freshness** — remain on one eligible tab for more than 30 seconds and verify the Agent marks the Context stale for diagnostics but still uses it only while the foreground Chrome title continues to match.
11. **Tab switch** — switch between two eligible tabs with different paths and verify the Activity boundary follows the Browser Context change.
12. **Agent status** — with a valid Context, verify the WorkTracker window header shows `Chrome: <host><path>`; after clear, verify it returns to `Chrome Context: —`.
13. **SQLite** — verify Chrome sessions populate `activity_sessions.browser_context_json`; non-Chrome sessions must keep it NULL.
14. **Sync persistence** — sync and verify Laravel stores `activity_sessions.browser_context` as JSON only for accepted Activities.
15. **Atomic Sync** — intentionally make the Browser extension portion invalid and verify the browser-only post-processing cannot leave a partially committed `Keyword` compatibility Rule. The complete Sync request must fail/roll back instead.
16. **Idempotent replay** — resend exactly the same Activity id/version/browser context and verify it succeeds without changing the stored Context.
17. **Replay tamper protection** — resend the same Activity id/version with a different Browser Context and verify the API returns a validation error. Increment the Activity version and verify the changed Context can then be accepted through the normal Sync/conflict path.
18. **Browser Rule replay** — resend the same Browser Rule id/version/type and verify it is idempotent; changing `BrowserHost`/`BrowserPath`/`BrowserTitle` without a version increment must be rejected.
19. **Browser Rule resolution** — create `BrowserHost contains github.com` and `BrowserPath contains /sam12r18/worktracker`; verify the Agent resolves the configured project and existing priority/weight semantics still control the winner.
20. **Browser Context web page** — open `/worktracker/browser-context`, verify recent Context rows render, create a Browser Rule there, then confirm it appears in the Agent after Sync.
21. **Privacy defense-in-depth** — send a crafted browser context containing a query, fragment, URL credentials, control characters, `incognito=true`, or `focused=false`; verify the API rejects it with validation error.
22. **Host/path integrity** — alter `host` or `path` so it no longer matches the normalized URL and verify the API rejects the payload.
23. **Regression** — run existing Activity Intelligence deterministic tests and verify PhpStorm Context Bridge still works.
24. **Accounting invariant** — verify overlapping Activities remain additive and Browser Context does not cap Effort to wall-clock Coverage.

## Acceptance

Alpha 8.1 P0 is accepted only when:

- Chrome Extension → Native Messaging → local `context.json` works;
- the active context is actively cleared on disable, blur, ignored/internal pages, excluded domains and Incognito;
- the Agent independently rejects stale/mismatched Browser Context and exposes Chrome status in the WPF header;
- Agent persists `browser_context_json` into SQLite and Outbox;
- Laravel accepts and persists only privacy-normalized Context for accepted Activity versions;
- BrowserHost/BrowserPath/BrowserTitle rules round-trip through Sync and resolve correctly;
- existing PhpStorm Context, Continuity Bridge and additive Effort semantics remain unchanged.
