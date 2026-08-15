# ADR 0010 — Billing Configuration Sync and Immutable Invoice Snapshots

Status: Accepted — 2026-08-12

## Decisions
1. Activity Types are server-managed billing configuration and are pulled to Windows for offline selection.
2. Windows sends `activity_type_id` and optional `is_billable` with Activity Sessions.
3. Project Pull includes customer id, project multiplier and billable default; Windows may display/cache these but Laravel remains authoritative for money calculation.
4. Invoice drafts can be rebuilt from source Activity Sessions.
5. Final invoices are immutable. Finalization snapshots base rate, customer factor, project factor, effective rate, billable effort and amount per Activity.
6. Billing uses additive Effort. Legitimate concurrent work creates independent invoice lines even when time ranges overlap.
7. Export avoids mandatory cPanel-hostile dependencies: Excel uses SpreadsheetML `.xls`; PDF is delivered via a print-optimized authenticated page and browser Save as PDF.
8. Activities already attached to a finalized invoice are excluded from future invoice drafts to avoid accidental double billing.

Historical pre-finalization rate resolution is defined in ADR 0011.
