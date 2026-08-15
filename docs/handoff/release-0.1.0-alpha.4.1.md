# WorkTracker 0.1.0-alpha.4.1 — Public Host Security Hardening

## Delivered
- Fail-closed authenticated WorkTracker dashboard authorization.
- Token-only API enforcement on top of `auth:sanctum`.
- Least-privilege Sanctum abilities for Device and Admin API clients.
- Exact Device UUID token binding.
- Dashboard token creation and revocation with one-time plaintext display.
- Token expiration defaults.
- Production HTTPS enforcement middleware.
- API throttling.
- Full Device UUID copy action in Windows Agent to simplify secure token provisioning.
- Security ADR, deployment guidance and public-host smoke test.

## Required deployment action
Set `WORKTRACKER_ADMIN_EMAILS`, use HTTPS, ensure `User` uses Sanctum `HasApiTokens`, configure trusted proxies when relevant, then issue a unique Device Token for each Windows Device UUID.

## Security invariant
Never replace scoped tokens with one global shared bearer token. A compromised Windows Agent token must not grant report/admin access and must not work for another Device UUID.
