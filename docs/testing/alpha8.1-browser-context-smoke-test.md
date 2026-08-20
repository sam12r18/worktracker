# Alpha 8.1 — Browser Context Smoke Test

1. Install extension and verify tracking is OFF by default.
2. Enable tracking and verify `%LOCALAPPDATA%\WorkTracker\browser\chrome\context.json` is created.
3. Open `https://example.com/task/1?token=secret#private`; verify saved URL has no query/fragment and path is `/task/1`.
4. If extension is manually allowed in Incognito, verify Incognito context is not written/applied.
5. Stay on one tab for >30 seconds; Agent must retain context while title matches.
6. Switch between tabs with different paths; Activity session boundary should follow path change.
7. Verify `activity_sessions.browser_context_json` is populated only for Chrome activities.
8. Trigger sync and verify Laravel stores `browser_context` JSON.
9. Test `BrowserHost contains github.com` and a more specific `BrowserPath contains /sam12r18/worktracker` rule.
10. Re-run Activity Intelligence and PhpStorm Context regression tests; additive/overlap semantics must remain unchanged.
