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
4. **Incognito** — even if the extension is manually allowed in Incognito, verify Incognito context is not written or accepted by the Laravel adapter.
5. **Foreground matching** — remain on one tab for more than 30 seconds and verify the Agent keeps the context only while the Chrome window title still matches.
6. **Tab switch** — switch between two tabs with different paths and verify the Activity boundary follows the browser path change.
7. **SQLite** — verify Chrome sessions populate `activity_sessions.browser_context_json`; non-Chrome sessions must keep it NULL.
8. **Sync persistence** — sync and verify Laravel stores `activity_sessions.browser_context` as JSON only for accepted Activities.
9. **Atomic Sync** — intentionally make the Browser extension portion invalid and verify the browser-only post-processing cannot leave a partially committed `Keyword` compatibility Rule. The complete Sync request must fail/roll back instead.
10. **Idempotent replay** — resend exactly the same Activity id/version/browser context and verify it succeeds without changing the stored Context.
11. **Replay tamper protection** — resend the same Activity id/version with a different Browser Context and verify the API returns a validation error. Increment the Activity version and verify the changed Context can then be accepted through the normal Sync/conflict path.
12. **Browser Rule replay** — resend the same Browser Rule id/version/type and verify it is idempotent; changing `BrowserHost`/`BrowserPath`/`BrowserTitle` without a version increment must be rejected.
13. **Browser Rule resolution** — create `BrowserHost contains github.com` and `BrowserPath contains /sam12r18/worktracker`; verify the Agent resolves the configured project and existing priority/weight semantics still control the winner.
14. **Browser Context web page** — open `/worktracker/browser-context`, verify recent Context rows render, create a Browser Rule there, then confirm it appears in the Agent after Sync.
15. **Privacy defense-in-depth** — send a crafted browser context containing a query, fragment, URL credentials, control characters, `incognito=true`, or `focused=false`; verify the API rejects it with validation error.
16. **Host/path integrity** — alter `host` or `path` so it no longer matches the normalized URL and verify the API rejects the payload.
17. **Regression** — run existing Activity Intelligence deterministic tests and verify PhpStorm Context Bridge still works.
18. **Accounting invariant** — verify overlapping Activities remain additive and Browser Context does not cap Effort to wall-clock Coverage.

## Acceptance

Alpha 8.1 P0 is accepted only when:

- Chrome Extension → Native Messaging → local `context.json` works;
- Agent persists `browser_context_json` into SQLite and Outbox;
- Laravel accepts and persists only privacy-normalized Context for accepted Activity versions;
- BrowserHost/BrowserPath/BrowserTitle rules round-trip through Sync and resolve correctly;
- existing PhpStorm Context, Continuity Bridge and additive Effort semantics remain unchanged.
