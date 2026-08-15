# WorkTracker Laravel integration module — alpha.6

`apps/api` is an integration module, **not** a complete standalone Laravel project. Merge it into a normal Laravel host that provides framework/vendor files, database config, authenticated User accounts and Laravel Sanctum.

## Required host capabilities
- Laravel web authentication/login.
- `Laravel\Sanctum\HasApiTokens` on the host User model.
- Sanctum `personal_access_tokens` migration.
- PHP/Laravel requirements documented in `docs/deployment/*`.
- HTTPS in production and correct trusted proxy configuration.
- MariaDB/MySQL/PostgreSQL target validated against WorkTracker migrations before production.

## Integration paths
Copy/merge:
- `app/Models`
- `app/Services`
- `app/Http/Controllers`
- `app/Http/Middleware`
- `app/Providers/WorkTrackerServiceProvider.php`
- `config/worktracker.php`
- `database/migrations`
- `resources/views/layouts/worktracker.blade.php`
- `resources/views/components/worktracker`
- `resources/views/worktracker`
- `routes/worktracker.php`
- `routes/worktracker-api.php`

Register `App\Providers\WorkTrackerServiceProvider` in `bootstrap/providers.php`. Do not duplicate the same WorkTracker routes in unrelated route files.

## Security model
- `/worktracker/*`: Laravel Session Auth + WorkTracker admin allow-list + CSRF + HTTPS requirement.
- Windows Agent: Personal Access Token abilities `device:register`, `device:sync`, `device:{uuid}`.
- Admin API: `admin:read` / `admin:write` token.
- Device token cannot read invoices/reports through the admin API.

## Billing/invoices
- `/worktracker/billing` manages Customers, Activity Types, base rates and multipliers.
- `/worktracker/invoices` creates monthly drafts, finalizes immutable snapshots and exports.
- Excel is SpreadsheetML `.xls` and requires no PhpSpreadsheet package.
- PDF is produced from the authenticated print view using browser **Print → Save as PDF**, avoiding a mandatory Dompdf/font dependency on shared cPanel hosting.

## Latest migration
`2026_08_12_000600_add_invoice_exclusion_counters.php`

## Production env baseline
```env
WORKTRACKER_ADMIN_EMAILS=you@example.com
WORKTRACKER_ALLOW_ANY_AUTHENTICATED_USER=false
WORKTRACKER_REQUIRE_HTTPS=true
WORKTRACKER_DEVICE_TOKEN_DAYS=90
WORKTRACKER_ADMIN_TOKEN_DAYS=30
```

Read `docs/handoff/current-project-map.md`, `docs/architecture/security.md`, `docs/architecture/billing.md`, and `docs/architecture/invoicing.md` before deployment.
