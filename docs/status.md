# WorkTracker Status

Current release: **0.1.0-alpha.7.2 — Backend Dependency & Packaging Hotfix**

## Completed source milestones
- alpha.1: offline foreground capture, idle handling, SQLite timeline, simultaneous manual timers, additive overlap accounting.
- alpha.2: projects, deterministic classification, Unknown Inbox, explicit Assign + Learn.
- alpha.3: transactional outbox, device-bound Sanctum Sync, DPAPI token protection, Project/Rule Pull.
- alpha.4.x: central reporting/operations, durable conflicts, public-host security, cPanel/local hardening, shared UI primitives.
- alpha.5: Customers, Activity Types, base rate card, multiplicative customer/project factors and effective-dated pricing overrides.
- alpha.6: Activity Type selection/correction in Windows, billing configuration Pull, effective-dated pricing history and invoice draft/final lifecycle.
- alpha.7: historical Activity editor + Audit Log, daily/weekly/monthly/custom reports, visual overlap timeline, responsive Laravel dashboard redesign and first extracted WPF summary component.

## Important semantics
- Effort is additive. Legitimate overlap is never capped to wall-clock coverage.
- Billing uses additive billable Effort.
- Final invoices freeze pricing inputs via invoice items + BillingRateSnapshot.
- Draft invoices may be rebuilt; finalized invoices are immutable through application services.
- An Activity already included in a finalized invoice is excluded from future invoice drafts.

## Validation gap
Static PHP/XAML/SQLite checks are performed in the packaging environment. Windows Agent Release build has been validated on Windows/.NET 10. End-to-end Laravel/Sanctum/MariaDB verification on the actual deployment target remains required before production use.

## Next
- Real Windows/.NET 10 build validation, then extract Manual Timer / Timeline / Sync sections into UserControls + ViewModels.
- Work-report CSV/XLS export and saved report presets.
- Invoice payment status, cancellation/credit-note semantics.
- Browser/IDE enrichment after the record/edit/report/bill lifecycle is stable.
