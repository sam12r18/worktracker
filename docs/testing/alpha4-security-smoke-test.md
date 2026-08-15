# Alpha 4.1 public-host security smoke test

Run these against an HTTPS staging host before production.

1. Request `/worktracker` while logged out → must redirect to host login / be denied by `auth`.
2. Log in with an account not in `WORKTRACKER_ADMIN_EMAILS` → `/worktracker` must return 403.
3. Log in with an allowed account → dashboard loads.
4. Create a Device Token for the exact Device UUID shown by Windows Agent.
5. Use that token with `GET /api/v1/reports/daily` → must return 403.
6. Use that token with `POST /api/v1/devices` for a different UUID → must return 403.
7. Use that token with its bound UUID for register + sync → must succeed.
8. Create an Admin Read token. GET report/operations APIs → succeeds; project POST/PATCH/DELETE and conflict resolve → 403.
9. Create an Admin Write token. Administrative writes → succeed subject to user ownership validation.
10. Revoke a token in dashboard. Re-use it → 401.
11. Revoke a Device record. Even with an otherwise valid device token, next Sync → 403.
12. Production HTTP request (not HTTPS) → rejected when `WORKTRACKER_REQUIRE_HTTPS=true`.
13. Verify token values do not appear in Laravel logs or browser URLs.
14. Verify CSRF rejection by submitting a WorkTracker dashboard POST without a valid CSRF token.
