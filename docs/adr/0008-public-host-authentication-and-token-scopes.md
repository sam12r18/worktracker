# ADR 0008 — Public host authentication and scoped access tokens

Status: Accepted — 2026-08-11

## Context
The Laravel host is publicly reachable. A single broad Sanctum token would let a compromised Windows Agent read reports, manage projects, or resolve conflicts. Session authentication alone also does not define who may enter the WorkTracker operations panel.

## Decision
1. `/worktracker/*` requires Laravel web authentication **and** WorkTracker admin authorization.
2. Production WorkTracker routes require HTTPS.
3. `/api/v1/*` is token-only even when Sanctum also supports SPA/session authentication.
4. Tokens use least-privilege abilities:
   - `device:register`
   - `device:sync`
   - `device:{uuid}` binding a device token to exactly one local Device ID
   - `admin:read`
   - `admin:write`
5. Device tokens cannot read reports, list other devices, mutate projects through CRUD APIs, or resolve conflicts.
6. Admin API mutations require `admin:write`; read endpoints accept `admin:read` or `admin:write`.
7. Device tokens and Admin API tokens should expire and be individually revocable.
8. The dashboard provides token issuance/revocation. Sanctum persists only the token hash; the plaintext token is displayed once.
9. Web state-changing requests remain CSRF-protected by Laravel's `web` middleware.
10. WorkTracker dashboard authorization is fail-closed by default. Configure `WORKTRACKER_ADMIN_EMAILS` (or an existing boolean `is_admin` attribute); broad authenticated-user access must be explicitly enabled.

## Consequences
A stolen Device Token has a much smaller blast radius and is useless for another Device UUID. Deployments must configure trusted proxies correctly when TLS terminates upstream so Laravel can reliably detect HTTPS.
