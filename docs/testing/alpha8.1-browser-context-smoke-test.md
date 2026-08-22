# Alpha 8.1 — Browser Context Smoke Test

## Preconditions

- Chrome installed
- .NET 10 SDK installed
- WorkTracker Agent builds/runs
- Laravel migration executed
- Extension loaded unpacked from `apps/chrome-extension`
- Native Messaging host registered with the actual Extension ID

## Tests

1. **Consent default** — install the extension and verify tracking is OFF by default and no browser `context.json` is created before opt-in.
2. **Native host** — enable tracking, open a normal HTTPS page, and verify `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json` is created.
3. **Privacy normalization** — open `https://example.com/task/1?token=secret#private`; verify saved `url` contains neither query nor fragment and `path` is `/task/1`.
4. **Incognito** — even if the extension is manually allowed in Incognito, verify Incognito context is not written or accepted by the server adapter.
5. **Foreground matching** — remain on one tab for more than 30 seconds and verify the Agent keeps the context only while the Chrome window title still matches.
6. **Tab switch** — switch between two tabs with different paths and verify the Activity boundary follows the browser path change.
7. **SQLite** — verify Chrome sessions populate `activity_sessions.browser_context_json`; non-Chrome sessions must keep it NULL.
8. **Sync** — sync and verify Laravel stores `activity_sessions.browser_context` as JSON; repeat the same client version and verify the adapter remains idempotent.
9. **Browser rule** — create `BrowserHost contains github.com` and `BrowserPath contains /sam12r18/worktracker`; verify the Agent resolves the configured project and specific priority/weight still controls the winner.
10. **Privacy defense-in-depth** — send a crafted browser context containing a query/fragment or `incognito=true`; verify the API rejects it with validation error.
11. **Regression** — run existing Activity Intelligence deterministic tests and verify PhpStorm Context Bridge still works.
12. **Accounting invariant** — verify overlapping Activities remain additive and Browser Context does not cap Effort to wall-clock Coverage.
