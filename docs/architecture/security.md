# Security Baseline

WorkTracker assumes the Laravel host can be reached from the public internet.

## Web dashboard
- `/worktracker/*` requires Laravel `web` + `auth` middleware.
- WorkTracker adds `EnsureWorkTrackerDashboardAccess`; access is fail-closed unless the authenticated user's email is in `WORKTRACKER_ADMIN_EMAILS`, the User has a truthy `is_admin` attribute, or `WORKTRACKER_ALLOW_ANY_AUTHENTICATED_USER=true` is deliberately configured.
- All dashboard mutations use POST/DELETE forms under the `web` middleware and therefore require CSRF tokens.
- Production routes require HTTPS when `WORKTRACKER_REQUIRE_HTTPS=true` (default).

## API
Every WorkTracker API route requires `auth:sanctum` **and a real Personal Access Token**. A browser/session-authenticated Sanctum request without a token is rejected by `RequireWorkTrackerTokenAbility`.

Token abilities:
- `device:register` — register/update the bound Windows device identity.
- `device:sync` — call `/api/v1/sync`.
- `device:{uuid}` — cryptographically scopes the bearer token's authorization to one Device UUID at the application layer.
- `admin:read` — read devices, projects, activities, reports, operations and conflicts.
- `admin:write` — all admin reads plus project/device mutations and conflict resolution.

Device tokens must carry all three device abilities: `device:register`, `device:sync`, and `device:{uuid}`. Both device registration and sync verify the UUID binding in the controller; possessing `device:sync` alone is not enough.

## Token lifecycle
- Generate/revoke WorkTracker tokens from the authenticated operations dashboard.
- Laravel Sanctum stores Personal Access Tokens hashed; plaintext is shown only once on creation.
- Windows stores its bearer token using DPAPI `CurrentUser`, never plaintext SQLite.
- Prefer short-lived Admin API tokens (default 30 days) and bounded Device Tokens (default 90 days).
- Revoke tokens immediately after suspected disclosure. Device `revoked_at` is a second independent kill switch for Sync.
- Never put tokens in URLs, query strings, logs, screenshots, exception messages or source control.

## Transport
- HTTPS is mandatory in production.
- If a reverse proxy/CDN terminates TLS, configure Laravel trusted proxies correctly. Do not disable HTTPS enforcement just to work around a bad proxy configuration.
- API rate limits are applied independently to registration, sync, reads and writes.

## Ownership and data isolation
- Every API query remains scoped to the authenticated user.
- Server validates that the pushed `device_id` belongs to the authenticated user.
- Re-registering a UUID owned by another user is rejected.
- A device token bound to UUID A cannot register or sync UUID B.
- Future team mode must add organization/project membership authorization rather than relying on user ownership alone.

## Capture privacy
- Never implement keylogging, clipboard capture, screenshots, password capture or page-content capture.
- Never log bearer tokens or sensitive window titles in server/application logs.
